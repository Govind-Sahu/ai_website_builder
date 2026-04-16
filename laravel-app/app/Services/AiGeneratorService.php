<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiGeneratorService
{
    private const DAILY_LIMIT = 5;
    private const CACHE_TTL   = 3600 * 24; // 24 hours

    /**
     * Check if user has exceeded daily generation limit.
     */
    public function hasReachedDailyLimit(\App\Models\User $user): bool
    {
        return $user->dailyGenerationCount() >= self::DAILY_LIMIT;
    }

    /**
     * Get remaining generations for today.
     */
    public function remainingGenerations(\App\Models\User $user): int
    {
        return max(0, self::DAILY_LIMIT - $user->dailyGenerationCount());
    }

    /**
     * Build a cache key from the prompt inputs.
     */
    public function buildCacheKey(string $businessName, string $businessType, string $description): string
    {
        return 'ai_response_' . md5(strtolower(trim($businessName)) . '|' . strtolower(trim($businessType)) . '|' . strtolower(trim($description)));
    }

    /**
     * Generate website content — checks cache first, then calls AI (or mock).
     *
     * @return array{data: array, from_cache: bool, cache_key: string}
     */
    public function generate(string $businessName, string $businessType, string $description): array
    {
        $cacheKey = $this->buildCacheKey($businessName, $businessType, $description);

        // Feature: Cache responses to avoid duplicate API calls
        if (Cache::has($cacheKey)) {
            return [
                'data'       => Cache::get($cacheKey),
                'from_cache' => true,
                'cache_key'  => $cacheKey,
            ];
        }

        $generated = $this->callAiApi($businessName, $businessType, $description);

        // Store in cache for 24 hours
        Cache::put($cacheKey, $generated, self::CACHE_TTL);

        return [
            'data'       => $generated,
            'from_cache' => false,
            'cache_key'  => $cacheKey,
        ];
    }

    /**
     * Call the AI API (real or mocked).
     * If OPENAI_API_KEY is set, calls OpenAI; otherwise returns a realistic mock.
     */
    private function callAiApi(string $businessName, string $businessType, string $description): array
    {
        $apiKey = config('services.openai.key');

        if ($apiKey) {
            return $this->callOpenAi($businessName, $businessType, $description, $apiKey);
        }

        return $this->mockResponse($businessName, $businessType, $description);
    }

    /**
     * Real OpenAI call with timeout/failure handling.
     */
    private function callOpenAi(string $businessName, string $businessType, string $description, string $apiKey): array
    {
        $prompt = <<<PROMPT
You are a professional copywriter. Generate website content for a business.

Business Name: {$businessName}
Business Type: {$businessType}
Description: {$description}

Respond ONLY with valid JSON in this exact format:
{
  "title": "SEO-optimized website title (max 60 chars)",
  "tagline": "Compelling one-line tagline (max 100 chars)",
  "about_section": "Engaging 2-3 sentence about section",
  "services": ["Service 1", "Service 2", "Service 3", "Service 4", "Service 5"]
}
PROMPT;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->retry(2, 500)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-3.5-turbo',
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 500,
                ]);

            if ($response->failed()) {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return $this->mockResponse($businessName, $businessType, $description);
            }

            $content = $response->json('choices.0.message.content');
            $decoded = json_decode($content, true);

            if (!$decoded || !isset($decoded['title'])) {
                Log::warning('OpenAI returned invalid JSON, falling back to mock');
                return $this->mockResponse($businessName, $businessType, $description);
            }

            return $decoded;
        } catch (\Exception $e) {
            Log::error('OpenAI call failed', ['error' => $e->getMessage()]);
            // Graceful fallback — never let AI failure break the endpoint
            return $this->mockResponse($businessName, $businessType, $description);
        }
    }

    /**
     * Deterministic mock response (used when no API key is set or on failure).
     */
    private function mockResponse(string $businessName, string $businessType, string $description): array
    {
        $typeMap = [
            'restaurant' => ['Food & Dining', 'Chef\'s Specials', 'Catering Services', 'Private Events', 'Online Ordering', 'Loyalty Program'],
            'tech'       => ['Custom Software', 'Cloud Solutions', 'AI Integration', 'Cybersecurity', 'DevOps', 'Tech Consulting'],
            'retail'     => ['In-Store Shopping', 'Online Store', 'Gift Cards', 'Loyalty Rewards', 'Custom Orders', 'Free Delivery'],
            'health'     => ['Primary Care', 'Specialist Consultations', 'Telehealth', 'Lab Tests', 'Wellness Programs', 'Insurance'],
            'default'    => ['Consulting', 'Implementation', 'Support & Maintenance', 'Training', 'Analytics', 'Custom Solutions'],
        ];

        $typeKey  = strtolower($businessType);
        $services = null;
        foreach ($typeMap as $key => $list) {
            if (str_contains($typeKey, $key)) {
                $services = $list;
                break;
            }
        }
        $services = $services ?? $typeMap['default'];

        return [
            'title'         => "{$businessName} — Premium {$businessType} Services",
            'tagline'       => "Delivering Excellence in {$businessType} — Trusted by Thousands",
            'about_section' => "{$businessName} is a leading {$businessType} business dedicated to delivering exceptional quality and service. "
                . "Founded on the principles of innovation and customer satisfaction, we bring your vision to life. "
                . substr($description, 0, 150) . (strlen($description) > 150 ? '...' : ''),
            'services'      => array_slice($services, 0, 5),
        ];
    }
}
