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

Still pending / out of scope: data-retention auto-purge for `location_pings` (Q2 policy undecided) and multi-event scale tuning (Q4). (FCM app push now ships on Android — see Push below.) `requests.assigned_rescuer_id`/`responded_at` are legacy (dispatch is now authoritative). Treat the manuals (`USER_MANUAL.md`, `ADMIN_MANUAL.md`) and the epic backlog as the source of truth for intended UX, and the code for what actually ships.

**Mobile (updated 2026-08-09).** Every user-facing surface is mobile-responsive, `/control` included — it switches to a full-bleed map + 3-stop snap bottom sheet below `lg`, so the older "PC-only, no responsive branch" notes in `07-web-control.md` / `control-map-spec.md` are superseded. The WebView app shell (Capacitor 8 + **remote URL**, so the origin equals the web's and sessions/CSRF/Reverb survive untouched) lives in a **separate local repo `~/Dev/gps119_app_mobile`** and runs on both platforms — see `src/docs/epics/mobile-app/`. Remaining N0 blockers are agreements/legal, not code (Play account ownership, location-data retention, 위치정보사업 신고); Apple's is resolved (individual).

**Push (updated 2026-08-09, epic N1 — ships).** **Web push (VAPID) works end to end.** **App push (FCM) is proven end to end on real hardware, both platforms** — token registration → send → notification → tap → deep-link landing, measured on an iPhone 16 Pro (iOS 18.7) and a Galaxy A36 (Android 16). Emulators/simulators are not enough here: the iOS simulator issues no APNs token, and three traps only appear on hardware — all three look identical (*nothing at all in the server access log*), so **check what URL the app was actually built with before suspecting the network**:
- `npx cap run` re-runs `cap sync` and rewrites `server.url` to the config default (`http://localhost:9050` — the phone itself). Use the repo scripts (`npm run run:ios`, `run:android:ip`) and verify the URL inside the built bundle's `capacitor.config.json`.
- iOS needs `NSLocalNetworkUsageDescription`, or the local-network prompt never appears and the app can never reach a LAN server. Safari is exempt, so it presents as "Safari works, the app doesn't".
- Android does not resolve `.local` (the emulator did, because it borrows the host resolver) — use the LAN IP, and put that IP in the cert SAN and `SANCTUM_STATEFUL_DOMAINS`. Android needs no CA install: the debug build embeds the dev CA.

Key pieces:
- `device_tokens` holds **both** app tokens and web subscriptions — one "channel that reaches this person". A token is a credential, so lookup/dedupe/logging all go through `token_hash` (unique); the raw `token` is `$hidden` on the model and is only ever accepted in a **request body**, never a URL path.
- `PushService` (policy: recipients, revoking dead tokens, logging) is separate from `PushSender` implementations (transport: `WebPushSender`, `FcmSender`). `PushMessage` is the transport-independent payload.
- `PushDelivery` splits `FAILED` (retry) from `INVALID` (revoke). Conflating them either retries dead tokens forever or deletes live devices on a blip.
- **No sender configured or no tokens → silent no-op.** A failed notification must never block a rescue request from being filed.
- 🔴 **Push payloads carry no phone numbers** (ADR-0004) — unlike the authorized channels. A test asserts this across every payload.
- Config `config/push.php`; generate keys with `php artisan push:vapid-keys` (rotating them invalidates every existing subscription). Browser side: `resources/js/push.js` + `push-toggle.js`, service worker `push`/`notificationclick` handlers in `public/sw.js`.
- **In the app there is no service worker** (WKWebView/Capacitor), so `push.js` branches to `push-native.js` (Capacitor `FirebaseMessaging`). That file owns three things the web gets for free: token registration (done by the **logged-in web page**, never the shell — the shell has no session cookie), tap routing (`notificationActionPerformed`), and **foreground display** (`notificationReceived`) — on Android FCM does not auto-display while the app is up, so without that listener the push is silently dropped.
- **Foreground display is Android-only, and it posts a real notification, not a DOM banner.** `needsForegroundNotification()` gates it: iOS already shows an OS notification (`presentationOptions` includes `alert`) and that one survives in the notification shade — leave what the OS does to the OS. Android schedules a `@capacitor/local-notifications` entry on the shell's `gps119-rescue-v1` channel (the id string must match `MainActivity.createRescueNotificationChannel()`, or Android silently downgrades to the default channel and heads-up disappears). `PushMessage::$tag` is folded into the notification id (`notificationId()`) so repeats replace instead of stacking — stacked notifications get auto-grouped and the group summary has no `extra`, which loses the deep link. The in-app banner survives only as a fallback for shells without the plugin and for `schedule()` rejecting when OS notifications are off.
- ⚠️ **`initNativePushRouting()` must be called from *every* Vite entry point** — `app.js` **and** `control/main.js`. `/control` loads only the latter, so wiring just `app.js` left the situation-room screen (the one that stays open all day) with no foreground display and dead notification taps. A spec asserts both entry points call it, matching on comment-stripped source.
- **`PushMessage::$tag` must reach every transport.** It was in `toWebPayload()` only, so web notifications replaced each other and app ones piled up; stacked Android notifications get grouped, and tapping a **group summary** loses the deep link. Now also emitted as `android.notification.tag` / `apns-collapse-id`. Note `push:test` deliberately uses a unique tag per send, so it cannot surface this class of bug.
- ⚠️ The **queue worker caches loaded classes** — run `php artisan queue:restart` after changing listeners or they keep running the old code.

**Tests.** PHP: `docker exec gps119_app-app-1 php artisan test`. JS: `docker exec gps119_app-app-1 npm test` (Vitest, `environment: node`, specs in `src/tests/js/`). JS specs cover `locationShare.js`, `roleMeta.js`, `push.js`, `push-native.js`, `native/bridge.js`, `echo.js`, and two control-SPA behaviours; the control SPA's Vue layer itself has none and is verified with gstack `browse` instead. Note that headless Chromium **denies notification permission**, so real push subscription cannot be verified there — it needs a real browser.

## Layout & Docker

The Laravel application lives in **`src/`**, not the repo root. The repo root holds only Docker config, the deploy runbook (`DEPLOY.md`), and manuals (`USER_MANUAL.md`, `ADMIN_MANUAL.md`, `EPIC-project-management.md`). `src/PROMPT.MD` is the original product spec.

The app runs in Docker (`docker-compose.yml` at root), **four services** all built from the same `docker/php/Dockerfile` and sharing the `src/` bind-mount at `/var/www/html`:
- **app** (`gps119_app-app-1`) — PHP/Apache web server. Apache also reverse-proxies WebSocket upgrades (`/app`) to the reverb service.
- **reverb** (`gps119_app-reverb-1`) — Laravel Reverb WebSocket daemon (`php artisan reverb:start`), container port `8080`.
- **queue** (`gps119_app-queue-1`) — queue worker (`php artisan queue:work database`) processing broadcasts + the `NotifyRescuers` listener.
- **db** (`gps119_app-db-1`) — MySQL 8.3.

Host ports: app `9050→80` (http) and **`9051→443` (https)**, Vite `9093`, Reverb WS `9055→8080` (dev-direct, legacy), MySQL `9052→3306`. Inside the network the app reaches reverb at host `reverb:8080`. `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=database`.

**Echo connects over the page's own origin, not a pinned host.** `resources/js/echo.js` derives host/port/scheme from `window.location` (`resolveReverbConfig`, spec `tests/js/echoConfig.test.js`); `VITE_REVERB_*` are *explicit overrides* and are left blank in `.env`. Apache reverse-proxies `/app` on both vhosts, so same-origin always works. The old pinned `VITE_REVERB_HOST` broke twice — once when the laptop's LAN IP changed (build-time constant, so it stayed broken until a rebuild) and again under https (`ws://` on an https page is blocked as mixed content).

**Local HTTPS (`9051`) exists because `navigator.geolocation`, service workers, and web push require a secure context — and `localhost` is the only http exception, private IPs are not.** So a phone or the app shell hitting `http://<LAN IP>:9050` gets geolocation refused *without a permission prompt*, which reads like "unsupported webview" and was once misdiagnosed as exactly that. Certs are mkcert-signed in `docker/apache/certs/` (gitignored); vhosts share `docker/apache/common.conf`. Device CA-trust steps and cert re-issue are in **`docker/apache/README.md`**.

**Production is a separate compose file, not a profile of the dev one (2026-08-10).** `docker-compose.prod.yml` + `docker/apache/apache-prod.conf` + `deploy.sh`, driven by two env files (`.env.deploy` for compose, `src/.env` from `src/.env.production.example`). Runbook: **`DEPLOY.md`**; the hosting decision and its NCP-migration trigger are **ADR-0006**. Target is a single AWS Lightsail Seoul VM — serverless is structurally impossible here (Reverb daemon + queue worker are always-on) and a foreign region drags in personal-data export obligations for `location_pings`. Notes that bite:
- Splitting the file is the point: mixing prod into `docker-compose.yml` is exactly how "I thought it was local" becomes a production DB.
- The prod file **does not publish db/reverb host ports** (dev publishes MySQL on `9052`), sets `restart: unless-stopped` on all four (dev omits it on `app`, so a reboot brings up everything but the web), gates startup on a db healthcheck, and caps log size.
- **Reverb still has no host port in prod** — Apache proxies `/app` on 443. That is what keeps `resolveReverbConfig` same-origin; publishing it directly reintroduces the pinned `VITE_REVERB_HOST` that broke twice. `VITE_REVERB_*` stay blank in production too.
- `deploy.sh` is not `git pull`: backup → maintenance → checkout → build → migrate → **restart queue+reverb** (the worker holds loaded classes) → health check. On failure it **stops with maintenance mode still on** rather than serving broken code. It prints `APP_ENV`/`APP_URL`/DB before doing anything.
- TLS is Let's Encrypt, not the mkcert certs — first issuance must be `--standalone` because the 443 vhost can't start without a cert, yet webroot renewal is served by that same Apache.
- **Boot-verified 2026-08-10** by an isolated local rehearsal (`-p gps119_rehearsal`, self-signed certs standing in for `/etc/letsencrypt`): `${APP_DOMAIN}` expands in both vhosts *and* in the cert path, db healthcheck gating works, db/reverb stay unpublished, `/up` serves 200 over TLS, 80→443 redirects, security headers land, and `/app` proxies to Reverb (101 + `X-Powered-By: Laravel Reverb`). What that rehearsal caught: the ACME `<Directory>` had `Options None`, which makes mod_rewrite refuse to run there (AH00670) and **403s the Let's Encrypt renewal path** — the hardening broke the thing it guarded. Now `Options FollowSymLinks`. A missing token still 301s instead of 404ing; harmless, since certbot only ever fetches a token it just wrote.
- 🔴 **Running the prod compose locally needs `-p <other-name>`.** `docker-compose.prod.yml` sets `name: gps119_app`, which collides with the dev compose's default project name (the directory). Same project → same containers → same `mysql_data` volume, so a prod-config container attaches to the **dev DB**, and `down -v` destroys it. Only bites locally; the server has one compose file.
- ⚠️ Editing a **single-file** bind mount (`apache-prod.conf`) from the host leaves the container on the old inode — `apachectl -S` then reports phantom syntax errors against a stale file. Recreate the container, don't `graceful`.
- **`deploy.sh` deploy+rollback rehearsed locally 2026-08-10** (repo cloned to scratch, `COMPOSE_PROJECT_NAME` for isolation, script itself unmodified). Three defects it caught, all now fixed: (a) 🔴 **rollback was entirely broken** — `DUMP_PATH="$(backup_db)"` also captured `log`'s stdout, so `PREV_DUMP` in `.deploy/last-release` was an ANSI-coloured log sentence; `source`ing it died with `DB: command not found`, leaving code and DB untouched at exactly the moment rollback is needed. `log`/`warn` now write to **stderr** — keep it that way, stdout is the return channel for value-returning functions. Rollback now also parses the state file instead of `source`ing it, and validates `PREV_SHA`/`PREV_DUMP` (the 14-day backup prune can delete the dump it points at). (b) §1-5 ran `key:generate` before `composer install`, so artisan could not boot. (c) `db:seed` lacked `--force`, so production silently answered "Command cancelled" and no admin user was created.
- ⚠️ Still unverified: **rehearsal on a real instance.** Real Let's Encrypt issuance, real-domain DNS, a Vite build inside 4GB, and reboot recovery are not covered by the local run — OPS-08 closes there, not here.

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

**Where a login lands.** `App\Http\Responses\LoginResponse` (bound in `FortifyServiceProvider::register` for **both** the login and two-factor contracts — landing must not change just because someone enables 2FA) sends admins to `/admin/dashboard` and everyone else to `config('fortify.home')` = `/dashboard`. `redirect()->intended()` still wins, so a deep link survives the login detour. Two related traps, both already hit once:
- `fortify.home` shipped as the skeleton default `/home`, a route this app does not have — **every successful login 404'd**. Auth itself was fine, so tests passed; they asserted *that* it redirected, never *what was at the other end*. `AuthRedirectTest` now follows the redirect.
- `/` used to bounce guests to `/requests/create`, which made the `auth` middleware store `intended=/requests/create`. That silently beat the role-based landing on the most common entry path (typing the bare domain). `/` now sends guests straight to `/login`, and a test asserts visiting `/` leaves no `intended` behind.

Round-tripping between shells: the admin sidebar's avatar dropdown links to the user screen, and the profile page shows an admin entry for admins only.

**Request flow & layering.** Business logic lives in **`app/Services/RequestService.php`**; controllers stay thin. The web `/requests/*` and `/dashboard` routes are mostly **closures defined inline in `routes/web.php`** (not controllers) — that file is the source of truth for page behavior. Push device registration lives at `POST /api/devices` / `DELETE /api/devices/current` (`DeviceTokenApiController`). The JSON API (`routes/api.php` → `app/Http/Controllers/Api/RequestApiController.php`) delegates to `RequestService` and returns a uniform envelope via the `response()->success()` / `response()->error()` **macros defined in `AppServiceProvider::boot`** — use these for all API responses.

**Events / real-time (live over Reverb).** Broadcasting runs on **Laravel Reverb** (`BROADCAST_CONNECTION=reverb`); channel authorization is wired via `->withBroadcasting(routes/channels.php)` in `bootstrap/app.php`. Channels (`routes/channels.php`):
- `requests.global` (private) — generic non-event requests; system admin/rescuer.
- `event.{projectId}.control` (private) — situation room; active `EventRole::CONTROLLER` **or** system admin.
- `event.{projectId}.locations` (presence) — any active participant; presence payload is `{user_id, role}` only.
- `event.{projectId}.dispatch.{userId}` (private) — the assigned paramedic only (own id + `canReceiveDispatch`).
- `event.{projectId}.requester.{userId}` (private) — the requester only (own id + owns a request in the event).

Broadcast events live in `app/Events`: `RequestCreated` (→ `event.{id}.control` when `project_id` set, else `requests.global`), `ParticipantLocationUpdated` (locations + control), `DispatchAssigned` (→ dispatch.{userId}, includes requester contact), `DispatchStatusUpdated` (→ control, no contact), `RequestStatusUpdated` (→ requester.{userId}, includes assigned paramedic contact). **Contact-in-payload rule (ADR-0004):** only the control / personal-dispatch / requester channels carry phone numbers — **push payloads never do** (they surface on lock screens and transit a vendor's servers).

**Event-handling conventions (2026-08-05) — follow these when adding events or listeners:**
- **Services dispatch domain events, not model hooks.** `RequestCreated` fires from `RequestService::createRequest`. It used to fire from the model's `created` hook, which made *creating a row* (a factory, a seed) indistinguishable from *a rescue request being filed* — every factory call ran the whole notification chain. The `creating` hook that attaches the default project stays on the model: that's an invariant every path must hold, not a flow-specific event.
- **Events dispatched inside a transaction implement `ShouldDispatchAfterCommit`** (all four broadcast events do). `DispatchService` publishes from inside `DB::transaction`; without this a rolled-back dispatch is still broadcast — "the paramedic's phone rang but there is no dispatch".
- **One listener per side effect.** `RequestCreated` → `NotifyRescuers` (push) *and* `AnnounceRequestToDiscord` (webhook), deliberately separate: sharing a job means a Discord failure retries the whole job and re-sends notifications that already succeeded. Same reason `DispatchAssigned` → `PushDispatchAssigned` and `RequestStatusUpdated` → `PushRequestStatusToRequester` are their own listeners.
- **Read config, not `env()`, inside listeners** — `env()` returns null once `config:cache` runs, so the effect silently switches off (`config('services.discord.webhook_url')`).

Listeners are auto-discovered from `app/Listeners` (no manual registration); `php artisan event:list` shows the wiring. Frontend `window.Echo` is initialized in `resources/js/echo.js` (imported by `bootstrap.js`).

**Event participation & dispatch (the realtime epic core).**
- `EventParticipant` (`event_participants`) ties a `User` to a `Project` with an `EventRole` + `ParticipantStatus` (pending/active/left), plus `sharing_location` and `last_lat/lng/last_seen_at` location cache. `User::eventRoleIn(Project)` returns the active role (single source for guards). Web join flow: `/events/join`, `/events/join/{joinCode}`, `/events/{id}/active`.
- Two middleware aliases gate event routes: **`event.role:controller`** (`EnsureEventRole` — resolves the project from `{requestId}`/`{dispatch}`/`{id}`, allows the listed `EventRole`s or system admin) and **`event.member`** (`EnsureEventMember` — any active participant).
- **Location pipeline:** `POST /api/events/{id}/location` (`event.member`, `throttle:2,1`) → `LocationService::record` updates the participant cache, queues a `PersistLocationPing` job into `location_pings` (append-only, no timestamps), and broadcasts `ParticipantLocationUpdated`. `sharing_location=false` short-circuits all three.
- **Dispatch state machine:** `Dispatch` (`dispatches`) + `DispatchService::assign/transition/reassign`. `DispatchStatus` (assigned→accepted→en_route→arrived→completed, plus rejected) enforces `allowedTransitions()`; transitions run in a `lockForUpdate` transaction (one active dispatch per request), are idempotent on same-status, and **DispatchService is the single writer of `requests.status`** (accepted/en_route/arrived→in_progress, completed→completed, rejected→unchanged). Endpoints: `POST /api/requests/{requestId}/dispatch`, `GET /api/requests/{requestId}/available-paramedics`, `PATCH /api/dispatches/{id}/status`, `GET /api/dispatches/mine`, `GET /api/events/{id}/dispatches`.
- **Reports:** `EventReportController` streams CSV (UTF-8 BOM, `fputcsv` + chunked) at `GET /api/events/{id}/report/{requests,dispatches,tracks}.csv` (`event.role:controller`).

**Projects.** `Project` (soft-deleted) models QR-code rescue campaigns: auto-generates a unique `slug`, a 6-char `join_code`, and computed `status` on create, and auto-deactivates past `end_date`. Public request entry is `/requests/create/{slug}`; admins manage projects, QR codes, cloning, and CSV export under `/admin/*`.

**Enums.** Domain enums live in `app/Enums` as backed string enums with **view helpers** (`label()`, `badgeClasses()`, `dotClass()`, etc.): `RequestStatus`, `RequestPriority`, `RequestType` (accident/breakdown/other/emergency, `defaultPriority()`), `EventRole` (7 roles, `markerColor()`/`canReceiveDispatch()`/`canDispatch()`), `ParticipantStatus`, `DispatchStatus` (`allowedTransitions()`/`syncsRequestStatus()`), `PushPlatform` (ios/android/web), `PushDelivery` (delivered/invalid/failed/skipped). They're cast on models — render through the helpers; query/compare with the enum cases.

⚠️ **Do not mirror enum values into JS.** That pattern drifted — 4 of 7 `EventRole` marker colours disagreed with `markerColor()` in production. The control SPA now receives them: `EventRole::mapMeta()` → `#control-app[data-role-meta]` → `initRoleMeta()` in `resources/js/control/roleMeta.js`, which keeps only what PHP cannot know (the icon shape). A Vitest spec fails if role hex literals reappear in that file, and a PHP test checks the injection path end to end. Follow the same shape for any new enum the frontend needs.

**Frontend convention.** Pages are Blade views; interactive map pages (`request/create*`, `request/show`, `event/*`, `dispatch/*`, `admin/requests/index`) pull **Vue 3 from `unpkg.com`** and `createApp(...).mount('#X')` inline per page. **Exception:** the web control SPA (`/control`) is a **Vite-bundled** Vue app (`resources/js/control/main.js`, added to `vite.config.js` input) — keep it separate from the per-page CDN pattern and from the `app.js` entry. Shared participant JS modules live in `public/js/components/` (e.g. `locationShare.js`, `dispatchMeta.js`, `kakaoNavi.js`). PWA assets are in `public/` (`manifest.webmanifest`, `sw.js`, `offline.html`, `icon-192/512.png`), registered by `resources/js/pwa.js` (imported by `app.js`) and linked from the `app` layout only. Layouts are in `resources/views/components/layouts` (`app`, `admin`); `/control` uses its own full-bleed shell.

## Conventions (from PROMPT.MD)

- Keep business logic in the **service layer**; controllers only handle request/response.
- Define enums under `App\Enums`.
- URIs follow REST; frontend↔backend communication is JSON.
- Keep secrets in `.env`, never in code.
