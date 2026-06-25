# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

GPS119 — an emergency rescue-request web app. A regular user shares their GPS location to send a 구조요청 (rescue request); rescuers/admins see requests in real time and dispatch. Requests are typically created by scanning a per-campaign QR code that links to a Project. UI and most labels are in Korean.

## Purpose & domain

**Purpose.** Let an event/activity organizer run its own GPS-based rescue dispatch alongside (not replacing) the national 119 service. The value is in situations where a victim *can't describe their location in words* — a mountain trail, a rural road, a marathon course: GPS coordinates + a Kakao Map nav link guide rescuers to the exact spot. Primary contexts are large outdoor events (marathons, hikes, festivals, cycling) and roadside/outdoor emergencies (vehicle breakdown, injury). Korea-only (rescuer dispatch and Kakao Maps assume a domestic context).

**Three core domain concepts:**

1. **User — three roles** (spatie roles). `user` = a person who may need help, logs in by **phone number**; `rescuer` = field responder who views/updates/assigns requests; `admin` = operator who manages members, requests, and projects, logs in by **email**. Phone number is domain-critical: rescuers call the requester directly, so a project request requires a phone on file (`/requests/create` redirects to `errors.require-phone` when missing).

2. **Request — the core transaction.** Captures lat/lng + address + priority (situation type) + contact phone. UI situation buttons map to priority: 사고(accident)/고장(breakdown)/기타(other)/긴급전화(direct call). Lifecycle: `pending → in_progress → completed`, plus `cancelled` — `in_progress` stamps `responded_at` (rescuer assigned/en route), `completed`/`cancelled` stamp `completed_at`. End users effectively **cannot self-cancel** (the manual tells them to phone in instead): in an emergency domain a missed request is worse than a false one.

3. **Project — event-scoped grouping (this app's distinguishing feature).** A "dedicated rescue space" per event (one marathon = one project). Each has a unique `slug` URL + QR code (`/requests/create/{slug}`); a participant who scans/clicks gets their request auto-attached to that project. Status is date-driven (pending → active → completed) and auto-deactivates past `end_date`; an inactive project shows guidance to fall back to the generic request page. Admins also get per-project stats, QR generation, clone, and CSV export.

**End-to-end flow:** organizer creates a project → issues URL/QR → posts it at the venue → participant scans, logs in (phone), pulls current location via Kakao Maps, taps a situation button → `Request` created → `RequestCreated` event notifies rescuers/admins (+ Discord webhook) → rescuer assigns (in_progress) and navigates via Kakao Map → requester watches status on the dashboard.

**Spec vs. reality.** PROMPT.MD and `EPIC-project-management.md` are the original specs; some planned items are not built. Notably there is **no `project_user` table** — "per-project dedicated rescuers" isn't implemented; new-request notifications fan out to *all* rescuers/admins by role. Per-project time-series charts and map-view project filtering are likewise not present. Treat the manuals (`USER_MANUAL.md`, `ADMIN_MANUAL.md`) as the source of truth for intended UX, and the code for what actually ships.

## Layout & Docker

The Laravel application lives in **`src/`**, not the repo root. The repo root holds only Docker config and manuals (`USER_MANUAL.md`, `ADMIN_MANUAL.md`, `EPIC-project-management.md`). `src/PROMPT.MD` is the original product spec.

The app runs in Docker (`docker-compose.yml` at root): **PHP/Apache** in container `gps119_app-app-1`, **MySQL 8.3** in `gps119_app-db-1`. `src/` is bind-mounted into the web container at `/var/www/html`. Host ports: app `9050→80`, Vite `9093`, MySQL `9052→3306`.

**Run all `artisan`/`composer`/`npm` commands inside the app container**, e.g.:

```bash
docker compose up -d
docker exec gps119_app-app-1 php artisan migrate
docker exec gps119_app-app-1 php artisan db:seed --class=RolePermissionSeeder
docker exec gps119_app-app-1 php artisan test                  # all tests
docker exec gps119_app-app-1 php artisan test --filter=SomeTest # single test
docker exec gps119_app-app-1 ./vendor/bin/pint                  # format (Laravel Pint)
docker exec gps119_app-app-1 php artisan pail                   # tail logs
```

Frontend (Vite + Tailwind 4) runs from `src/`: `npm run dev` (HMR server on `0.0.0.0:9093`) or `npm run build`. Vite is configured for the bind-mount, so run it inside the container or with the container's network reachable.

Note: `.env.example` defaults to `sqlite`, but the real `src/.env` points at the MySQL `db` container. The `docker/php/entrypoint.sh` auto-installs a fresh Laravel only if `app/` is missing — it won't touch an existing checkout.

## Architecture

**Stack:** Laravel 12 / PHP 8.2, MySQL, Blade + Vue 3 (loaded per-page from a CDN), Tailwind 4 via Vite, Kakao Maps JS API.

**Auth & roles.** Authentication is Laravel **Fortify** (not Breeze, despite PROMPT.MD), customized in `app/Providers/FortifyServiceProvider.php`: regular users log in by **phone number** (digits only — `User::setPhoneAttribute` strips formatting), admins by **email**. **Sanctum** secures the `/api/*` routes. **Socialite** handles Naver/Kakao login (providers registered in `AppServiceProvider::boot`). Authorization uses **spatie/laravel-permission** with three roles — `user`, `rescuer`, `admin` — seeded by `RolePermissionSeeder` (also creates `admin@admin.com`). Code checks `$user->hasRole('admin'|'rescuer')` directly; admin web routes are additionally gated by the `admin` middleware alias (`App\Http\Middleware\AdminMiddleware`).

**Request flow & layering.** Business logic lives in **`app/Services/RequestService.php`**; controllers stay thin. The web `/requests/*` and `/dashboard` routes are mostly **closures defined inline in `routes/web.php`** (not controllers) — that file is the source of truth for page behavior. The JSON API (`routes/api.php` → `app/Http/Controllers/Api/RequestApiController.php`) delegates to `RequestService` and returns a uniform envelope via the `response()->success()` / `response()->error()` **macros defined in `AppServiceProvider::boot`** — use these for all API responses.

**Events / real-time.** Creating a `Request` fires `RequestCreated` from the model's `booted()` `created` hook (`app/Models/Request.php`). `RequestCreated implements ShouldBroadcast` (channels `requests`, private `rescuers`) and is auto-discovered by the `NotifyRescuers` listener, which logs, notifies rescuers/admins, and posts to a **Discord webhook** (`DISCORD_WEBHOOK_URL`). `BROADCAST_CONNECTION` defaults to `log` and the queue listener is currently disabled in the listener — treat broadcasting/notifications as best-effort, synchronous side effects.

**Projects.** `Project` (soft-deleted) models QR-code rescue campaigns: auto-generates a unique `slug` and computed `status` on create, and auto-deactivates past `end_date`. Public entry point is `/requests/create/{slug}`; admins manage projects, QR codes, cloning, and CSV export under `/admin/*`.

**Enums.** Domain enums live in `app/Enums` (`RequestStatus`, `RequestPriority`) as backed string enums and carry **view helpers** (`label()`, `badgeClasses()`, `dotClass()`, `isActive()`) — render status/priority through these rather than hardcoding Korean strings or Tailwind classes. They're cast on the model, so query scopes and comparisons use the enum cases.

**Frontend convention.** Pages are Blade views; interactive map pages (`request/create*`, `request/show`, `admin/requests/index`) pull **Vue 3 from `unpkg.com`** and `createApp(...).mount('#app')` inline per page (no SPA build, no app-wide Vue bundle). Shared map logic and the Kakao Maps integration live in page scripts. Layouts are in `resources/views/components/layouts` (`app`, `admin`).

## Conventions (from PROMPT.MD)

- Keep business logic in the **service layer**; controllers only handle request/response.
- Define enums under `App\Enums`.
- URIs follow REST; frontend↔backend communication is JSON.
- Keep secrets in `.env`, never in code.
