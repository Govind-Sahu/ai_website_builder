# AI Website Builder — Laravel REST API

A fully-featured REST API that lets users generate website content (title, tagline, about section, services) using AI — with Sanctum authentication, CRUD, pagination, rate limiting, caching, and prompt/response history.

---

## Stack

- **Framework**: Laravel 11
- **Authentication**: Laravel Sanctum (Bearer token)
- **Database**: SQLite (zero-config dev) — swap to MySQL/PostgreSQL in `.env`
- **Cache**: File-based — swap to Redis in `.env` for production
- **AI**: OpenAI GPT-3.5-turbo with automatic mock fallback (works without any API key)

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php          # register, login, logout, me
│   │   └── WebsiteController.php       # CRUD + generate + history + stats
│   └── Requests/
│       ├── RegisterRequest.php         # register validation rules
│       ├── LoginRequest.php            # login validation rules
│       ├── GenerateWebsiteRequest.php  # generation input validation
│       └── UpdateWebsiteRequest.php    # partial update validation
├── Models/
│   ├── User.php                        # HasApiTokens, relationships, dailyGenerationCount()
│   ├── Website.php                     # Generated website content
│   └── GenerationHistory.php          # Prompt + response audit log
├── Services/
│   └── AiGeneratorService.php          # AI call, cache check, rate limit logic
└── Traits/
    └── ApiResponse.php                 # Custom JSON response format helpers

database/migrations/
├── create_users_table.php
├── create_websites_table.php           # business_name, type, desc, title, tagline, about, services(JSON)
└── create_generation_histories_table.php  # prompt(JSON), response(JSON), cache_key, from_cache

routes/api.php                          # All 11 API routes
bootstrap/app.php                       # Global exception → clean JSON error handler
```

---

## Quick Setup

```bash
# 1. Clone / open the laravel-app directory
cd laravel-app

# 2. Install PHP dependencies
composer install

# 3. Run migrations (SQLite — no DB server needed)
php artisan migrate

# 4. Start the server
php artisan serve --port=8000
```

### Optional: Enable real AI generation

Add your OpenAI key to `.env`:
```dotenv
OPENAI_API_KEY=sk-...
```
Without the key, the app returns **realistic mock data** automatically — no errors, no crashes.

---

## API Reference

**Base URL**: `http://localhost:8000/api`

All responses use this custom format:
```json
{
  "status": "success | error",
  "message": "Human-readable message",
  "data": {},
  "meta": {},
  "errors": {}
}
```

---

### Auth Routes (Public)

#### `POST /auth/register`
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```
Returns token in `data.access_token`.

#### `POST /auth/login`
```json
{ "email": "jane@example.com", "password": "password123" }
```

---

### Auth Routes (Protected — Bearer token required)

#### `POST /auth/logout`
#### `GET /auth/me` — Returns user + daily generation stats

---

### Website Routes (Protected)

#### `POST /websites/generate` — Generate from business inputs

```json
{
  "business_name": "Bloom Bakery",
  "business_type": "Restaurant",
  "description": "A family-owned artisan bakery in Brooklyn offering sourdough, pastries, and custom celebration cakes baked fresh every morning since 1998."
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Website generated successfully.",
  "data": {
    "id": 1,
    "title": "Bloom Bakery — Premium Restaurant Services",
    "tagline": "Delivering Excellence in Restaurant — Trusted by Thousands",
    "about_section": "Bloom Bakery is a leading Restaurant business...",
    "services": ["Food & Dining", "Chef's Specials", "Catering Services", "Private Events", "Online Ordering"]
  },
  "meta": {
    "from_cache": false,
    "remaining_today": 4,
    "daily_limit": 5
  }
}
```

#### `GET /websites?page=1&per_page=10` — Paginated list
#### `GET /websites/{id}` — Single website
#### `PUT /websites/{id}` — Update any field
#### `DELETE /websites/{id}` — Delete
#### `GET /websites/history` — Prompt + response log (paginated)
#### `GET /websites/stats` — Daily usage stats

---

## Features Implemented

### Feature 1: Daily Generation Limit (5/day)

```php
// User model
public function dailyGenerationCount(): int
{
    return $this->generationHistories()
        ->whereDate('created_at', today())
        ->where('from_cache', false)
        ->count();
}
```

When limit is exceeded, returns HTTP **429**:
```json
{
  "status": "error",
  "message": "Daily generation limit reached. You can generate up to 5 websites per day.",
  "errors": { "remaining_today": 0, "resets_at": "2026-04-16T23:59:59+00:00" }
}
```

### Feature 2: Store Prompt + Response History

Every generation — including cache hits — is logged to `generation_histories`:
```php
GenerationHistory::create([
    'user_id'    => $user->id,
    'website_id' => $website->id,
    'prompt'     => ['business_name' => ..., 'business_type' => ..., 'description' => ...],
    'response'   => $generated,
    'cache_key'  => $cacheKey,
    'from_cache' => $fromCache,
]);
```

### Feature 3 (Bonus): Cache Responses to Avoid Duplicate API Calls

Identical inputs produce the same `cache_key` (MD5 hash):
```php
$cacheKey = 'ai_response_' . md5(strtolower($businessName) . '|' . strtolower($businessType) . '|' . strtolower($description));
if (Cache::has($cacheKey)) {
    return ['data' => Cache::get($cacheKey), 'from_cache' => true, 'cache_key' => $cacheKey];
}
```
Cache TTL: 24 hours. Users see `"from_cache": true` in the response `meta`.

### Feature 4: Custom API Response Format

All controllers use the `ApiResponse` trait with consistent `status/message/data/meta/errors` structure.

---

## Validation Rules

| Field | Rules |
|---|---|
| `name` | required, string, max 255 |
| `email` | required, email, unique in users |
| `password` | required, min 8, confirmed |
| `business_name` | required, string, min 2, max 255 |
| `business_type` | required, string, min 2, max 100 |
| `description` | required, string, min 20, max 1000 |

Validation failures → HTTP 422 with field-level error map.

---

## cURL Examples (for Screen Recording)

```bash
# Register
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane","email":"jane@test.com","password":"secret123","password_confirmation":"secret123"}'

# Login and save token
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"jane@test.com","password":"secret123"}' \
  | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

# Generate a website
curl -X POST http://localhost:8000/api/websites/generate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"business_name":"Bloom Bakery","business_type":"Restaurant","description":"A family-owned artisan bakery in Brooklyn offering sourdough, pastries, and custom celebration cakes baked fresh every morning since 1998."}'

# Test cache (same input → from_cache: true)
curl -X POST http://localhost:8000/api/websites/generate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"business_name":"Bloom Bakery","business_type":"Restaurant","description":"A family-owned artisan bakery in Brooklyn offering sourdough, pastries, and custom celebration cakes baked fresh every morning since 1998."}'

# Test validation error
curl -X POST http://localhost:8000/api/websites/generate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"business_name":"x","description":"short"}'

# List with pagination
curl "http://localhost:8000/api/websites?page=1&per_page=5" \
  -H "Authorization: Bearer $TOKEN"

# Update
curl -X PUT http://localhost:8000/api/websites/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"tagline":"Fresh from the oven, straight to your heart."}'

# Stats
curl http://localhost:8000/api/websites/stats -H "Authorization: Bearer $TOKEN"

# History
curl http://localhost:8000/api/websites/history -H "Authorization: Bearer $TOKEN"

# Delete
curl -X DELETE http://localhost:8000/api/websites/1 \
  -H "Authorization: Bearer $TOKEN"
```

---

## Recording Q&A Answers

### Why did you design the database this way?

**Three focused tables:**

**`users`** — Standard Laravel auth table. Nothing custom needed.

**`websites`** — Stores AI-generated content per user. `services` is JSON because it's a variable-length list always read/written together — a join table would add queries for no query benefit. Compound index on `(user_id, created_at)` makes paginated user-scoped listing fast. `user_id` cascades on delete to keep data clean.

**`generation_histories`** — Deliberately separate from `websites`. It serves two jobs: (1) audit log of every AI call with full `prompt` and `response` as flexible JSON columns, and (2) the data source for daily rate limit counting (`WHERE DATE(created_at) = today`). `website_id` is nullable with `SET NULL` on delete — history persists even after website deletion. `cache_key` is indexed for fast lookup reference.

**JSON vs relational for `services`/`prompt`/`response`**: These are document-like payloads with no relational query needs. JSON avoids over-normalization and keeps the schema simple.

---

### How do you handle API failures/delays?

Three layers in `AiGeneratorService`:

```php
// Layer 1: Timeout + automatic retry with backoff
$response = Http::withToken($apiKey)
    ->timeout(30)       // fail fast at 30 seconds
    ->retry(2, 500)     // retry twice with 500ms delay
    ->post('https://api.openai.com/v1/chat/completions', [...]);

// Layer 2: Non-200 response handling
if ($response->failed()) {
    Log::error('OpenAI API error', ['status' => $response->status()]);
    return $this->mockResponse(...);  // graceful fallback
}

// Layer 3: Network / parse exception catch-all
} catch (\Exception $e) {
    Log::error('OpenAI call failed', ['error' => $e->getMessage()]);
    return $this->mockResponse(...);  // API failure never reaches the user as 500
}
```

Failures are **logged silently** and the endpoint returns realistic mock data. Users never see a 500 error from an AI outage.

---

### What breaks if 1000 users hit this API simultaneously?

**Bottlenecks ranked by severity:**

1. **`php artisan serve`** — single-process, single-thread. Handles ~1 request at a time.
   → Fix: **Nginx + PHP-FPM** (multi-worker) or **Laravel Octane** (Swoole coroutines)

2. **OpenAI rate limits** — OpenAI enforces RPM (requests per minute) per API key.
   → Fix: Response cache (already implemented) + Laravel Queue for async generation

3. **SQLite** — file-level write lock; concurrent writes queue behind each other.
   → Fix: Switch to **PostgreSQL** (`DB_CONNECTION=pgsql` in `.env`), add PgBouncer

4. **File cache** — Concurrent writes can corrupt cache files under high load.
   → Fix: `CACHE_STORE=redis` in `.env`

5. **No DB connection pooling** — 1000 connections × 1 thread each exhausts DB connections.
   → Fix: PgBouncer (PostgreSQL) or connection pooling middleware

**Production `.env` changes:**
```dotenv
DB_CONNECTION=pgsql
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=your-redis-host
SESSION_DRIVER=redis
```
