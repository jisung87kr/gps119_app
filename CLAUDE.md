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

**Realtime dispatch & control (implemented, M0–M4).** The original GPS119 (one-shot location share) has been extended into a **realtime event-scoped dispatch platform** — see `src/docs/epics/realtime-dispatch-control/`. What now ships beyond the base concepts above:
- **Event-scoped roles via `event_participants` pivot** (the old "no `project_user` table" note is obsolete). Participants join a project by a 6-char `join_code` and hold an `EventRole` (participant/staff/police/volunteer_course/volunteer_medic/paramedic/controller). This is *separate* from the spatie system roles.
- **Live broadcasting over Laravel Reverb** (presence + private channels), replacing the old log-only best-effort path.
- **Dispatch state machine** (`dispatches` table + `DispatchService`) replaces single-field assignment as the source of truth for who is responding.
- **Realtime location tracking** (`location_pings`), a **web control SPA** (`/control`), **event record CSV exports**, and a **PWA shell** were added.

Still pending / out of scope: data-retention auto-purge for `location_pings` (Q2 policy undecided), multi-event scale tuning (Q4), and Capacitor/FCM background push. `requests.assigned_rescuer_id`/`responded_at` are legacy (dispatch is now authoritative). Treat the manuals (`USER_MANUAL.md`, `ADMIN_MANUAL.md`) and the epic backlog as the source of truth for intended UX, and the code for what actually ships.

## Layout & Docker

The Laravel application lives in **`src/`**, not the repo root. The repo root holds only Docker config and manuals (`USER_MANUAL.md`, `ADMIN_MANUAL.md`, `EPIC-project-management.md`). `src/PROMPT.MD` is the original product spec.

The app runs in Docker (`docker-compose.yml` at root), **four services** all built from the same `docker/php/Dockerfile` and sharing the `src/` bind-mount at `/var/www/html`:
- **app** (`gps119_app-app-1`) — PHP/Apache web server. Apache also reverse-proxies WebSocket upgrades (`/app`) to the reverb service.
- **reverb** (`gps119_app-reverb-1`) — Laravel Reverb WebSocket daemon (`php artisan reverb:start`), container port `8080`.
- **queue** (`gps119_app-queue-1`) — queue worker (`php artisan queue:work database`) processing broadcasts + the `NotifyRescuers` listener.
- **db** (`gps119_app-db-1`) — MySQL 8.3.

Host ports: app `9050→80`, Vite `9093`, **Reverb WS `9055→8080`** (dev-direct), MySQL `9052→3306`. The browser connects Echo to `9055` (`VITE_REVERB_PORT=9055`); inside the network the app reaches reverb at host `reverb:8080`. `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=database`.

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

**Stack:** Laravel 12 / PHP 8.2, MySQL, Blade + Vue 3 (per-page from a CDN; the `/control` SPA is the one exception — a Vite-bundled Vue app), Tailwind 4 via Vite, Kakao Maps JS API, **Laravel Reverb** (WebSocket) + Laravel Echo, **Sanctum**.

**Auth & roles.** Authentication is Laravel **Fortify** (not Breeze, despite PROMPT.MD), customized in `app/Providers/FortifyServiceProvider.php`: regular users log in by **phone number** (digits only — `User::setPhoneAttribute` strips formatting), admins by **email**. **Sanctum** secures the `/api/*` routes. **Socialite** handles Naver/Kakao login (providers registered in `AppServiceProvider::boot`). Authorization uses **spatie/laravel-permission** with three roles — `user`, `rescuer`, `admin` — seeded by `RolePermissionSeeder` (also creates `admin@admin.com`). Code checks `$user->hasRole('admin'|'rescuer')` directly; admin web routes are additionally gated by the `admin` middleware alias (`App\Http\Middleware\AdminMiddleware`).

**Request flow & layering.** Business logic lives in **`app/Services/RequestService.php`**; controllers stay thin. The web `/requests/*` and `/dashboard` routes are mostly **closures defined inline in `routes/web.php`** (not controllers) — that file is the source of truth for page behavior. The JSON API (`routes/api.php` → `app/Http/Controllers/Api/RequestApiController.php`) delegates to `RequestService` and returns a uniform envelope via the `response()->success()` / `response()->error()` **macros defined in `AppServiceProvider::boot`** — use these for all API responses.

**Events / real-time (live over Reverb).** Broadcasting runs on **Laravel Reverb** (`BROADCAST_CONNECTION=reverb`); channel authorization is wired via `->withBroadcasting(routes/channels.php)` in `bootstrap/app.php`. Channels (`routes/channels.php`):
- `requests.global` (private) — generic non-event requests; system admin/rescuer.
- `event.{projectId}.control` (private) — situation room; active `EventRole::CONTROLLER` **or** system admin.
- `event.{projectId}.locations` (presence) — any active participant; presence payload is `{user_id, role}` only.
- `event.{projectId}.dispatch.{userId}` (private) — the assigned paramedic only (own id + `canReceiveDispatch`).
- `event.{projectId}.requester.{userId}` (private) — the requester only (own id + owns a request in the event).

Broadcast events live in `app/Events`: `RequestCreated` (→ `event.{id}.control` when `project_id` set, else `requests.global`), `ParticipantLocationUpdated` (locations + control), `DispatchAssigned` (→ dispatch.{userId}, includes requester contact), `DispatchStatusUpdated` (→ control, no contact), `RequestStatusUpdated` (→ requester.{userId}, includes assigned paramedic contact). **Contact-in-payload rule (ADR-0004):** only the control / personal-dispatch / requester channels carry phone numbers. `RequestCreated` still fires from the model `created` hook; the `NotifyRescuers` listener now `implements ShouldQueue` (runs on the queue worker) — logs, notifies, and posts the **Discord webhook** (`DISCORD_WEBHOOK_URL`). Frontend `window.Echo` is initialized in `resources/js/echo.js` (imported by `bootstrap.js`).

**Event participation & dispatch (the realtime epic core).**
- `EventParticipant` (`event_participants`) ties a `User` to a `Project` with an `EventRole` + `ParticipantStatus` (pending/active/left), plus `sharing_location` and `last_lat/lng/last_seen_at` location cache. `User::eventRoleIn(Project)` returns the active role (single source for guards). Web join flow: `/events/join`, `/events/join/{joinCode}`, `/events/{id}/active`.
- Two middleware aliases gate event routes: **`event.role:controller`** (`EnsureEventRole` — resolves the project from `{requestId}`/`{dispatch}`/`{id}`, allows the listed `EventRole`s or system admin) and **`event.member`** (`EnsureEventMember` — any active participant).
- **Location pipeline:** `POST /api/events/{id}/location` (`event.member`, `throttle:2,1`) → `LocationService::record` updates the participant cache, queues a `PersistLocationPing` job into `location_pings` (append-only, no timestamps), and broadcasts `ParticipantLocationUpdated`. `sharing_location=false` short-circuits all three.
- **Dispatch state machine:** `Dispatch` (`dispatches`) + `DispatchService::assign/transition/reassign`. `DispatchStatus` (assigned→accepted→en_route→arrived→completed, plus rejected) enforces `allowedTransitions()`; transitions run in a `lockForUpdate` transaction (one active dispatch per request), are idempotent on same-status, and **DispatchService is the single writer of `requests.status`** (accepted/en_route/arrived→in_progress, completed→completed, rejected→unchanged). Endpoints: `POST /api/requests/{requestId}/dispatch`, `GET /api/requests/{requestId}/available-paramedics`, `PATCH /api/dispatches/{id}/status`, `GET /api/dispatches/mine`, `GET /api/events/{id}/dispatches`.
- **Reports:** `EventReportController` streams CSV (UTF-8 BOM, `fputcsv` + chunked) at `GET /api/events/{id}/report/{requests,dispatches,tracks}.csv` (`event.role:controller`).

**Projects.** `Project` (soft-deleted) models QR-code rescue campaigns: auto-generates a unique `slug`, a 6-char `join_code`, and computed `status` on create, and auto-deactivates past `end_date`. Public request entry is `/requests/create/{slug}`; admins manage projects, QR codes, cloning, and CSV export under `/admin/*`.

**Enums.** Domain enums live in `app/Enums` as backed string enums with **view helpers** (`label()`, `badgeClasses()`, `dotClass()`, etc.): `RequestStatus`, `RequestPriority`, `RequestType` (accident/breakdown/other/emergency, `defaultPriority()`), `EventRole` (7 roles, `markerColor()`/`canReceiveDispatch()`/`canDispatch()`), `ParticipantStatus`, `DispatchStatus` (`allowedTransitions()`/`syncsRequestStatus()`). They're cast on models — render through the helpers; query/compare with the enum cases. Some frontend code mirrors these maps in JS (clearly commented) since the browser can't call PHP enums.

**Frontend convention.** Pages are Blade views; interactive map pages (`request/create*`, `request/show`, `event/*`, `dispatch/*`, `admin/requests/index`) pull **Vue 3 from `unpkg.com`** and `createApp(...).mount('#X')` inline per page. **Exception:** the web control SPA (`/control`) is a **Vite-bundled** Vue app (`resources/js/control/main.js`, added to `vite.config.js` input) — keep it separate from the per-page CDN pattern and from the `app.js` entry. Shared participant JS modules live in `public/js/components/` (e.g. `locationShare.js`, `dispatchMeta.js`, `kakaoNavi.js`). PWA assets are in `public/` (`manifest.webmanifest`, `sw.js`, `offline.html`, `icon-192/512.png`), registered by `resources/js/pwa.js` (imported by `app.js`) and linked from the `app` layout only. Layouts are in `resources/views/components/layouts` (`app`, `admin`); `/control` uses its own full-bleed shell.

## Conventions (from PROMPT.MD)

- Keep business logic in the **service layer**; controllers only handle request/response.
- Define enums under `App\Enums`.
- URIs follow REST; frontend↔backend communication is JSON.
- Keep secrets in `.env`, never in code.
