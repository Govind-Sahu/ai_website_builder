<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateWebsiteRequest;
use App\Http\Requests\UpdateWebsiteRequest;
use App\Models\GenerationHistory;
use App\Models\Website;
use App\Services\AiGeneratorService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    use ApiResponse;

    public function __construct(private AiGeneratorService $aiService) {}

    /**
     * List all websites for the authenticated user with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 50);

        $websites = $request->user()
            ->websites()
            ->latest()
            ->paginate($perPage);

        return $this->paginatedResponse($websites, 'Websites retrieved successfully.');
    }

    /**
     * Generate a new website using AI and save it.
     */
    public function generate(GenerateWebsiteRequest $request): JsonResponse
    {
        $user = $request->user();

        // Feature 1: Daily generation limit (5 per day)
        if ($this->aiService->hasReachedDailyLimit($user)) {
            return $this->errorResponse(
                'Daily generation limit reached. You can generate up to 5 websites per day.',
                429,
                [
                    'daily_limit'       => 5,
                    'remaining_today'   => 0,
                    'resets_at'         => now()->endOfDay()->toIso8601String(),
                ]
            );
        }

        $businessName = $request->business_name;
        $businessType = $request->business_type;
        $description  = $request->description;

        // Feature 3: Cache + Feature 2: History stored regardless
        $result = $this->aiService->generate($businessName, $businessType, $description);

        $generated  = $result['data'];
        $fromCache  = $result['from_cache'];
        $cacheKey   = $result['cache_key'];

        // Save generated website
        $website = $user->websites()->create([
            'business_name' => $businessName,
            'business_type' => $businessType,
            'description'   => $description,
            'title'         => $generated['title'],
            'tagline'       => $generated['tagline'],
            'about_section' => $generated['about_section'],
            'services'      => $generated['services'],
        ]);

        // Feature 2: Store prompt + response history
        GenerationHistory::create([
            'user_id'    => $user->id,
            'website_id' => $website->id,
            'prompt'     => [
                'business_name' => $businessName,
                'business_type' => $businessType,
                'description'   => $description,
            ],
            'response'   => $generated,
            'cache_key'  => $cacheKey,
            'from_cache' => $fromCache,
        ]);

        return $this->successResponse(
            data: $website,
            message: 'Website generated successfully.',
            statusCode: 201,
            meta: [
                'from_cache'        => $fromCache,
                'remaining_today'   => $this->aiService->remainingGenerations($user),
                'daily_limit'       => 5,
            ]
        );
    }

    /**
     * Show a single website.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $website = $request->user()->websites()->find($id);

        if (!$website) {
            return $this->errorResponse('Website not found.', 404);
        }

        return $this->successResponse($website, 'Website retrieved successfully.');
    }

    /**
     * Update a website.
     */
    public function update(UpdateWebsiteRequest $request, int $id): JsonResponse
    {
        $website = $request->user()->websites()->find($id);

        if (!$website) {
            return $this->errorResponse('Website not found.', 404);
        }

        $website->update($request->validated());

        return $this->successResponse($website->fresh(), 'Website updated successfully.');
    }

    /**
     * Delete a website.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $website = $request->user()->websites()->find($id);

        if (!$website) {
            return $this->errorResponse('Website not found.', 404);
        }

        $website->delete();

        return $this->successResponse(null, 'Website deleted successfully.');
    }

    /**
     * List generation history for the authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 50);

        $history = $request->user()
            ->generationHistories()
            ->with('website:id,business_name,title')
            ->latest()
            ->paginate($perPage);

        return $this->paginatedResponse($history, 'Generation history retrieved successfully.');
    }

    /**
     * Get generation stats for the authenticated user.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse(
            data: [
                'total_websites'        => $user->websites()->count(),
                'generations_today'     => $user->dailyGenerationCount(),
                'remaining_today'       => $this->aiService->remainingGenerations($user),
                'daily_limit'           => 5,
                'total_history_entries' => $user->generationHistories()->count(),
                'cached_responses_used' => $user->generationHistories()->where('from_cache', true)->count(),
            ],
            message: 'Generation stats retrieved.'
        );
    }
}
