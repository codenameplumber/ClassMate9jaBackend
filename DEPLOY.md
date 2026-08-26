# Deploying ClassMate 9ja on Laravel Cloud

Everything here is about the part of the platform that runs when nobody is
looking: marking scripts and sending parent reports. The web side needs no
special handling — push the repo, set the environment variables, deploy.

---

## What actually runs in the background

Two things, and they have opposite shapes. That is the whole reason they are
kept apart.

| Queue | What is on it | Shape |
|---|---|---|
| `marking` | `MarkSubmission` — one job per submitted script | A student is waiting. Must start within seconds. Bursty: a class of 40 submits inside two minutes, then nothing for a day. |
| `mail` | Weekly parent reports, and the parent's own "email me this" button | Nobody is waiting. Deliberately slow — released at `MAIL_THROTTLE_PER_MINUTE` so the mailer does not reject the batch. |
| `content` | `GenerateCourse`, `GenerateTopicContent`, `GenerateQuestions` | Nobody is waiting, and each job is minutes of model calls. One course is a scheme call plus roughly seventy more. Must never sit in front of a script. |

Plus the scheduler, which fires `reports:weekly` three times a week
(`routes/console.php`).

If both sat on one queue, a Sunday-evening report blast would sit in front of
every script submitted during it. At 5 emails a minute and 85 linked parents
that is a seventeen-minute wait for a student who just pressed submit. So
`MarkSubmission` declares `onQueue('marking')` and everything mail-shaped
declares `onQueue('mail')`.

Two tests fail if anyone merges them again:
`ScaleTest::test_marking_and_mail_do_not_share_a_queue` and
`ScaleTest::test_content_generation_does_not_share_a_queue_with_marking`.

---

## Option A — managed queues (recommended)

Laravel Cloud provisions the queue, runs the workers, scales them with the
backlog, drops them to zero when idle, and gives you a failed-jobs dashboard
with retry. No `queue:work` process to babysit and no `queue:restart` after a
deploy.

**One managed queue handles exactly one queue name**, so this app wants two.
Check your plan before counting on that:

| Plan | Managed queues per environment |
|---|---|
| Starter | 1 |
| Growth | 10 |
| Business / Enterprise | Unlimited |

On **Starter you only get one**, which is not enough to keep marking away from
mail — use Option B instead, or put the single managed queue on `marking` and
leave mail on an app-cluster process.

### Before the first deploy

Managed queues need the AWS SDK present, or the deploy fails with an explicit
error:

```sh
composer require aws/aws-sdk-php
```

Laravel 13.19+ is also required. This app is on 13.26.1, so that is already
satisfied.

### Creating them

In the environment canvas: **Add compute → Managed queue**, twice.

| | Queue 1 | Queue 2 | Queue 3 |
|---|---|---|---|
| **Name** | `marking` | `mail` | `content` |
| **Type** | Standard | Standard | Standard |
| **Compute class** | Flex | Flex | **Pro** |
| **Memory** | 512 MiB | 256 MiB | 512 MiB |
| **Workers min/max** | 0 / 5 | 0 / 1 | 0 / 2 |

The names must match the strings in the code exactly — `marking`, `mail` and
`content`.

Why these numbers:

- **`mail` max 1 worker.** The throttle assumes serial delivery. Two workers
  double the real send rate and put you back into the provider's rate limit.
- **`marking` max 5.** Marking is bursty and each job is independent, so
  parallelism helps. Five concurrent workers means at most five OpenAI calls in
  flight — comfortable against the default rate limits, and it can be raised
  once you have watched a real submission burst.
- **Flex for marking and mail.** Both idle almost all day. Flex bills only
  while jobs run and wakes in under a second, which is fine for both.
- **Pro for `content`, or nothing on it will ever finish.** A Flex worker is
  killed at 90 seconds. `GenerateCourse` makes one scheme call and then queues
  a child per topic; `GenerateTopicContent` makes three calls (English lesson,
  Pidgin lesson, classwork) and `AI_WRITER_TIMEOUT` defaults to 90 on its own.
  Pro has no fixed runtime limit. On Flex, set `AI_WRITER_TIMEOUT=25` and
  accept that long lessons will time out and be retried.
- **`content` max 2 workers.** Course generation is many small calls in
  sequence; two in flight is plenty and keeps well clear of rate limits while
  marking has the headroom it needs.

### The 90-second Flex limit

A Flex worker gets 90 seconds to finish a job. `MarkSubmission` calls the model
once per written answer, and the busiest assignment in the seed has two — so
the worst case is two API calls back to back. `AI_TIMEOUT=35` is set in `.env`
to keep that under the ceiling with room to spare.

If you later write assignments with more written answers than that, either drop
`AI_TIMEOUT` further or move the `marking` queue to the **Pro** class, which has
no fixed runtime limit. Pro keeps one worker always on and bills continuously.

If a marking job does get killed, nothing is lost: `MarkSubmission::failed()`
puts the script back into the teacher's review queue rather than leaving it
stuck showing "marking".

### The 15-minute delay ceiling

Standard managed queues refuse a per-job delay longer than 15 minutes. The
weekly blast staggers each email by `60 / MAIL_THROTTLE_PER_MINUTE` seconds, so
at a low throttle a big batch would ask for a delay the queue will not accept.

`reports:weekly` caps every delay at 14 minutes and warns when a batch is large
enough to hit it. The number to fix it with is the throttle:

| `MAIL_THROTTLE_PER_MINUTE` | Reports that stagger cleanly |
|---|---|
| 5 (Mailtrap sandbox) | 70 |
| 30 | 420 |
| 100 (Resend) | 1,400 |

With 85 linked parents, **5/minute is not enough** — that is a Mailtrap sandbox
constraint, not a production one. Set it to 100 when you move to Resend.

`ScaleTest::test_no_queued_report_is_delayed_past_the_platform_ceiling` reads
the real `jobs` table and fails if any delay crosses 15 minutes.

### One thing to watch after the first deploy

Creating a managed queue sets **`QUEUE_CONNECTION=cloud` for the whole
environment**. Any job dispatched without an explicit connection now goes to a
managed queue instead of the `database` driver this app uses locally. That is
what you want here — but it means the switch happens the moment the first queue
exists, not when you get round to updating config.

---

## Option B — a worker cluster on the database driver

Right choice on Starter, or if you would rather keep the `database` queue driver
and manage the worker yourself.

**Add compute → Worker cluster**, then **Background processes → New background
process**:

```
php artisan queue:work --queue=marking,mail,content --tries=2 --max-time=3600 --sleep=3
```

The queue order is the point. `--queue=marking,mail,content` drains `marking`
completely before touching `mail`, and `content` only when both are empty, so a
student's script always jumps both the report blast and a course being written.
It gives you the priority separation without needing three managed queues.

On this option there is no 90-second ceiling, so course generation works
without the Pro upgrade — `--max-time=3600` is the process lifetime, not a
per-job limit.

Set **processes: 1**. A second process would break the mail throttle for the
same reason a second managed worker would.

Note that processes are spawned **per replica** — a cluster autoscaled to 3
replicas with "2 processes" configured runs 6 workers, not 2. Keep the replica
maximum at 1 for this cluster, or the throttle stops meaning anything.

Leave `QUEUE_CONNECTION=database`. No `queue:restart` is needed after a deploy;
Laravel Cloud handles it.

The tradeoff versus Option A: no failed-jobs dashboard, no scale-to-zero, and
the worker bills continuously.

---

## The scheduler

Nothing to configure. Laravel Cloud runs the Laravel scheduler for you — no
cron entry, no `schedule:work` process — and for Laravel applications scheduled
tasks keep firing even while the environment is asleep.

The three entries in `routes/console.php` are picked up as they are:

```php
Schedule::command('reports:weekly')->weeklyOn(0, '18:00');
Schedule::command('reports:weekly --day=friday')->fridays()->at('18:00');
Schedule::command('reports:weekly --day=monday')->mondays()->at('07:00');
```

Times are UTC unless the app timezone says otherwise. `18:00` UTC is 19:00 in
Lagos — check that is the hour you actually want a parent's phone to buzz.

---

## Environment variables

Set these in the environment's variables panel. Laravel Cloud injects the
database and cache credentials itself; do not paste those in by hand.

```dotenv
APP_ENV=production
APP_DEBUG=false

# Option A leaves this alone — deploying a managed queue sets it to `cloud`.
# Option B needs it explicitly.
QUEUE_CONNECTION=database

CACHE_STORE=database          # or `redis` if you provision a KV store
SESSION_DRIVER=database

AI_DRIVER=openai
AI_MODEL=gpt-4o-mini
AI_TUTOR_MODEL=gpt-4o
AI_WRITER_MODEL=gpt-4o          # writing curriculum, not marking it
AI_CONFIDENCE_FLOOR=80
AI_TIMEOUT=35                   # marking: two calls inside a 90s Flex worker
AI_WRITER_TIMEOUT=90            # one lesson; needs the Pro `content` queue
AI_QUESTIONS_PER_TOPIC=10
OPENAI_API_KEY=sk-...

MAIL_MAILER=resend
RESEND_API_KEY=re_...
MAIL_FROM_ADDRESS=reports@yourdomain.ng
MAIL_FROM_NAME="ClassMate 9ja"
MAIL_THROTTLE_PER_MINUTE=100
```

`OPENAI_API_KEY` is the only one that changes behaviour by its absence rather
than its value: with it empty the platform still runs completely, objective
marking included. Only written answers stop being machine-marked, and those go
to the teacher instead. That is deliberate — see `AiServiceProvider`.

---

## Deploy commands

```sh
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Do not cache config locally — `config:cache` freezes `.env` into a file, and
this app reads `AI_DRIVER` and the mail throttle at runtime.

---

## Verifying it after the first deploy

```sh
# 1. A script gets marked. Submit one from a student login, then:
php artisan tinker --execute="dump(App\Models\Submission::latest()->first()->status);"
# 'marked' means the marking queue is alive. 'marking' means nothing picked it up.

# 2. Every AI path answers. This is the one that has never run without a key.
php artisan ai:check

# 2b. Stock the practice bank. Look before you leap - it prints the size first.
php artisan questions:generate --dry-run

# 3. Mail leaves the building.
php artisan mail:check --to=you@yourdomain.ng

# 4. The weekly blast queues without hitting the ceiling.
php artisan reports:weekly --day=sunday -v
# A warning here means MAIL_THROTTLE_PER_MINUTE is too low for your parent count.
```

On Option A, watch all of it land under **Monitoring → Queues**. On Option B,
the worker's output goes to the environment's **Logs** tab alongside the web
logs.
