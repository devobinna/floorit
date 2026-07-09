# Floorit: AI-Powered Flooring Visualizer

**Upload a photo of any room. Pick a flooring texture. Get a photorealistic render of what it would actually look like in seconds.**

Floorit is a full-stack SaaS platform that lets homeowners, flooring retailers, and contractors preview flooring materials (epoxy, hardwood, tile, marble, vinyl, and more) in a customer's real space before a single tile is ordered. It's built around a credit-based generation engine, a multi-provider AI orchestration layer, a public REST API, and an embeddable JavaScript widget that lets any third-party website offer the same visualizer under their own branding.

> 🔗 **Live demo:** https://floorit.app

---

## ⚠️ About this repository

This is a **curated portfolio excerpt**, not the full application. Floorit is a real, production commercial product with paying customers, so the complete codebase (business logic details, admin tooling, full test suite, deployment config, and credentials) stays private.

What's here is a representative slice of the backend and the embeddable SDK enough to demonstrate architecture, coding style, and problem-solving without exposing the whole system. Files reference services and classes (e.g. `GenerationService`, `PlanService`, the admin control panel) that exist in the full codebase but aren't included in this snapshot.

---

## The problem it solves

"What would this floor look like in *my* garage / living room / kitchen?" is a question every flooring retailer's sales team answers manually with sample boards and guesswork. Floorit turns that into a 30-second self-serve interaction: upload a photo, pick a material, get a photorealistic composite which increases conversion for retailers and removes friction for customers.

## Core features

**For end users**
- Upload a photo or capture one live via the device camera
- Choose from a curated, categorized library of flooring textures
- AI-generated photorealistic result with correct perspective, lighting, and shadow only the floor changes, everything else in the room stays untouched
- Guest mode (2 free generations/day, IP-rate-limited) with a frictionless upgrade path to a full account
- Credit-based billing, wallet top-ups, and subscription plans via Stripe

**For businesses embedding Floorit**
- A single `<script>` tag embeds a fully self-contained modal widget on any third-party site no iframe, no external CSS conflicts
- Per-integration API keys with usage tracking, rate limiting, and expiry
- REST API for headless integration (server-to-server generation + polling)

**For admins**
- Full control panel: user management, texture library management, generation monitoring, revenue/usage analytics
- Configurable AI provider routing and system settings without a redeploy

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2, Laravel 12 |
| Queue / async processing | Laravel Queues (database driver), job retries, scheduled polling |
| Database | MySQL |
| Payments | Stripe (PaymentIntents, Checkout subscriptions, webhooks) |
| Auth | Laravel Sanctum (users) + custom API key middleware (third-party integrations) |
| Frontend | Blade, Tailwind CSS 4, Vite |
| Embeddable widget | Vanilla JavaScript (zero dependencies, no build step) |
| AI generation | Google Gemini (`gemini-2.5-flash-image`) via the Generative Language API, with Replicate, Hugging Face, Magic Hour, Kei, WaveSpeed, and NanoBanana integrated as alternative providers |

## Architecture

Every generation whether it comes from a logged-in user, an anonymous guest, a third-party REST API caller, or an embedded widget on someone else's site flows through the **same orchestrator**, so business rules (credits, rate limits, validation) are enforced exactly once, regardless of entry point.

```
                     ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
                     │  Web app     │  │  Guest        │  │  Public API  │  │  Embedded SDK│
                     │  (logged-in) │  │  (rate-limited)│  │  (X-API-Key) │  │  (Bearer key)│
                     └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘
                            │                 │                 │                 │
                            └────────────┬────┴────────┬────────┴────────┬────────┘
                                         │             GenerationContext
                                         ▼
                              ┌────────────────────────┐
                              │  GenerationOrchestrator  │  ← single entry point
                              │  - resolve texture       │
                              │  - enforce rate limits    │
                              │  - reserve credits (txn)  │
                              │  - persist + dispatch job │
                              └────────────┬─────────────┘
                                           ▼
                              ┌────────────────────────┐
                              │   ProcessGeneration job   │  (queued, async, retried)
                              └────────────┬─────────────┘
                                           ▼
                              ┌────────────────────────┐
                              │  AIProviderInterface      │  ← strategy pattern
                              │  GoogleAIProvider (active) │
                              │  Replicate / HF / MagicHour │
                              │  / Kei / WaveSpeed / NanoBanana│
                              └────────────┬─────────────┘
                                           ▼
                                result saved + preview generated
                                credits refunded automatically on failure
```

### Design decisions worth highlighting

**One orchestrator, four callers.** `GenerationOrchestrator::handle()` is the single choke point every generation request passes through a guest upload from the public homepage and a server-to-server API call both build a `GenerationContext` and hand it to the same method. This means credit logic, rate limiting, and validation live in exactly one place instead of being duplicated (and drifting) across four controllers. See [`GenerationOrchestrator.php`](backend/Generation/GenerationOrchestrator.php) and [`GenerationContext.php`](backend/Generation/GenerationContext.php).

**Credits are reserved inside a locked DB transaction.** Concurrent generation requests from the same user can't race past a balance check `reserveCredits()` takes a row lock (`lockForUpdate()`) before decrementing, and refunds automatically if anything downstream fails (job dispatch failure, provider error, or a permanently-failed queue job). See the `reserveCredits()` method in the orchestrator and the `failed()` handler in [`ProcessGeneration.php`](backend/Jobs/ProcessGeneration.php).

**AI providers are pluggable, not hardcoded.** [`AIProviderInterface`](backend/Generation/Providers/AIProviderInterface.php) abstracts over both synchronous providers (Gemini returns the image inline in one call) and asynchronous ones (submit now, poll later) behind the same contract. The project evaluated six different AI backends Gemini, Replicate (Stable Diffusion inpainting + Segment Anything), Hugging Face, Magic Hour, Kei, WaveSpeed, and NanoBanana before settling on Gemini 2.5 Flash Image as the primary provider for cost and quality, with the others kept as swappable fallbacks (`config/services.php` in the full app).

**The texture image is cached at the provider, not re-uploaded every time.** [`GoogleAIProvider`](backend/Generation/Providers/GoogleAIProvider.php) uploads each flooring texture to the Google File API once and reuses the returned URI for up to 48 hours (`uploadFileToApi()`), instead of re-sending the same base64-encoded texture image on every single generation. A background warm-up job pre-populates this cache the first time a texture is used, so only the very first generation per texture pays the upload cost.

**Guest access is rate-limited without an account.** Anonymous visitors get real, working generations (no signup wall on first use) but are capped via a cache-backed daily counter keyed by IP see `checkRateLimit()` in the orchestrator. This is deliberately a soft product decision (let people try it before asking for an account) implemented as a hard technical guard (increment-before-dispatch to close the race window on concurrent guest requests).

**API keys are validated once and cached.** [`AuthenticateApiKey`](backend/Http/Middleware/AuthenticateApiKey.php) resolves a raw key to its owning user and rate limit once, caches that resolution for 5 minutes, and applies a sliding per-minute rate limit all without hitting the database on every request. Usage stats (`last_used_at`, `total_requests`) are still updated on every call for accurate analytics.

**Money and credits are separate, both append-only ledgers.** [`WalletService`](backend/Services/WalletService.php) and [`CreditService`](backend/Services/CreditService.php) never mutate a balance without writing an accompanying transaction row (`WalletTransaction` / `CreditTransaction`) inside the same DB transaction every deposit, deduction, refund, and admin-initiated transfer is fully auditable after the fact. [`PaymentService`](backend/Services/PaymentService.php) wires Stripe PaymentIntents and subscription Checkout sessions into that same ledger via webhook handling with signature verification.

**The embeddable widget is a modal, not an iframe.** [`floorit.js`](sdk/floorit.js) is a single dependency-free file that injects a style-scoped modal directly into the host page's DOM (see [`sdk/README.md`](sdk/README.md) for why this beat the original iframe-based prototype camera access, responsive sizing, and perceived performance all improved).

## Database schema

The [`database/migrations`](database/migrations) folder is included in full 27 migrations tracing the schema from the initial Laravel scaffold through additive, non-breaking changes (nullable `user_id` to support guest generations, indexes added after profiling slow queries, a new `embed_codes` table added for the SDK feature, processing-method enums extended as new AI providers were integrated). It's a reasonably honest record of how the data model actually evolved under a real product, not a schema designed all at once.

## Repository structure

```
backend/
├── Generation/
│   ├── GenerationOrchestrator.php      # single entry point for every generation request
│   ├── GenerationContext.php           # value object carrying caller identity + input
│   ├── Providers/
│   │   ├── AIProviderInterface.php     # strategy interface sync & async providers
│   │   └── GoogleAIProvider.php        # active provider: Gemini 2.5 Flash Image
│   └── Exceptions/                     # typed domain exceptions (credits, rate limit)
├── Jobs/
│   └── ProcessGeneration.php           # queued worker: calls provider, saves result, refunds on failure
├── Services/
│   ├── CreditService.php               # credit ledger (reserve / deduct / refund / transfer)
│   ├── WalletService.php               # cash wallet ledger (deposit / withdraw / refund)
│   └── PaymentService.php              # Stripe PaymentIntents, Checkout, webhook handling
├── Http/
│   ├── Controllers/Api/
│   │   ├── GenerationController.php    # REST API v1 (X-API-Key auth)
│   │   └── EmbedController.php         # SDK-facing endpoints (Bearer token auth)
│   └── Middleware/
│       └── AuthenticateApiKey.php      # key resolution, caching, sliding-window rate limiting
└── Models/
    ├── Generation.php
    └── ApiKey.php

database/migrations/                    # full schema history, 27 migrations

sdk/
├── floorit.js                          # embeddable widget, zero dependencies
└── README.md                           # SDK integration guide + design rationale
```

## Getting oriented (this repo isn't runnable standalone)

Because this is an excerpt of a larger private Laravel application, `composer install && php artisan serve` won't work here out of the box several referenced classes (`GenerationService`, `PlanService`, the admin control panel, views, routes, config) live in the full codebase and aren't included. The best way to read this repo:

1. Start with [`GenerationOrchestrator.php`](backend/Generation/GenerationOrchestrator.php) it's the spine of the whole generation pipeline.
2. Follow a request through [`GenerationContext.php`](backend/Generation/GenerationContext.php) → the orchestrator → [`ProcessGeneration.php`](backend/Jobs/ProcessGeneration.php) → [`GoogleAIProvider.php`](backend/Generation/Providers/GoogleAIProvider.php).
3. Look at [`CreditService.php`](backend/Services/CreditService.php) / [`WalletService.php`](backend/Services/WalletService.php) for the ledger pattern.
4. Open [`sdk/floorit.js`](sdk/floorit.js) and its [README](sdk/README.md) for the front-end/embed side.

## License

The code in this repository is shared under the [MIT License](LICENSE) you're free to read, learn from, and reuse these patterns. Note that this is an excerpt of a larger closed-source production application; the license covers what's shown here, not the full Floorit platform.

---

Built by **THE SOG** [GitHub](https://github.com/SOGTheFirst).
