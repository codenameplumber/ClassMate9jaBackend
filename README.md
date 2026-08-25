# ClassMate 9ja — Platform

Laravel 13 + Livewire 4 + MySQL. The server-rendered app for teachers, parents
and admins, the API for the offline student PWA, and the marking pipeline.

---

## Run it

WAMP (or any MySQL on 3306) needs to be running.

```bash
cd platform
composer install
php artisan key:generate          # only if .env has no APP_KEY yet
php artisan migrate:fresh --seed  # builds the curriculum and the demo school
php artisan serve --port=8080
```

Open <http://127.0.0.1:8080>.

If the queue is doing real work (AI marking), run a worker alongside:

```bash
php artisan queue:work
```

## Sign in

Password for all of them: `password`

| Role | Email | Lands on |
|---|---|---|
| Teacher | teacher@classmate9ja.ng | `/teacher` — 30 scripts waiting |
| Parent | parent@classmate9ja.ng | `/parent` |
| Admin | admin@classmate9ja.ng | `/admin` |
| Student | student@classmate9ja.ng | `/app/student/dashboard.html` |

The login page has **Tap to fill** buttons, so nobody has to type any of that.

## The 90-second demo

1. Log in as the **teacher** — the dashboard opens on *30 scripts pending*
2. **Start marking**. Every answer already carries a mark, a confidence score,
   and the comment the student will see
3. Press **A** repeatedly and watch the pace counter fall to a few seconds per
   script
4. Stop on one flagged **⚠ Needs your eye** — those are the written answers the
   marker was under 80% sure about, sorted to the top
5. Adjust a mark with +/−, approve, then **Approve confident** to clear the rest
6. **Reports → Print** for the head-teacher version

---

## What is where

```
app/
  Livewire/            One component per screen
    Auth/              login, register, forgot, reset, onboarding
    Teacher/           dashboard, classes, assignments, marking, reports
    ParentArea/        dashboard, report
    Admin/             overview, curriculum, people
  Models/              18 Eloquent models
  Services/
    Ai/Marker.php      The provider seam — one interface, two drivers
    Ai/OpenAiMarker.php
    Ai/NullMarker.php  The no-key driver; the platform runs fully on this
    AssignmentDrafter  Builds a paper from AI, or from the question bank
  Jobs/MarkSubmission  Objective marking in PHP, written answers to the model
  Support/
    ClassStats         Everything the teacher screens compute
    ChildSummary       Everything the parent screens compute
    Navigation         The sidebar, defined once per role
  Http/
    Controllers/Api/   Auth, Account, Sync (classwork), Tutor
    Middleware/        EnsureRole, ApiToken
  Services/Ai/Tutor    The live tutor; cached, and optional

database/
  migrations/          The whole schema in one file
  content/*.json       Curriculum exported from the front-end js/data modules
  seeders/             ContentSeeder (curriculum) + DemoSeeder (demo school)

public/app/            The offline student PWA, served as static files
resources/views/
  layouts/             app (dashboard shell) + guest (auth)
  livewire/            One Blade view per component
  mail/                The weekly parent report
```

### The design system is not duplicated

Blade links `/app/css/*.css` — the same stylesheets the PWA uses. There is one
copy of the design tokens on disk, so the server-rendered screens and the
offline app can never drift apart.

### The curriculum is not transcribed

`database/content/*.json` is generated from the front-end's `js/data` modules,
which stay the place content is authored:

```bash
node ../scratch/export-content.mjs   # regenerates the JSON
php artisan db:seed --class=ContentSeeder
```

Currently seeded: **12 subjects · 288 topics · 576 lessons** (every one in both
English and Pidgin) · **579 classwork questions** · **42 past questions**.

---

## AI

The platform is **fully functional with no API key at all**, and that is
deliberate:

- **Objective marking** is `$chosen === $answer_index` in PHP — free, instant,
  exact, and it never touches a model.
- **The tutor** answers from the response layer already shipped on the device.
- **Written answers** are the only thing a model is needed for. With no key,
  they are marked *"Awaiting your mark"* and wait in the teacher's queue.

OpenAI does three jobs once a key is present — written marking, assignment
drafting, and the live tutor:

```
AI_DRIVER=openai
AI_MODEL=gpt-4o-mini        # marking and drafting
AI_TUTOR_MODEL=gpt-4o       # the tutor talks to students directly
OPENAI_API_KEY=sk-...
AI_CONFIDENCE_FLOOR=80
```

The tutor caches every answer server-side for 30 days, keyed on the question,
topic and language. Thirty students on the same topic ask nearly the same
thing, so the second one onward costs nothing — and the cached answer is what
gets shipped to devices for offline use.

`AI_CONFIDENCE_FLOOR` is the line below which an answer is flagged for a human
and pushed to the top of the queue. Below that line the marker is asking for a
teacher, not making a decision.

Swapping provider means writing one more class implementing
`App\Services\Ai\Marker` and changing one config line. No screen and no job
touches a vendor SDK.

---

## The student app

The PWA is served from `/app/` and is a real client now, not a demo with
bundled data:

```
POST /api/login             → bearer token
POST /api/register          → creates the account on the server
POST /api/forgot-password   → emails a reset link
POST /api/onboarding        → class, subjects, goal, language, daily goal
PATCH /api/me               → settings changes

GET  /api/bootstrap         → subjects, topics, lessons in both locales
POST /api/state             → the whole progress blob, last-write-wins
POST /api/attempts          → a finished practice set or mock

GET  /api/assignments       → classwork the teacher set
GET  /api/assignments/{id}  → one paper, questions only — never the answer key
POST /api/submissions       → a completed paper; queues the marking job
GET  /api/submissions       → marked scripts, released only once approved

POST /api/tutor             → a live answer; falls back to the on-device tutor
```

### The loop, verified end to end

Teacher publishes → student sees it in **Classwork** → answers objective and
written questions → submits (a resend is deduped by `client_uuid`) → objective
answers are marked instantly in PHP and the written one is flagged → teacher
marks it and approves → the student sees the grade and the teacher's comment.

A student never sees an AI mark before a teacher has approved it. An unapproved
script returns `released: false` with no score and no comments — showing a draft
as a grade is the fastest way to lose a teacher's trust.

Two things make this survivable on a bad connection:

- **`client_uuid` on everything a device writes.** A retried request upserts
  instead of duplicating, so the app can retry blindly.
- **Nothing in `js/core/api.js` ever throws.** Losing signal returns
  `{offline: true}` and the app carries on; failed writes park in a local queue
  and drain when the network returns.

Signing in tries the server first and falls back to the local account when
offline — being offline must never lock a student out of their own lessons.

### Editing the front-end

`platform/public/app/` is a copy. Author in the repo root, then re-copy:

```bash
cd ..
cp -r css js auth student assets index.html manifest.json sw.js reset.html platform/public/app/
```

---

## Email

`MAIL_MAILER=log` by default, so nothing needs SMTP credentials — sent mail
lands in `storage/logs/laravel.log`. Point `MAIL_MAILER` at `smtp` and fill the
usual `MAIL_*` variables for real delivery.

Weekly parent reports:

```bash
php artisan reports:weekly          # today's scheduled links
php artisan reports:weekly --day=sunday
```

The parent report screen also has an **Email it to me** button.

Alerts are sent as Laravel **Notifications**, not raw Mailables, so adding SMS
later is `via()` returning `['mail', 'vonage']` rather than a rewrite.

---

## Tests

```bash
php artisan test
```

52 tests, 171 assertions. They run against the real seeded MySQL database inside
a transaction, so they exercise the actual curriculum and the actual marking
queue and roll back afterwards. Covered: every screen renders per role, role
gates redirect, login succeeds and fails correctly, approving a script stores
the teacher's number, an override beats the AI mark, bulk approve leaves flagged
scripts behind, the API bootstraps, a bad token is rejected, and a resent
attempt is not duplicated.

---

## Deliberately not built yet

- **Sanctum** — one hashed token column and one middleware covers
  single-device-per-student. Swapping it in replaces `User::issueApiToken()`
  and `ApiToken` middleware, nothing else.
- **Redis / Horizon** — the database queue driver is enough at this volume.
- **Offline marking for teachers** — marking needs network in v1. Stated
  plainly rather than half-implemented.
- **A queue worker in production** — `MarkSubmission` is queued, so without
  `php artisan queue:work` running as a service, marking silently never
  happens. Tests pass only because the test environment runs jobs
  synchronously.
- **Multi-device students** — the blob sync is last-write-wins. The event-log
  design is the upgrade path when it is actually needed.
- **CDN pack delivery, payments, SMS** — see the architecture brief.
