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
| **Demo logins** — eighteen real accounts, tap to copy | https://claude.ai/code/artifact/43c727df-32d2-4726-b48c-3a4b5039f1ce |
| **Technical overview** — architecture, AI engine, measured performance | https://claude.ai/code/artifact/92138c0b-0556-416d-b3f9-4bc736e975fa |
| **Build status audit** — page by page, what works and what does not | https://claude.ai/code/artifact/de0a7ad3-80f6-4b32-8785-31b3c5f22bd0 |
| **Architecture brief** — why Livewire runs half of this and not the other half | https://claude.ai/code/artifact/f843ad50-9f95-43c9-8698-d46f1147e27e |
| **Competition submission** — problem, prototype, workflow, tools, I/O, evidence, impact | https://claude.ai/code/artifact/f2c3bbd1-f08a-476e-8a67-9f6055d9cae5 |
| **Deploying on Laravel Cloud** — queue workers, scheduler, the two platform limits that bite | [DEPLOY.md](DEPLOY.md) |

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

Marking, mail and course generation all run on queues, so in a second
terminal:

```bash
php artisan queue:work --queue=marking,mail,content
```

The order matters: a submitted script always jumps ahead of a report blast or
a course being written.

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

### All seven teachers

| Email | Name | Classes | School | Pending |
|---|---|---|---|---|
| `teacher@classmate9ja.ng` | Mrs. Folake Adebayo | SS2A | GSS Wuse, FCT | 30 |
| `teacher00@classmate9ja.ng` | Mrs. Ngozi Musa | SS2A, SS1B | GSS Wuse, FCT | 40 |
| `teacher01@classmate9ja.ng` | Mrs. Halima Bala | SS2A, SS1B | GSS Wuse, FCT | 40 |
| `teacher10@classmate9ja.ng` | Mrs. Kelechi Nnamdi | SS2A, SS1B | Command Day, Ikeja | 40 |
| `teacher11@classmate9ja.ng` | Mr. Titilayo Shehu | SS2A, SS1B | Command Day, Ikeja | 40 |
| `teacher20@classmate9ja.ng` | Mr. Morenike Nwachukwu | SS2A, SS1B | Holy Trinity, Enugu | 40 |
| `teacher21@classmate9ja.ng` | Mrs. Tunde Sani | SS2A, SS1B | Holy Trinity, Enugu | 40 |

### Six students, all in SS2A

| Email | Name |
|---|---|
| `student@classmate9ja.ng` | Chidera Okonkwo |
| `aishabello@demo.classmate9ja.ng` | Aisha Bello |
| `tundeadeyemi@demo.classmate9ja.ng` | Tunde Adeyemi |
| `ngozieze@demo.classmate9ja.ng` | Ngozi Eze |
| `ibrahimmusa@demo.classmate9ja.ng` | Ibrahim Musa |
| `halimayusuf@demo.classmate9ja.ng` | Halima Yusuf |

### Four parents

| Email | Name | Child |
|---|---|---|
| `parent@classmate9ja.ng` | Mr. Emeka Okonkwo | Chidera Okonkwo |
| `parent.emekaokoro.mat-0000@demo.classmate9ja.ng` | Mrs. Amina Okoro | Emeka Okoro |
| `parent.tundelawal.mat-0003@demo.classmate9ja.ng` | Mr. Uche Lawal | Tunde Lawal |
| `parent.titilayoadesina.mat-0006@demo.classmate9ja.ng` | Mr. Olumide Adesina | Titilayo Adesina |

The remaining 264 students and 81 parents are searchable in **Admin - People**.
The login page has **Tap to fill** buttons for the four main roles.

## Where the data lives

| | |
|---|---|
| Database | `classmate9ja` |
| Host | `127.0.0.1:3306` |
| User | `root`, no password |
| Accounts | 363 — 270 students, 85 parents, 7 teachers, 1 admin |
| Marked scripts | 512, across 1,837 marked answers |

If phpMyAdmin looks empty, confirm you are on the WAMP MySQL instance at
`127.0.0.1:3306` and refresh the schema list. Rebuild at any time with
`php artisan migrate:fresh --seed`.

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
| Subject offerings | 45 declared, 24 filled, 21 awaiting generation |
| Students | 270 |
| Parents | 85 |
| Teachers | 7 |
| Subjects | 12 |
| Topics | 288 |
| Lessons | 576 — every one in **both English and Pidgin** |
| Classwork questions | 579 |
| Past questions | 42 (WAEC, JAMB, NECO) |
| Assignments | 39 |
| Marked scripts | 512 |
| Practice attempts | 484 |

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
    Admin/             overview, courses, curriculum, teachers, students, people
  Models/              18 Eloquent models
  Services/
    Ai/Marker.php      The marking seam - one interface
    Ai/OpenAiMarker    Written marking + assignment drafting (active)
    Ai/ClaudeMarker    The same on Anthropic - switch with AI_DRIVER
    Ai/CourseWriter    The curriculum-writing seam
    Ai/OpenAiCourseW.. Schemes, lessons in both locales, questions
    Ai/Tutor.php       The live tutor
    Ai/Null*           The no-key drivers; the platform runs fully on these
    Ai/Schema/         Structured-output shapes
    AssignmentDrafter  A drafted paper, or one built from the question bank
  Jobs/
    MarkSubmission     Objective marking in PHP, written answers to the model
    GenerateCourse     A scheme of work, then a job per topic
    GenerateTopicCon.. One topic: English lesson, Pidgin lesson, classwork
    GenerateQuestions  Stocks the practice bank, once, on demand
  Support/
    Levels             JSS1..SS3, and what is junior or senior
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

## The AI engine — OpenAI

Three unrelated workloads with three different cost profiles:

| Job | How it runs | With no key |
|---|---|---|
| **Objective marking** | `$chosen === $answer_index` in PHP | Unaffected — free, instant, exact |
| **Written marking** | OpenAI, marking scheme in the system message | Flagged for a teacher |
| **Assignment drafting** | OpenAI writes paper + marking scheme | Built from the question bank |
| **Tutor** | OpenAI, answers cached 30 days | The on-device rule tutor answers |

```
AI_DRIVER=openai
AI_MODEL=gpt-4o-mini        # marking and drafting
AI_TUTOR_MODEL=gpt-4o       # the tutor talks to students directly
AI_CONFIDENCE_FLOOR=80
OPENAI_API_KEY=sk-...
```

A **Claude driver is also implemented** and switchable without touching code:
set `AI_DRIVER=claude` and fill `ANTHROPIC_API_KEY` (both sit commented in
`.env`).

**Every failure path returns `null`, never a guess.** A refusal, a timeout, a
malformed reply — all of them mean "a human marks this". `AI_CONFIDENCE_FLOOR`
is the line below which an answer is flagged and sorted to the top of the
teacher's queue: under it, the model is asking for help, not deciding.

Swapping provider means one class implementing `App\Services\Ai\Marker` and one
config line. No screen and no job touches a vendor SDK — which is exactly why
moving between OpenAI and Claude is an `.env` edit.


---

## Levels, and why they are a table

A subject on its own is not something a student can be given. Further Maths
does not exist at JSS2 and Basic Science does not exist at SS3, so the unit
everything is built on is **a subject at a level** — the `subject_levels` table.

| | |
|---|---|
| Declared | An admin says "Basic Science: JSS1–JSS3". The rows exist immediately. |
| Draft | The offering exists and holds no content. Nobody is shown it. |
| Generating | A course is being written into it right now. |
| Published | It has topics, so students at that level can take it. |

Declaring the offering **before** any content exists is what gives the
generator a target to fill and the sign-up screen something honest to show. A
student is only ever offered subjects published at their own year, and
`/api/bootstrap` sends only that year's topics, lessons and questions.

The shipped scheme of work has no level in it — it is one twelve-week scheme of
senior material — so it is written out across **SS1, SS2 and SS3**, which share
a scheme. The junior years are left deliberately empty: seeding SS-level topics
into JSS1 would be worse than an empty course. Those nine offerings are the
queue an admin sees waiting on the courses screen.

---

## Teachers are approved, not self-appointed

Anyone can register claiming to be a teacher. Marking other people's children
is not something an account gets to grant itself, so:

1. Register as a teacher → the account is created **pending**
2. **Set up** — school, subjects, and the levels each is taught at
3. An admin approves or turns it down, with a reason the teacher is shown
4. Only then does `/teacher` open

`EnsureApproved` holds everything under `/teacher` except setup and the waiting
page, and `homeRoute()` sends a pending account to an honest holding screen
rather than a dashboard with no classes on it that merely looks broken.

What a teacher teaches is a pivot with the level on it (`teacher_subject`),
because "Maths at SS1 and SS2" is the real unit. It replaces `users.subject_slug`,
which held one string and which nothing ever wrote to.

---

## Admin

| Screen | What it does |
|---|---|
| **Overview** | Counts, plus the two things that actually need a person: teachers waiting, and levels declared but empty |
| **Courses** | Add, edit and remove courses; set which levels each runs at; ask for one to be written |
| **Lessons** | The curriculum browser, per subject and level |
| **Teachers** | Approve, turn down, and assign subjects and levels. Pending sorts to the top |
| **Students** | Every student, paginated, filterable by level and school |

Removing a course is refused while students are enrolled or a class is running
on it — the cascade would take its topics, lessons and questions with it. And
unticking a level that already holds content keeps it: students have downloaded
that year, so dropping it is a decision rather than a cleared checkbox.

### Having a course written

The admin explains what they want in a brief, and a queued job writes it:

```
brief -> scheme of work (1 call)
      -> topics
      -> per topic: English lesson, Pidgin lesson, classwork (3 calls each)
```

Split into a job per topic on purpose. A managed queue worker gets a fixed
number of seconds and one course is roughly seventy calls; as one job it would
be killed halfway every time. As a scheme plus N children, each piece finishes
inside the limit, progress is visible as topics appear, and a failure costs one
topic rather than the whole course.

**Both locales, always.** `Topic::lessonFor()` falls back to English when a
locale is missing — silently. A course generated with the Pidgin half skipped
would look complete and quietly serve English to every student who asked for
Pidgin, which is the kind of failure nobody reports because nothing appears
broken.

Every run is a row in `course_generations` with its brief, its counts and, if
it failed, a readable reason. A subject that silently never filled up is the
worst possible outcome, so it is not one of the options.

---

## The practice bank

Past questions used to be a 42-item array compiled into `js/data/questions.js`,
and the `questions` table was invisible to the student app. Seven of the twelve
subjects had none at all.

Now `/api/bootstrap` ships the bank for the student's subjects at their level,
and the bundled array is only a cold-start sample for a device that has never
synced.

When a subject is thin, generation is queued and the questions arrive on the
next sync. **Not while the student waits** — a live call per practice set would
cost money on every attempt, take half a minute, and break the promise printed
on that screen: that every question is already on the phone and needs no data.


### Stocking it in bulk

On-demand fills a subject to ten questions a topic when a student opens it
thin. `questions:generate` is the other end — every published offering taken up
to a full bank before anyone asks.

```bash
php artisan questions:generate --dry-run     # always look first
php artisan questions:generate
php artisan questions:generate --subject=maths --level=SS2
php artisan questions:generate --target=200
```

It prints the size of the job before it queues anything:

```
864 jobs, about 18,144 questions, roughly 1,728 model calls.
```

The target is per subject-level and divided across that offering's topics, so
500 across a 24-topic course asks for about 21 each. Each job asks in runs of
at most twelve, because a single call for forty questions comes back truncated
or repetitive and a batch that fails loses everything in it.

Running it twice costs the second run nothing: questions are keyed by a hash of
their prompt and every job re-counts before it writes. Offerings with no scheme
of work are named and skipped — there is nothing to write questions *about*
until the course exists.

**`AI_WRITER_MODEL` is what this spends.** It defaults to the better model
because it also writes lessons, and 1,700 calls on a premium model is real
money. For a bulk question run specifically, `gpt-4o-mini` is the sensible
setting — objective questions with a marking key are not the place the
expensive model earns its keep.

The admin courses screen has the same thing per offering, behind the 📝 button.

Everything generated is stored `source: ai` with `reviewed_at` null, and the
admin overview counts them. An AI-written answer key nobody has checked marks a
correct student wrong and **nothing on the screen looks broken** — provenance is
what lets a bad batch be found and pulled.

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

Served at `/learn/*`, and a real client rather than a demo with bundled data.

The twelve screens used to be static `.html` files under `public/app`, which is
why their file extension showed up in the URL. They are Laravel routes and one
Blade view now — they were identical 27-line shells apart from a title and a
script tag. `public/app` still holds the CSS, the JS modules and the icons,
served straight off disk.

The pages are gated by the Laravel session, and each one hands the device an
API token for `/api`. There is no second login, and no account living in local
storage pretending to be one: `auth.js` used to keep accounts, passwords and
OTP codes on the device, with three demo logins compiled into the bundle.

Offline still works. The service worker — served from the site root now, so its
scope covers `/learn` and not just `/app` — caches each screen on first visit,
and the account is mirrored to the device. What is gone is the ability to
*become* someone with no network, which never worked against anything real.

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

POST  /api/practice           -> stock this subject's question bank
POST  /api/tutor              -> a live answer, or a graceful decline
```

### The loop, verified end to end

Teacher publishes, student sees it in **Classwork**, answers objective and
written questions, submits (a resend is deduped by `client_uuid`), objective
answers are marked instantly in PHP and written ones sent to the model or flagged,
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

**86 tests, 269 assertions, all passing.** They run against the real seeded
MySQL database inside a transaction, so they exercise the actual curriculum and
the actual marking queue, then roll back.

| Suite | What it proves |
|---|---|
| `PlatformSmokeTest` | Every screen renders per role; role gates redirect; login succeeds and fails correctly |
| `AuditTest` | Adversarial probes: cross-teacher access, cross-class submission, login throttling, queue ordering, marking exactness |
| `StudentApiTest` | The classwork loop, the answer key never leaking, marks hidden until approval, onboarding, tutor degradation, the past-question bank reaching the device |
| `ScaleTest` | Query budgets and timings at 270 students, mark distribution, email rendering, AI binding |
| `OpenAiEngineTest` | The OpenAI request shape, reply parsing, mark clamping, and that every failure path returns `null` — all against a faked transport, so it needs no key |

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
| Queue worker | Not running by default | **Required**, or marking silently never happens. Two queues, `marking` and `mail` — see [DEPLOY.md](DEPLOY.md) |
| Scheduler | 3 weekly-report jobs registered | `* * * * * php artisan schedule:run`, or nothing at all on Laravel Cloud, which runs it for you |
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

- **Every AI path has now run for real.** `php artisan ai:check` passes 9/9:
  the tutor answers in English and in Pidgin, a model answer marks 4/4 at
  confidence 100, a vague one marks 0/4 at confidence 20 and is correctly
  flagged, a blank answer never leaves the machine, and the writer produces a
  scheme, a Pidgin lesson and questions with usable answer keys. Run it again
  after any change to the key, the model or the prompts.

  The test suite pins the AI keys empty in `phpunit.xml` so it stays hermetic —
  without that, every run bills you and takes ten minutes. Tests that need a
  configured provider build one with a fake key and `Http::fake()`.

- **Marking agreement has not been measured at scale.** `ai:check` proves the
  floor separates a good answer from a vague one, on one question. That is not
  the same as agreeing with a teacher across a class. Take 100 answers a
  teacher has already marked, run them through, and compare — a marker that is
  confidently wrong twice a class is worse than no marker, and only measurement
  tells you which one you have.

- **The three senior years share one scheme of work.** By design for now — the
  exported curriculum has no level in it. Real per-year content is what the
  course generator is for. The nine junior offerings are empty and are the
  queue it exists to work through.

- **AI-written questions are unreviewed.** They are flagged `source: ai` with
  `reviewed_at` null and counted on the admin overview, but nothing yet lets an
  admin work through them and sign them off. That screen is the next thing this
  needs.

- **The Livewire teacher and parent screens do not match the designed pages.**
  The designs are in `../Classmate9ja/teacher` and `../Classmate9ja/parent`; the
  Blade views use the same CSS but are a re-interpretation rather than a port.
  Making them match is a rebuild of five teacher and two parent views.

- **`../Classmate9ja/platform/` is a stale duplicate** of this application from
  before the folder was renamed. Safe to delete once you are happy nothing of
  yours is in it.
