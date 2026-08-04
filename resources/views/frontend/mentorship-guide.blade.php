<!DOCTYPE html>
<html lang="en"> 
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>How Mentorship Works — MNCH Platform</title>
<meta name="description" content="A click-by-click guide for mentors and mentees using the MNCH facility mentorship workflow — creating a mentorship, enrolling mentees, sending invitations, and confirming attendance.">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@600;700&family=Source+Sans+3:wght@400;600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">

<style>
  :root {
    --bg:        #f4f8f8;
    --surface:   #ffffff;
    --ink:       #10262b;
    --ink-soft:  #45636a;
    --line:      #d3e2e3;
    --accent:    #0097a7;
    --accent-ink: #005e68;
    --accent-wash: #e3f4f5;
    --mentee:    #7a5cc0;
    --mentee-wash: #ede8f8;
    --good:      #1f8a5f;
    --good-wash: #e3f5ec;
    --warn:      #b3690a;
    --warn-wash: #fbede0;
    --shadow: 0 1px 2px rgba(16,38,43,.06), 0 8px 24px -12px rgba(16,38,43,.18);
  }

  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    background: var(--bg);
    color: var(--ink);
    font-family: 'Source Sans 3', ui-sans-serif, system-ui, -apple-system, sans-serif;
    font-size: 17px;
    line-height: 1.55;
    -webkit-font-smoothing: antialiased;
  }
  h1, h2, h3, .label-font { font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif; }
  code, .mono { font-family: 'IBM Plex Mono', ui-monospace, 'SFMono-Regular', monospace; }

  /* ---------- Back-to-site bar ---------- */
  .backbar {
    background: var(--surface);
    border-bottom: 1px solid var(--line);
  }
  .backbar a {
    max-width: 860px;
    margin: 0 auto;
    padding: .85rem 1.5rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .88rem;
    font-weight: 600;
    color: var(--accent-ink);
    text-decoration: none;
  }
  .backbar a:hover { text-decoration: underline; }

  .wrap {
    max-width: 860px;
    margin: 0 auto;
    padding: 3rem 1.5rem 6rem;
  }

  /* ---------- Masthead ---------- */
  .masthead {
    display: flex;
    flex-direction: column;
    gap: .9rem;
    padding-bottom: 2.4rem;
    border-bottom: 1px solid var(--line);
    margin-bottom: 2.6rem;
  }
  .eyebrow {
    font-family: 'IBM Plex Sans';
    font-weight: 600;
    font-size: .78rem;
    letter-spacing: .11em;
    text-transform: uppercase;
    color: var(--accent-ink);
    display: flex;
    align-items: center;
    gap: .5rem;
  }
  .eyebrow::before {
    content: "";
    width: 22px;
    height: 2px;
    background: var(--accent);
    display: inline-block;
    border-radius: 1px;
  }
  h1 {
    font-size: clamp(1.9rem, 4vw, 2.5rem);
    font-weight: 700;
    line-height: 1.12;
    margin: 0;
    text-wrap: balance;
    color: var(--ink);
  }
  .dek {
    font-size: 1.08rem;
    color: var(--ink-soft);
    max-width: 62ch;
    margin: 0;
  }

  /* ---------- Track legend ---------- */
  .legend {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-top: .4rem;
  }
  .legend-item {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .88rem;
    color: var(--ink-soft);
  }
  .swatch {
    width: 10px; height: 10px; border-radius: 3px; flex: none;
  }
  .swatch.mentor { background: var(--accent); }
  .swatch.mentee { background: var(--mentee); }

  /* ---------- Section headers ---------- */
  .track-head {
    display: flex;
    align-items: baseline;
    gap: .75rem;
    margin: 3.4rem 0 .35rem;
  }
  .track-head h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
  }
  .track-head .tag {
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: .22rem .55rem;
    border-radius: 5px;
  }
  .track-mentor .tag { background: var(--accent-wash); color: var(--accent-ink); }
  .track-mentee .tag { background: var(--mentee-wash); color: var(--mentee); }
  .track-sub {
    color: var(--ink-soft);
    font-size: .98rem;
    max-width: 60ch;
    margin: 0 0 1.6rem;
  }

  /* ---------- Steps ---------- */
  .steps {
    display: flex;
    flex-direction: column;
    border-left: 2px solid var(--line);
    margin-left: 15px;
  }

  .step {
    position: relative;
    padding: 0 0 2.1rem 2.1rem;
  }
  .step:last-child { padding-bottom: .2rem; }
  .step::before {
    content: attr(data-n);
    position: absolute;
    left: -16px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--surface);
    border: 2px solid var(--accent);
    color: var(--accent-ink);
    font-family: 'IBM Plex Sans';
    font-weight: 700;
    font-size: .85rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .track-mentee .step::before { border-color: var(--mentee); color: var(--mentee); }

  .step h3 {
    font-size: 1.12rem;
    font-weight: 600;
    margin: .1rem 0 .35rem;
  }
  .step p { margin: 0 0 .5rem; color: var(--ink); }
  .step p.muted { color: var(--ink-soft); font-size: .95rem; }

  .path {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .3rem;
    font-size: .87rem;
    color: var(--ink-soft);
  }
  .path .crumb {
    background: var(--accent-wash);
    color: var(--accent-ink);
    border-radius: 5px;
    padding: .12rem .5rem;
    font-weight: 600;
    font-size: .82rem;
  }
  .track-mentee .path .crumb { background: var(--mentee-wash); color: var(--mentee); }
  .path .sep { color: var(--line); }

  /* ---------- UI button / menu-item mockups (match real Filament colors) ---------- */
  :root {
    --ui-primary: var(--accent); --ui-primary-ink: #fff;
    --ui-success: #16a34a; --ui-success-ink: #fff;
    --ui-warning: #b45309; --ui-warning-ink: #fff;
    --ui-danger:  #dc2626; --ui-danger-ink: #fff;
    --ui-info:    #0284c7; --ui-info-ink: #fff;
    --ui-gray:    #64748b; --ui-gray-ink: #fff;
  }
  .ui-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-family: 'IBM Plex Sans';
    font-weight: 600;
    font-size: .82rem;
    padding: .18rem .62rem;
    border-radius: 6px;
    line-height: 1.5;
    white-space: nowrap;
    box-shadow: 0 1px 1px rgba(0,0,0,.12);
  }
  .ui-btn.primary { background: var(--ui-primary); color: var(--ui-primary-ink); }
  .ui-btn.success { background: var(--ui-success); color: var(--ui-success-ink); }
  .ui-btn.warning { background: var(--ui-warning); color: var(--ui-warning-ink); }
  .ui-btn.danger  { background: var(--ui-danger);  color: var(--ui-danger-ink); }
  .ui-btn.info    { background: var(--ui-info);    color: var(--ui-info-ink); }
  .ui-btn.gray    { background: var(--surface); color: var(--ink-soft); border: 1px solid var(--line); box-shadow: none; }

  .ui-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6em;
    height: 1.6em;
    border-radius: 6px;
    background: var(--surface);
    border: 1px solid var(--line);
    color: var(--ink-soft);
    font-size: .85rem;
    vertical-align: -.35em;
  }

  .ui-menu {
    font-size: .8rem;
    color: var(--ink-soft);
    display: inline-flex;
    align-items: center;
    gap: .35rem;
  }

  .screen {
    font-family: 'IBM Plex Mono';
    font-size: .78rem;
    color: var(--ink-soft);
    text-transform: none;
    letter-spacing: 0;
  }
  .screen b { color: var(--ink); font-weight: 500; }

  .click-path {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    row-gap: .4rem;
    column-gap: .4rem;
    margin: .5rem 0;
  }
  .click-path .arrow { color: var(--line); font-size: .8rem; }

  /* ---------- Callout: no mentees yet ---------- */
  .callout {
    margin: 3.4rem 0;
    background: var(--surface);
    border: 1px solid var(--line);
    border-left: 4px solid var(--warn);
    border-radius: 10px;
    padding: 1.5rem 1.7rem;
    box-shadow: var(--shadow);
  }
  .callout .kicker {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-family: 'IBM Plex Sans';
    font-weight: 700;
    font-size: .78rem;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: var(--warn);
    margin-bottom: .6rem;
  }
  .callout .dot {
    width: 8px; height: 8px; border-radius: 50%; background: var(--warn);
  }
  .callout h3 {
    margin: 0 0 .4rem;
    font-size: 1.25rem;
  }
  .callout p { margin: 0 0 .8rem; }
  .callout ol {
    margin: 0;
    padding-left: 1.2rem;
  }
  .callout ol li { margin-bottom: .55rem; }
  .callout ol li:last-child { margin-bottom: 0; }
  .callout .path { margin-top: .3rem; }

  /* ---------- Email dispatch box ---------- */
  .mail-box {
    margin-top: 3.4rem;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
  }
  .mail-head {
    background: var(--accent-wash);
    color: var(--accent-ink);
    padding: .9rem 1.4rem;
    font-family: 'IBM Plex Sans';
    font-weight: 700;
    font-size: 1.02rem;
    display: flex;
    align-items: center;
    gap: .6rem;
  }
  .mail-body {
    background: var(--surface);
    padding: 1.4rem;
    display: grid;
    gap: 1rem;
  }
  .mail-route {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: .2rem 1rem;
    align-items: start;
    padding: .9rem 1rem;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: var(--bg);
  }
  .mail-route .who {
    font-family: 'IBM Plex Sans';
    font-weight: 600;
    font-size: .82rem;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--ink-soft);
    grid-column: 1 / -1;
  }
  .mail-route .btn-desc { grid-column: 1 / -1; color: var(--ink); }
  .mail-route .result { grid-column: 1 / -1; color: var(--ink-soft); font-size: .92rem; }

  /* ---------- Tips grid ---------- */
  .tips {
    margin-top: 3.4rem;
  }
  .tips h2 { font-size: 1.3rem; margin-bottom: .3rem; }
  .tip-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
    margin-top: 1.2rem;
  }
  .tip {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 1.1rem 1.2rem;
    box-shadow: var(--shadow);
  }
  .tip .q {
    font-family: 'IBM Plex Sans';
    font-weight: 600;
    font-size: .98rem;
    margin: 0 0 .35rem;
    color: var(--ink);
  }
  .tip .a { margin: 0; font-size: .93rem; color: var(--ink-soft); }
  .tip .a .mono { color: var(--accent-ink); }

  footer.page-footer {
    margin-top: 4rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--line);
    color: var(--ink-soft);
    font-size: .85rem;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .5rem;
  }

  @media (max-width: 560px) {
    .steps { margin-left: 10px; }
    .step { padding-left: 1.6rem; }
    .step::before { left: -13px; width: 26px; height: 26px; font-size: .78rem; }
  }
</style>
</head>
<body>

<div class="backbar">
  <a href="{{ url('/') }}">&larr; Back to MNCH Platform</a>
</div>

<div class="wrap">

  <header class="masthead">
    <div class="eyebrow">MNCH Mentorship Platform &nbsp;&middot;&nbsp; Facility Mentorship Guide</div>
    <h1>Running a mentorship, start to finish</h1>
    <p class="dek">A plain-language walk-through of what a mentor does to set up and run a facility mentorship, and what a mentee sees on the other end — including the two things people get stuck on most: enrolling mentees, and sending the emails that invite them.</p>
    <div class="legend">
      <span class="legend-item"><span class="swatch mentor"></span> Mentor steps</span>
      <span class="legend-item"><span class="swatch mentee"></span> Mentee steps</span>
    </div>
  </header>

  <!-- ============ MENTOR TRACK ============ -->
  <section class="track-mentor">
    <div class="track-head">
      <h2>For the mentor</h2>
      <span class="tag">Setup &rarr; Delivery</span>
    </div>
    <p class="track-sub">Everything below happens inside <b>Mentorships</b> in the admin panel — one training record carries a mentor through the whole cycle.</p>

    <div class="steps">

      <div class="step" data-n="1">
        <h3>Create the mentorship</h3>
        <p class="screen">Screen: <b>Mentorships</b> (left sidebar, under Training Management)</p>
        <p>Click <span class="ui-btn primary">+ New Mentorship</span> at the top right. Choose <b>Live Mentorship</b> or <b>Pilot Run</b>, then fill in <b>County</b> &rarr; <b>Facility</b>, tap a programme card under <b>Mentorship Program</b>, and set the dates and <b>Number of Mentees</b>.</p>
        <p class="muted">Submitting shows "Mentorship Created" and drops you straight onto the Classes/Cohort screen for it.</p>
      </div>

      <div class="step" data-n="2">
        <h3>Add a class (cohort)</h3>
        <p class="screen">Screen: <b>Classes/Cohort</b></p>
        <p>Click <span class="ui-btn success">Create New Class/Cohort</span> at the top. Give it a name and dates within the mentorship's own dates, then submit — you're taken straight to that class's Modules screen.</p>
      </div>

      <div class="step" data-n="3">
        <h3>Add modules to the class</h3>
        <p class="screen">Screen: <b>Class &gt; Modules</b></p>
        <p>Click <span class="ui-btn primary">Add Modules</span> at the top. In the panel that slides over, tick topics under <b>Available Program Modules</b>, leave <b>Auto-populate sessions from program template</b> switched on, and submit.</p>
      </div>

      <div class="step" data-n="4">
        <h3>Enroll your mentees</h3>
        <p class="screen">Screen: <b>Class &gt; Modules</b></p>
        <div class="click-path">
          <span class="ui-btn success">Add Mentees</span>
          <span class="arrow">— or —</span>
          <span class="ui-menu">from <b>Classes/Cohort</b>, click <span class="ui-icon">&#8942;</span> at the end of the class's row &rarr; <span class="ui-btn success" style="box-shadow:none">Manage/Invite Mentees</span></span>
        </div>
        <p>Either route lands you on that class's <b>Mentees</b> screen. Click <span class="ui-btn gray">Register Mentee &#8942;</span>, then pick <span class="ui-btn primary">Add from List</span> (search and tick people already in the system) or <span class="ui-btn success">Add Mentee</span> (type an email — an existing account pre-fills, a new one is created on the spot).</p>
        <p class="muted">Stuck on this exact step? See the callout just below — it's the most common place people pause.</p>
      </div>

      <div class="step" data-n="5">
        <h3>Invite a co-mentor (optional)</h3>
        <p class="screen">Screen: <b>Classes/Cohort</b>, or <b>Mentorships</b> row <span class="ui-icon">&#8942;</span> &rarr; Co-Mentors</p>
        <p>Click <span class="ui-btn warning">Invite Co-Mentor</span>, pick them under <b>Select Mentor</b>, add an optional message, and submit. They only get facilitation access once they click <span class="ui-btn primary">Accept Invitation</span> on the link you send them.</p>
      </div>

      <div class="step" data-n="6">
        <h3>Start the class</h3>
        <p class="screen">Screen: the class's <b>Mentees</b> screen</p>
        <p>Once there's at least one mentee and one module, click <span class="ui-btn success">Start Class</span>. If either is still missing, the confirmation popup tells you exactly which button to use instead rather than letting you proceed.</p>
      </div>

      <div class="step" data-n="7">
        <h3>Run each module</h3>
        <p class="screen">Screen: <b>Class &gt; Modules</b></p>
        <p>On a module's row, click <span class="ui-btn success">Start</span> when you begin teaching it — this switches on its attendance link. When the topic is done, click <span class="ui-btn primary">Complete</span> on that same row: attendees are recorded as finished, no-shows stay open for a later session.</p>
      </div>

      <div class="step" data-n="8">
        <h3>Close out the class</h3>
        <p class="screen">Screen: the class's <b>Mentees</b> screen</p>
        <p>Click <span class="ui-btn danger">End Class</span> once every module is done. This locks new enrollment and marks every mentee who attended as completed — their certificate-worthy record.</p>
      </div>

    </div>
  </section>

  <!-- ============ CALLOUT: created but no mentees ============ -->
  <div class="callout">
    <div class="kicker"><span class="dot"></span> Common snag</div>
    <h3>"I created the mentorship, but no mentees are enrolled yet"</h3>
    <p>That's expected — creating the mentorship only sets up the shell (where, what, when). Mentees are enrolled per <b>class</b>, as a separate, deliberate step. Here's exactly where to click:</p>
    <ol>
      <li>Open <b>Mentorships</b> in the sidebar, find your mentorship's row, click the <span class="ui-icon">&#8942;</span> at the end of it, and choose <span class="ui-btn primary" style="box-shadow:none">Manage Classes</span>.</li>
      <li>No class yet? Click <span class="ui-btn success">Create New Class/Cohort</span> first — mentees are enrolled into a class, not directly onto the mentorship. If a class already exists, click the <span class="ui-icon">&#8942;</span> at the end of its row and choose <span class="ui-btn success" style="box-shadow:none">Manage/Invite Mentees</span>.</li>
      <li>You're now on that class's <b>Mentees</b> screen. Click <span class="ui-btn gray">Register Mentee &#8942;</span> — this opens two options:
        <div class="path" style="margin-top:.5rem">
          <span class="crumb">Add from List — search &amp; tick existing users</span><span class="sep">/</span>
          <span class="crumb">Add Mentee — type an email to look up or create one</span>
        </div>
      </li>
      <li>In <b>Add Mentee</b>, typing an email that already exists pre-fills their name, cadre, department and facility automatically. A new email shows a banner reading <i>"No account found for this email"</i> and asks for the remaining details — the account is created with a default password of <code class="mono">123456</code>.</li>
      <li>Once mentees are on the roster, use <span class="ui-btn info">Send Invite</span> on each row (or the bulk <span class="ui-btn info">Send Invitations</span> button) to actually email them the link — see the box below.</li>
    </ol>
    <p style="margin-top:.9rem;font-size:.88rem;color:var(--ink-soft)">There's also a training-wide <b>Mentees</b> screen (Mentorships row <span class="ui-icon" style="width:1.4em;height:1.4em;font-size:.75rem">&#8942;</span> &rarr; <span style="color:var(--ink)">Mentees</span>) with its own <span class="ui-btn primary" style="box-shadow:none">Add Mentees</span> / <span class="ui-btn success" style="box-shadow:none">Quick Add New User</span> / <span class="ui-btn warning" style="box-shadow:none">Bulk Import</span> buttons — useful as an overall roster, but attendance and progress are always tracked per class, so the steps above are what actually gets someone into a session.</p>
  </div>

  <!-- ============ EMAIL DISPATCH ============ -->
  <div class="mail-box">
    <div class="mail-head">&#9993; Sending invitation emails</div>
    <div class="mail-body">
      <div class="mail-route">
        <span class="who">One mentee at a time</span>
        <span class="btn-desc">On the class's <b>Mentees</b> screen, find their row and click <span class="ui-btn info">Send Invite</span> — it reads <span class="ui-btn gray">Resend Invite</span> once one has already gone out. Confirm in the popup and it sends immediately.</span>
        <span class="result">Their row's "Invited" column updates to show how long ago it was sent.</span>
      </div>
      <div class="mail-route">
        <span class="who">If a mentee has no email on file</span>
        <span class="btn-desc">Click <span class="ui-btn warning">Update Email</span> on their row first — <span class="ui-btn info" style="box-shadow:none">Send Invite</span> only appears once there's an address to send to.</span>
      </div>
      <div class="mail-route">
        <span class="who">Everyone in the class at once</span>
        <span class="btn-desc">Click <span class="ui-btn info">Send Invitations</span> at the top of the class's Mentees screen, then choose who: <i>All mentees with email addresses</i>, <i>Only those not yet invited</i>, or <i>Only those already invited (reminder)</i>.</span>
      </div>
      <div class="mail-route">
        <span class="who">Skip email entirely</span>
        <span class="btn-desc">Click <span class="ui-btn info">Enrollment Link</span> at the top of the same screen — it hands you one public link you can paste into WhatsApp or SMS instead.</span>
      </div>
      <div class="mail-route">
        <span class="who">To invite a co-mentor</span>
        <span class="btn-desc">Click <span class="ui-btn warning">Invite Co-Mentor</span>, pick them under <b>Select Mentor</b>, add an optional message, and submit — the email goes out automatically, and the confirmation popup also gives you a copyable link.</span>
      </div>
    </div>
  </div>

  <!-- ============ MENTEE TRACK ============ -->
  <section class="track-mentee">
    <div class="track-head">
      <h2>For the mentee</h2>
      <span class="tag">No login needed to start</span>
    </div>
    <p class="track-sub">A mentee never has to find their own way into the system — every step starts from a link that lands in their inbox or phone.</p>

    <div class="steps">

      <div class="step" data-n="1">
        <h3>The invitation arrives</h3>
        <p>An email turns up with a personal enrollment link (or the mentor shared it directly) — unique to that mentee and that class.</p>
      </div>

      <div class="step" data-n="2">
        <h3>Open the link and enter their email</h3>
        <p class="screen">Public page: <span class="mono">/enroll/{token}</span></p>
        <p>The page confirms which class and program they're joining, then asks for <b>Your Email Address</b> under "Ready to enroll?". Fill it in and click <span class="ui-btn info">Continue to Enroll &rarr;</span>.</p>
      </div>

      <div class="step" data-n="3">
        <h3>Log in — or set up their account, first time only</h3>
        <p class="screen">Public page: <span class="mono">/account/verify/{user}</span></p>
        <p>Finishing enrollment needs a login. A brand-new mentee gets an account-setup email — they open it, choose a password, and click <span class="ui-btn primary">Verify &amp; Activate My Account</span>. A returning mentee just logs in as normal.</p>
      </div>

      <div class="step" data-n="4">
        <h3>Wait for a module to start</h3>
        <p>Nothing to click yet — this happens the moment the mentor clicks <span class="ui-btn success" style="box-shadow:none">Start</span> on a module.</p>
      </div>

      <div class="step" data-n="5">
        <h3>Confirm attendance</h3>
        <p class="screen">Public page: <span class="mono">/module/attend/{token}</span></p>
        <p>During the session, the mentee opens the module's attendance link and clicks <span class="ui-btn success">&#10003; Mark as Present</span>. That's the entire action — one click, no form.</p>
      </div>

      <div class="step" data-n="6">
        <h3>Track their own progress</h3>
        <p>Logging back in any time shows which modules are done, in progress, or still ahead — and their assessment score once the mentor records it.</p>
      </div>

    </div>

    <div class="callout" style="margin-top:2.2rem;border-left-color:var(--mentee)">
      <div class="kicker" style="color:var(--mentee)"><span class="dot" style="background:var(--mentee)"></span> If they're joining as a co-mentor instead</div>
      <p style="margin:0">The invitation email links to <span class="mono">/co-mentor/accept/{token}</span>. Opening it shows the mentorship they're being asked to help facilitate, with two buttons: <span class="ui-btn primary">Accept Invitation</span> or <span class="ui-btn danger">Decline</span>.</p>
    </div>
  </section>

  <!-- ============ QUICK ANSWERS ============ -->
  <div class="tips">
    <h2>Quick answers</h2>
    <div class="tip-grid">
      <div class="tip">
        <p class="q">A mentee already did this module elsewhere — do they repeat it?</p>
        <p class="a">No. If they completed it in any earlier class, they're automatically marked <span class="mono">exempted</span> here.</p>
      </div>
      <div class="tip">
        <p class="q">Can I add a module after the class has already started?</p>
        <p class="a">Yes — every mentee already enrolled is rolled into it automatically, no manual re-adding.</p>
      </div>
      <div class="tip">
        <p class="q">A mentee never got the email — what do I check?</p>
        <p class="a">Confirm an email is on file, then click <span class="mono">Resend Invite</span> on their row — it reuses the same link.</p>
      </div>
      <div class="tip">
        <p class="q">Someone missed a session — are they locked out?</p>
        <p class="a">No. The module stays open until you click <span class="mono">Complete</span> on it, so a later session can still catch them.</p>
      </div>
      <div class="tip">
        <p class="q">Can I remove a mentee I added by mistake?</p>
        <p class="a">Yes — click <span class="mono">Remove</span> on their row in the class Mentees screen; this clears their progress records for that class too.</p>
      </div>
      <div class="tip">
        <p class="q">What does clicking Start Class actually change?</p>
        <p class="a">It opens every module in it and switches on the attendance links mentees will tap.</p>
      </div>
    </div>
  </div>

  <footer class="page-footer">
    <span>MNCH Mentorship Platform — Facility Mentorship</span>
    <span>Reference guide, not a substitute for role permissions</span>
  </footer>

</div>
</body>
</html>
