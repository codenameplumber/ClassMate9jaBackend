# ClassMate 9ja

An offline-first AI learning and class-management platform for Nigerian
secondary schools, WAEC and JAMB candidates.

Students learn, practise and sit exams with **zero data**. Teachers get thirty
scripts back already marked, with a confidence score on every answer. Parents
get one email a week that tells them what to actually do about it.

---

## Documents

| | |
|---|---|
| **Demo logins** — ten accounts, tap to copy | https://claude.ai/code/artifact/43c727df-32d2-4726-b48c-3a4b5039f1ce |
| **Technical overview** — architecture, AI engine, measured performance | https://claude.ai/code/artifact/92138c0b-0556-416d-b3f9-4bc736e975fa |
| **Build status audit** — page by page, what works and what does not | https://claude.ai/code/artifact/de0a7ad3-80f6-4b32-8785-31b3c5f22bd0 |
| **Architecture brief** — why Livewire runs half of this and not the other half | https://claude.ai/code/artifact/f843ad50-9f95-43c9-8698-d46f1147e27e |

---

## Run it

MySQL must be running (WAMP is fine).

```bash
cd Classmate9ja-Laravel
composer install
php artisan key:generate        # only if .env has no APP_KEY
php artisan migrate:fresh --seed
php artisan serve --port=8080
```

Open <http://127.0.0.1:8080>.

Marking runs on a queue, so in a second terminal:

```bash
php artisan queue:work
```

Without a worker, submitted scripts stay unmarked. This is the single most
common "why is nothing happening" cause.

## Sign in

Password for every seeded account: **`password`**

| Role | Email | Lands on |
|---|---|---|
| Teacher | `teacher@classmate9ja.ng` | 30 scripts waiting to mark |
| Student | `student@classmate9ja.ng` | The offline app at `/app` |
| Parent | `parent@classmate9ja.ng` | This week's verdict on their child |
| Admin | `admin@classmate9ja.ng` | Platform counts and the curriculum |

Five more teachers: `teacher00@` through `teacher21@classmate9ja.ng`.
The other 270 students and 85 parents are searchable in **Admin - People**.

The login page has **Tap to fill** buttons, so nobody types any of that.

## The 90-second demo

1. Log in as the **teacher** — the dashboard opens on *30 scripts pending*
2. **Start marking**. Every answer carries a mark, a confidence score, and the
   comment the student will see
3. Press **A** repeatedly and watch the pace counter fall to seconds per script
4. Stop on one flagged **Needs your eye** — the written answers the marker was
   under 80% sure about, sorted to the top
5. Adjust a mark with +/-, approve, then **Approve confident** to clear the rest
6. **Reports - Print** for the head-teacher version

---

## What is in the box

Seeded by `php artisan migrate:fresh --seed`:

| | |
|---|---|
| Schools | 3 |
| Classes | 13 |
| Students | 270 |
| Parents | 85 |
| Teachers | 7 |
| Subjects | 12 |
| Topics | 288 |
| Lessons | 576 — every one in **both English and Pidgin** |
| Classwork questions | 579 |
| Past questions | 42 (WAEC, JAMB, NECO) |
| Assignments | 39 |
| Marked scripts | 511 |
| Practice attempts | 483 |

Everything is deterministic — names, abilities and dates all derive from a hash
of the record — so the same database comes out of every run and a demo never
shifts under you.

---

## Architecture

Three tiers, one Laravel application, one database.

| Tier | Stack | Who uses it |
|---|---|---|
| Staff web app | Livewire 4, Blade, session auth | Teachers, parents, admins |
| Student app | Offline PWA, service worker | Students |
| Sync and content API | Bearer token, 15 endpoints | Between the two |

The split is forced by one fact: **Livewire pays a network round trip per
interaction, and a student studying with the data off cannot pay one.** So the
student surface stays a PWA and Laravel is the system of record behind it.
Teachers and parents are online when they work, so Livewire is exactly right
for them.

```
app/
  Livewire/            One component per screen
    Auth/              login, register, forgot, reset, onboarding
    Teacher/           dashboard, classes, assignments, marking, reports
    ParentArea/        dashboard, report
    Admin/             overview, curriculum, people
  Models/              18 Eloquent models
  Services/
    Ai/Marker.php      The provider seam - one interface
    Ai/ClaudeMarker    Written marking + assignment drafting
    Ai/Tutor.php       The live tutor
    Ai/NullMarker      The no-key driver; the platform runs fully on this
    Ai/Schema/         Structured-output shapes
    AssignmentDrafter  A paper from Claude, or from the question bank
  Jobs/MarkSubmission  Objective marking in PHP, written answers to Claude
  Support/
    ClassStats         Everything the teacher screens compute
    ChildSummary       Everything the parent screens compute
    Navigation         The sidebar, defined once per role
  Http/
    Controllers/Api/   Auth, Account, Sync, Tutor
    Middleware/        EnsureRole, ApiToken
  Console/Commands/    reports:weekly, mail:check

database/
  migrations/          The whole schema in one file
  content/*.json       Curriculum exported from the design's js/data modules
  seeders/             Content, Demo (the marking queue), Scale (3 schools)

public/app/            The offline student PWA, served as static files
resources/views/
  layouts/             app (dashboard shell) + guest (auth)
  livewire/            One Blade view per component
  mail/                The weekly parent report
```

### The design system is not duplicated

Blade links `/app/css/*.css` — the same stylesheets the PWA uses. One copy of
the design tokens on disk, so the server-rendered screens and the offline app
cannot drift apart.

### The curriculum is not transcribed

`database/content/*.json` is generated from the design's `js/data` modules,
which stay the place content is authored. After changing content there,
re-export the JSON and reseed:

```bash
php artisan db:seed --class=ContentSeeder
```

---

## The AI engine — Claude

Three unrelated workloads with three different cost profiles:

| Job | How it runs | With no key |
|---|---|---|
| **Objective marking** | `$chosen === $answer_index` in PHP | Unaffected — free, instant, exact |
| **Written marking** | Claude, marking scheme in a cached system block | Flagged for a teacher |
| **Assignment drafting** | Claude writes paper + marking scheme | Built from the question bank |
| **Tutor** | Claude, cached 30 days | The on-device rule tutor answers |

```
AI_DRIVER=claude
AI_MODEL=claude-opus-5
AI_TUTOR_MODEL=claude-opus-5
AI_CONFIDENCE_FLOOR=80
ANTHROPIC_API_KEY=sk-ant-...
```

Two decisions worth knowing:

**Marks are a structured output, not parsed prose.** `MarkResult` declares typed
`mark` / `confidence` / `comment` fields validated at the API boundary. A
pipeline that regexes a number out of a sentence is one odd reply away from
writing a wrong grade into a student's record.

**Every failure path returns `null`, never a guess.** A refusal, a timeout, a
malformed reply — all of them mean "a human marks this". `AI_CONFIDENCE_FLOOR`
is the line below which an answer is flagged and sorted to the top of the
teacher's queue: under it, the model is asking for help, not deciding.

Swapping provider means one class implementing `App\Services\Ai\Marker` and one
config line. No screen and no job touches a vendor SDK.

---

## Email

Mailtrap sandbox SMTP, verified working end to end.

```
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=<from your Mailtrap inbox>
MAIL_PASSWORD=<from your Mailtrap inbox>
MAIL_FROM_ADDRESS="hello@classmate9ja.ng"
MAIL_THROTTLE_PER_MINUTE=5
```

Send one of every email the platform produces, through the real transport:

```bash
php artisan mail:check
php artisan mail:check --to=you@example.com
```

That covers the SMTP smoke test, a password reset link, an email verification,
the weekly parent report, and the report for a student with no data at all —
the case that breaks report builders.

### About MAIL_THROTTLE_PER_MINUTE

Mailtrap's free sandbox accepts roughly **one message per ten seconds** and
answers a burst with `550 Too many emails per second`. A school with 500 linked
parents would lose most of a Sunday send to that.

So bulk sends space themselves: `reports:weekly` queues each report with an
increasing delay rather than firing them all at once. Raise the number on a
production plan; `5` is tuned for the free sandbox.

```bash
php artisan reports:weekly              # today's scheduled links
php artisan reports:weekly --day=sunday -v
```

The parent report screen also has an **Email it to me** button.

Alerts are Laravel **Notifications**, not raw Mailables, so adding SMS later is
`via()` returning `['mail', 'vonage']` rather than a rewrite.

---

## The student app

Served from `/app`, and a real client rather than a demo with bundled data.

```
POST  /api/login              -> bearer token (throttled 8/min)
POST  /api/register           -> creates the account server-side
POST  /api/forgot-password    -> emails a reset link
POST  /api/onboarding         -> class, subjects, goal, language, daily goal
PATCH /api/me                 -> settings

GET   /api/bootstrap          -> subjects, topics, lessons in both locales
POST  /api/state              -> the whole progress blob, last-write-wins
POST  /api/attempts           -> a finished practice set or mock

GET   /api/assignments        -> classwork the teacher set
GET   /api/assignments/{id}   -> one paper, never the answer key
POST  /api/submissions        -> a completed paper; queues marking
GET   /api/submissions        -> marks, released only once approved

POST  /api/tutor              -> a live answer, or a graceful decline
```

### The loop, verified end to end

Teacher publishes, student sees it in **Classwork**, answers objective and
written questions, submits (a resend is deduped by `client_uuid`), objective
answers are marked instantly in PHP and written ones sent to Claude or flagged,
teacher adjusts and approves, and the student sees the grade and the comment.

**A student never sees an unapproved mark.** An AI mark nobody has signed off is
a draft; the API returns `released: false` with no score and no comments until
approval. Showing a draft as a grade is the fastest way to lose a teacher.

Two properties make this survivable on a bad connection:

- **`client_uuid` on everything a device writes** — a retry upserts instead of
  duplicating, so the app can retry blindly.
- **Nothing in `js/core/api.js` ever throws** — losing signal returns
  `{offline: true}`, failed writes park in a local queue, and the queue drains
  when the network returns.

Signing in tries the server first and falls back to the local account when
offline. Being offline must never lock a student out of their own lessons.

### Editing the front-end

`public/app/` is a copy. The design lives in `../Classmate9ja`. Author there,
then re-copy:

```bash
cd ../Classmate9ja
cp -r css js auth student teacher parent assets index.html manifest.json sw.js reset.html \
      ../Classmate9ja-Laravel/public/app/
```

---

## Tests

```bash
php artisan test
```

**67 tests, 218 assertions, all passing.** They run against the real seeded
MySQL database inside a transaction, so they exercise the actual curriculum and
the actual marking queue, then roll back.

| Suite | What it proves |
|---|---|
| `PlatformSmokeTest` | Every screen renders per role; role gates redirect; login succeeds and fails correctly |
| `AuditTest` | Adversarial probes: cross-teacher access, cross-class submission, login throttling, queue ordering, marking exactness |
| `StudentApiTest` | The classwork loop, the answer key never leaking, marks hidden until approval, onboarding, tutor degradation |
| `ScaleTest` | Query budgets and timings at 270 students, mark distribution, email rendering, AI binding |

### Measured at scale

Warm renders against 270 students and 511 scripts:

| Screen | Time | Queries |
|---|---|---|
| Teacher dashboard | 153 ms | 14 |
| Class roster | 157 ms | 10 |
| Marking queue | 195 ms | 14 |
| Class report | 194 ms | 12 |

Flat query counts across every screen — no N+1 anywhere. The first request of a
deployment is slower because it compiles Blade templates; that is a build cost,
not a page cost, so the profiler warms views before measuring.

### Four security defects, found and fixed

The audit suite is written to fail rather than to confirm. It found four real
problems, each of which now has a regression test:

- A teacher could open **and approve** another teacher's marking queue by URL.
  Ownership is now checked inside the `assignment` accessor, not just the route
  — the id is a Livewire property the browser can set.
- A student could submit work into **any** classroom. Class membership is now
  the authorisation check.
- The API login endpoint had **no throttle**. Now `throttle:8,1` on every
  unauthenticated write endpoint.

---

## Going live

| Concern | Now | For production |
|---|---|---|
| Queue worker | Not running by default | **Required.** Supervise `php artisan queue:work`, or marking silently never happens |
| Scheduler | 3 weekly-report jobs registered | One crontab line: `* * * * * php artisan schedule:run` |
| Email | Mailtrap sandbox, working | A real provider and a verified sending domain; raise `MAIL_THROTTLE_PER_MINUTE` |
| App environment | `APP_DEBUG=true`, local URL | `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `config:cache` |
| Database | root, no password | A dedicated user with a password |
| Email verification | Implemented, not enforced | Add `verified` middleware to the dashboards |

### Before anyone's work is marked for real

Take 100 answers a teacher has already marked, run them through, and measure
agreement. The confidence floor lets the model ask for help — but a marker that
is confidently wrong twice a class is worse than no marker, and only measurement
tells you which one you have.

---

## Known outstanding

- **The Livewire teacher and parent screens do not match the designed pages.**
  The designs are in `../Classmate9ja/teacher` and `../Classmate9ja/parent`; the
  Blade views use the same CSS but are a re-interpretation rather than a port.
  Making them match is a rebuild of five teacher and two parent views.
- **No Claude call has executed yet.** `ANTHROPIC_API_KEY` is empty, so only the
  null driver is exercised. The paths are wired and covered on the fallback.
- **`../Classmate9ja/platform/` is a stale duplicate** of this application from
  before the folder was renamed. It is what the SMTP credentials first landed
  in. Safe to delete once you are happy nothing of yours is in it.
