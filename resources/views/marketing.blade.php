<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>ClassMate 9ja &mdash; Your AI Teacher, Offline</title>
<meta name="description" content="An offline AI tutor, auto-marker and WAEC/JAMB practice engine for Nigerian secondary school students. Works with zero data.">
<meta name="theme-color" content="#008751">
<link rel="manifest" href="/app/manifest.json">
<link rel="stylesheet" href="/app/css/tokens.css">
<link rel="stylesheet" href="/app/css/base.css">
<link rel="stylesheet" href="/app/css/components.css">
<link rel="stylesheet" href="/app/css/layout.css">
<link rel="stylesheet" href="/app/css/pages/home.css">
<script>
  try {
    var t = JSON.parse(localStorage.getItem('cm9ja:theme') || 'null')
      || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
</script>
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<!-- ======================= HEADER ======================= -->
<header class="site-header">
  <div class="container inner">
    <a class="brand-mark" href="{{ route('home') }}">
      <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
    </a>

    <nav class="site-nav hide-mobile" aria-label="Main">
      <a href="#how">How it works</a>
      <a href="#features">Features</a>
      <a href="#offline">Offline</a>
      <a href="#who">For schools</a>
    </nav>

    <div class="row gap-2">
      <button class="btn btn-ghost btn-icon" id="themeBtn" aria-label="Switch theme">
        &#9789;
      </button>
      @auth
        <a class="btn btn-ghost hide-mobile" href="{{ auth()->user()->homeRoute() }}">My dashboard</a>
      @else
        <a class="btn btn-ghost hide-mobile" href="{{ route('login') }}">Log in</a>
      @endauth
      <a class="btn btn-primary hide-mobile" href="{{ route('register') }}">Start free</a>
      <button class="btn btn-secondary btn-icon hide-desktop" id="menuBtn" aria-label="Open menu">
        &#9776;
      </button>
    </div>
  </div>
</header>

<main id="main">

<!-- ======================= HERO ======================= -->
<section class="hero">
  <div class="container inner">
    <div>
      <span class="eyebrow">&#128246; Works at 0% network</span>

      <h1>Your <span class="hl">AI teacher</span>, inside your phone. No data needed.</h1>

      <p class="lede">
        ClassMate 9ja teaches the topic, marks your work in seconds, and drills you
        with 5,000+ WAEC and JAMB past questions — all offline. Whether NEPA take
        light or your data don finish, class still dey go on.
      </p>

      <div class="cta-row">
        <a class="btn btn-primary btn-lg" href="{{ route('register') }}">
          Start learning free &rarr;
        </a>
        <a class="btn btn-secondary btn-lg" href="{{ route('login') }}">
          &#9654; See the demo
        </a>
      </div>

      <div class="proof">
        <div>
          <div class="n">5,000+</div>
          <div class="l">Past questions, 2010&ndash;2025</div>
        </div>
        <div>
          <div class="n">9</div>
          <div class="l">Core subjects, JSS1&ndash;SS3</div>
        </div>
        <div>
          <div class="n">0&nbsp;MB</div>
          <div class="l">Data needed to study</div>
        </div>
      </div>
    </div>

    <!-- Phone mockup: built from the same components the real app uses -->
    <div class="hero-visual">
      <div class="phone">
        <div class="notch"></div>
        <div class="screen">
          <div class="p-top">
            <div>
              <div class="t-2xs subtle">Good evening</div>
              <div class="fw-bold t-sm">Chidera 👋</div>
            </div>
            <div class="row gap-1">
              <span class="stat-chip streak">🔥 12</span>
              <span class="stat-chip points">⚡ 2,340</span>
            </div>
          </div>

          <div class="p-body">
            <div class="card card-brand card-pad-sm">
              <div class="t-2xs" style="opacity:.8">CONTINUE WHERE YOU STOP</div>
              <div class="fw-bold" style="margin:4px 0 8px">SS2 Physics · Week 4</div>
              <div class="t-2xs" style="opacity:.85;margin-bottom:6px">Electric Fields &amp; Potential</div>
              <div class="bar bar-sm" style="background:rgba(255,255,255,.25)">
                <i style="width:68%;background:#fff"></i>
              </div>
            </div>

            <div class="grid grid-2 keep-2" style="gap:var(--s-2)">
              <div class="card card-pad-sm">
                <div class="itile itile-sm itile-amber">✍️</div>
                <div class="t-2xs fw-bold" style="margin-top:6px">Classwork</div>
                <div class="t-2xs subtle">Marked in 4s</div>
              </div>
              <div class="card card-pad-sm">
                <div class="itile itile-sm itile-sky">📝</div>
                <div class="t-2xs fw-bold" style="margin-top:6px">Mock JAMB</div>
                <div class="t-2xs subtle">40 Qs · timed</div>
              </div>
            </div>

            <div class="card card-pad-sm">
              <div class="row between" style="margin-bottom:6px">
                <span class="t-2xs fw-bold">Weakest topic</span>
                <span class="chip chip-danger t-2xs">Needs work</span>
              </div>
              <div class="t-2xs subtle" style="margin-bottom:6px">Chemistry · Mole Concept</div>
              <div class="bar bar-sm"><i class="danger" style="width:34%"></i></div>
            </div>
          </div>

          <div class="p-tabs">
            <span class="on">🏠<br>Home</span>
            <span>📚<br>Learn</span>
            <span>📝<br>Practice</span>
            <span>📊<br>Progress</span>
          </div>
        </div>
      </div>

      <div class="float-card f1">
        <span class="itile itile-sm">🤖</span>
        <div>
          <div class="fw-bold t-2xs">AI marked 30 scripts</div>
          <div class="subtle t-2xs">in 4 minutes</div>
        </div>
      </div>

      <div class="float-card f2">
        <span class="itile itile-sm itile-amber">🔥</span>
        <div>
          <div class="fw-bold t-2xs">12-day streak</div>
          <div class="subtle t-2xs">Keep am up!</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ======================= PROBLEM ======================= -->
<section class="container" style="padding-bottom:var(--s-4)">
  <div class="problem">
    <div class="grid" style="grid-template-columns:1fr 1.15fr;gap:var(--s-10);align-items:center">
      <div>
        <span class="chip chip-amber">The problem</span>
        <h2 style="margin-block:var(--s-4) var(--s-3)">
          Mass failure is not because our students are not sharp.
        </h2>
        <p style="color:var(--ink-300);font-size:var(--text-sm)">
          It is because one teacher cannot mark 60 scripts, explain a topic
          one-on-one, and still be available at 9pm when the student finally
          opens the book. And the apps that could help all need data.
        </p>
      </div>

      <div class="row wrap gap-6">
        <div class="stat grow">
          <div class="n">1.5M+</div>
          <div class="l">Students write WAEC &amp; JAMB every year</div>
        </div>
        <div class="stat grow">
          <div class="n">1 : 60</div>
          <div class="l">Typical teacher-to-student ratio in class</div>
        </div>
        <div class="stat grow">
          <div class="n">₦0</div>
          <div class="l">What a student should need to spend on data to learn</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ======================= FEATURES ======================= -->
<section class="section container" id="features">
  <div class="sec-head">
    <span class="eyebrow">What the AI actually does</span>
    <h2>Four jobs, one app</h2>
    <p>Every feature maps to a real gap in a Nigerian classroom — not a gimmick.</p>
  </div>

  <div class="grid grid-4">
    <article class="feature">
      <span class="num">01</span>
      <div class="itile itile-lg">👩🏾‍🏫</div>
      <h3>Personalised teaching</h3>
      <p>
        Pick your class and subject, and the AI builds a 12-week scheme of work,
        then explains each topic in clear English or Pidgin — using danfo, market
        and NEPA examples that actually land.
      </p>
      <div class="solves">Solves: <b>no teacher to explain</b></div>
    </article>

    <article class="feature">
      <span class="num">02</span>
      <div class="itile itile-lg itile-amber">✍️</div>
      <h3>Instant marking</h3>
      <p>
        Classwork is set and marked on the spot. At the end of the scheme the AI
        sets a 40-question exam and hands back a WAEC-style grade with feedback on
        exactly where you lost marks.
      </p>
      <div class="solves">Solves: <b>marking backlog</b></div>
    </article>

    <article class="feature">
      <span class="num">03</span>
      <div class="itile itile-lg itile-sky">📝</div>
      <h3>WAEC &amp; JAMB drills</h3>
      <p>
        5,000+ past questions from 2010 to 2025, timed exactly like the real thing.
        Instant scoring, and a diagnosis of the topics quietly costing you marks.
      </p>
      <div class="solves">Solves: <b>no past questions, no timing practice</b></div>
    </article>

    <article class="feature">
      <span class="num">04</span>
      <div class="itile itile-lg itile-violet">📊</div>
      <h3>Tracking that parents trust</h3>
      <p>
        Study time, streaks and weak subjects roll up into a weekly report for
        parents, and a class dashboard that lets a teacher mark 30 scripts in
        five minutes.
      </p>
      <div class="solves">Solves: <b>nobody knows how the child is doing</b></div>
    </article>
  </div>
</section>

<!-- ======================= HOW IT WORKS ======================= -->
<section class="section container" id="how">
  <div class="sec-head">
    <span class="eyebrow">How it works</span>
    <h2>From install to first marked script in four steps</h2>
  </div>

  <div class="grid grid-4 steps-grid gap-8">
    <div class="step">
      <h4>Download once</h4>
      <p>Install the app and pull one content pack while you have network — at school, at a friend's place, anywhere.</p>
    </div>
    <div class="step">
      <h4>Pick class &amp; subjects</h4>
      <p>JSS1 to SS3, up to nine core subjects, and your target: WAEC, JAMB, or both.</p>
    </div>
    <div class="step">
      <h4>Learn and practise offline</h4>
      <p>Scheme of work, topic explanations, classwork, past questions and mocks — all with the network off.</p>
    </div>
    <div class="step">
      <h4>Sync when you see network</h4>
      <p>Scores, streaks and reports upload in the background so teachers and parents stay in the loop.</p>
    </div>
  </div>
</section>

<!-- ======================= OFFLINE ======================= -->
<section class="container section" id="offline">
  <div class="offline-band">
    <div class="stack gap-5">
      <span class="chip" style="background:rgba(255,255,255,.2);color:#fff;align-self:flex-start">
        Offline first, not offline "later"
      </span>
      <h2>Put your phone on flight mode. The app still works.</h2>
      <p>
        Everything a student needs is stored on the device: the scheme of work,
        the explanations, the question bank, the marking rules. Network only
        matters when it is time to sync — and even that waits patiently.
      </p>
      <div class="stack gap-3" style="margin-top:var(--s-2)">
        <div class="tick"><span class="mark">&#10003;</span> No data to open a lesson, take a test, or get marked</div>
        <div class="tick"><span class="mark">&#10003;</span> Under 40 MB for a full subject pack</div>
        <div class="tick"><span class="mark">&#10003;</span> Works on Android 8 and up, 2 GB RAM phones included</div>
        <div class="tick"><span class="mark">&#10003;</span> Installs to the home screen like a normal app</div>
      </div>
    </div>

    <div class="demo-box">
      <div class="row between" style="margin-bottom:var(--s-4)">
        <span class="t-sm fw-bold">Storage on this phone</span>
        <span class="chip" style="background:rgba(255,255,255,.2);color:#fff">128 MB used</span>
      </div>

      <div class="stack gap-4">
        <div>
          <div class="row between t-sm" style="margin-bottom:6px">
            <span>Mathematics · SS2</span><span class="fw-bold">38 MB</span>
          </div>
          <div class="bar bar-sm" style="background:rgba(255,255,255,.25)"><i style="width:100%;background:#fff"></i></div>
        </div>
        <div>
          <div class="row between t-sm" style="margin-bottom:6px">
            <span>English Language · SS2</span><span class="fw-bold">31 MB</span>
          </div>
          <div class="bar bar-sm" style="background:rgba(255,255,255,.25)"><i style="width:100%;background:#fff"></i></div>
        </div>
        <div>
          <div class="row between t-sm" style="margin-bottom:6px">
            <span>JAMB past questions bank</span><span class="fw-bold">Downloading · 74%</span>
          </div>
          <div class="bar bar-sm" style="background:rgba(255,255,255,.25)">
            <i style="width:74%;background:var(--amber-300)"></i>
          </div>
        </div>
      </div>

      <div class="row gap-2" style="margin-top:var(--s-5);font-size:var(--text-xs);opacity:.9">
        &#128246; Network off — nothing above is affected
      </div>
    </div>
  </div>
</section>

<!-- ======================= AUDIENCE ======================= -->
<section class="section container" id="who">
  <div class="sec-head">
    <span class="eyebrow">Built for three people at once</span>
    <h2>One app, three doors</h2>
    <p>Each role sees only what it needs — and all three see the same truth about the student.</p>
  </div>

  <div class="grid grid-3">
    <div class="audience">
      <div class="top">
        <div class="itile itile-lg">🎓</div>
        <h3 style="margin-block:var(--s-4) var(--s-2)">Students</h3>
        <p class="muted t-sm">JSS1 to SS3, plus JAMB and WAEC candidates.</p>
      </div>
      <div class="body">
        <div class="line"><span class="mark">&#10003;</span> 12-week scheme of work per subject</div>
        <div class="line"><span class="mark">&#10003;</span> Ask the AI tutor anything, any time</div>
        <div class="line"><span class="mark">&#10003;</span> Timed mock exams with instant grading</div>
        <div class="line"><span class="mark">&#10003;</span> Points, levels, badges and streaks</div>
        <a class="btn btn-soft btn-block mt-auto" href="{{ route('register') }}?role=student">Start as a student</a>
      </div>
    </div>

    <div class="audience">
      <div class="top">
        <div class="itile itile-lg itile-sky">🧑🏾‍🏫</div>
        <h3 style="margin-block:var(--s-4) var(--s-2)">Teachers</h3>
        <p class="muted t-sm">For the teacher carrying 60 students per class.</p>
      </div>
      <div class="body">
        <div class="line"><span class="mark">&#10003;</span> AI drafts your scheme and assignments</div>
        <div class="line"><span class="mark">&#10003;</span> Batch marking: approve or override in one tap</div>
        <div class="line"><span class="mark">&#10003;</span> See which topic the whole class failed</div>
        <div class="line"><span class="mark">&#10003;</span> Class reports ready for the head teacher</div>
        <a class="btn btn-soft btn-block mt-auto" href="{{ route('register') }}?role=teacher">Start as a teacher</a>
      </div>
    </div>

    <div class="audience">
      <div class="top">
        <div class="itile itile-lg itile-amber">👨🏾‍👩🏾‍👦🏾</div>
        <h3 style="margin-block:var(--s-4) var(--s-2)">Parents</h3>
        <p class="muted t-sm">For the parent paying the fees and hoping for the best.</p>
      </div>
      <div class="body">
        <div class="line"><span class="mark">&#10003;</span> Weekly report by SMS or in-app</div>
        <div class="line"><span class="mark">&#10003;</span> Real study time, not guesswork</div>
        <div class="line"><span class="mark">&#10003;</span> Know the weakest subject before the exam</div>
        <div class="line"><span class="mark">&#10003;</span> Follow more than one child</div>
        <a class="btn btn-soft btn-block mt-auto" href="{{ route('register') }}?role=parent">Start as a parent</a>
      </div>
    </div>
  </div>
</section>

<!-- ======================= CTA ======================= -->
<section class="container" style="padding-bottom:var(--s-8)">
  <div class="cta-band">
    <span class="eyebrow">Free for students, forever</span>
    <h2 style="margin-top:var(--s-4)">The next WAEC is closer than you think.</h2>
    <p>
      Create an account in under a minute, download your subjects once,
      and study from anywhere — with or without network.
    </p>
    <div class="row center gap-3 wrap">
      <a class="btn btn-primary btn-lg" href="{{ route('register') }}">Create free account</a>
      <a class="btn btn-secondary btn-lg" href="{{ route('login') }}">Log in to the demo</a>
    </div>
  </div>
</section>

</main>

<!-- ======================= FOOTER ======================= -->
<footer class="site-footer">
  <div class="container">
    <div class="foot-grid">
      <div>
        <a class="brand-mark" href="{{ route('home') }}">
          <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
        </a>
        <p style="margin-top:var(--s-4);font-size:var(--text-sm);max-width:34ch;color:var(--ink-400)">
          An offline AI teacher and class workspace built for Nigerian secondary
          schools, WAEC and JAMB candidates.
        </p>
      </div>

      <div>
        <h5>Product</h5>
        <ul class="stack gap-3">
          <li><a href="#features">Features</a></li>
          <li><a href="#how">How it works</a></li>
          <li><a href="#offline">Offline mode</a></li>
          <li><a href="{{ route('learn.screen', 'downloads') }}">Content packs</a></li>
        </ul>
      </div>

      <div>
        <h5>Roles</h5>
        <ul class="stack gap-3">
          <li><a href="{{ route('register') }}?role=student">For students</a></li>
          <li><a href="{{ route('register') }}?role=teacher">For teachers</a></li>
          <li><a href="{{ route('register') }}?role=parent">For parents</a></li>
          <li><a href="{{ route('login') }}">Log in</a></li>
        </ul>
      </div>

      <div>
        <h5>Support</h5>
        <ul class="stack gap-3">
          <li><a href="#how">Getting started</a></li>
          <li><a href="{{ route('password.request') }}">Reset password</a></li>
          <li><a href="#who">For schools</a></li>
          <li><a href="#offline">Data &amp; storage</a></li>
        </ul>
      </div>
    </div>

    <div class="foot-bottom">
      <span>© {{ date('Y') }} ClassMate 9ja. Built for Nigerian classrooms.</span>
      <span>Made with 🇳🇬 for students who no get data.</span>
    </div>
  </div>
</footer>



<script>
/* Theme toggle only. Everything else on this page is static. */
(function () {
  var btn = document.getElementById('themeBtn');
  if (!btn) return;
  function paint() {
    btn.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '\u2600' : '\u263E';
  }
  paint();
  btn.addEventListener('click', function () {
    var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('cm9ja:theme', JSON.stringify(next)); } catch (e) {}
    paint();
  });
  var menu = document.getElementById('menuBtn');
  if (menu) menu.addEventListener('click', function () {
    window.location.href = '{{ route("login") }}';
  });
})();
</script>
</body>
</html>
