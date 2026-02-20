<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Progress — {{ $class->name }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
        <style>
            *, *::before, *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            :root {
                --bg: #f1f5f9;
                --surface: #ffffff;
                --surface-raised: #f8fafc;
                --border: #e2e8f0;
                --border-light: #f1f5f9;

                --text: #0f172a;
                --text-secondary: #475569;
                --text-muted: #94a3b8;

                --blue: #1e40af;
                --blue-mid: #2563eb;
                --blue-light: #eff6ff;
                --blue-border: #bfdbfe;
                --blue-vivid: #3b82f6;

                --green: #166534;
                --green-mid: #16a34a;
                --green-light: #f0fdf4;
                --green-border: #bbf7d0;
                --green-badge: #dcfce7;

                --purple: #6b21a8;
                --purple-light: #faf5ff;
                --purple-border: #e9d5ff;
                --purple-badge: #f3e8ff;

                --amber: #92400e;
                --amber-badge: #fef3c7;

                --red: #991b1b;
                --red-badge: #fee2e2;

                --teal: #115e59;
                --teal-light: #f0fdfa;
                --teal-border: #99f6e4;

                --radius-lg: 16px;
                --radius: 12px;
                --radius-sm: 8px;
                --radius-xs: 6px;

                --shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.06);
                --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
                --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
            }

            body {
                font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
                background: var(--bg);
                color: var(--text);
                line-height: 1.6;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            .page {
                max-width: 1080px;
                margin: 0 auto;
                padding: 2rem 1.25rem 5rem;
            }

            /* ═══ HERO ═══ */
            .hero {
                background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 55%, #2563eb 100%);
                border-radius: var(--radius-lg);
                padding: 2.25rem 2.5rem 2.5rem;
                color: #fff;
                position: relative;
                overflow: hidden;
                box-shadow: var(--shadow-lg), 0 0 0 1px rgba(30,64,175,0.15);
            }
            .hero::before {
                content: '';
                position: absolute;
                top: -60%;
                right: -15%;
                width: 520px;
                height: 520px;
                background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 65%);
            }
            .hero-row {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
                position: relative;
                z-index: 1;
            }
            .hero h1 {
                font-size: 1.65rem;
                font-weight: 800;
                letter-spacing: -0.03em;
                line-height: 1.2;
            }
            .hero .sub {
                margin-top: 0.3rem;
                font-size: 0.88rem;
                font-weight: 500;
                opacity: 0.78;
            }

            .btn-back {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                padding: 0.55rem 1.15rem;
                background: rgba(255,255,255,0.13);
                backdrop-filter: blur(8px);
                border: 1px solid rgba(255,255,255,0.2);
                border-radius: var(--radius-sm);
                color: #fff;
                font-size: 0.82rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s;
                font-family: inherit;
                white-space: nowrap;
            }
            .btn-back:hover {
                background: rgba(255,255,255,0.22);
                transform: translateY(-1px);
            }
            .btn-back svg {
                width: 17px;
                height: 17px;
            }

            /* ── Stats ── */
            .stat-row {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 0.75rem;
                margin-top: 1.75rem;
                position: relative;
                z-index: 1;
            }
            .stat {
                background: rgba(255,255,255,0.11);
                backdrop-filter: blur(6px);
                border: 1px solid rgba(255,255,255,0.14);
                border-radius: var(--radius-sm);
                padding: 1rem 1.15rem;
            }
            .stat-val {
                font-size: 1.85rem;
                font-weight: 800;
                letter-spacing: -0.04em;
                line-height: 1;
            }
            .stat-lbl {
                font-size: 0.7rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                opacity: 0.7;
                margin-top: 0.35rem;
            }

            /* ── Progress bar ── */
            .prog {
                margin-top: 1.75rem;
                position: relative;
                z-index: 1;
            }
            .prog-head {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
            }
            .prog-head span {
                font-size: 0.78rem;
                font-weight: 600;
                opacity: 0.85;
            }
            .prog-track {
                height: 10px;
                background: rgba(255,255,255,0.14);
                border-radius: 100px;
                overflow: hidden;
            }
            .prog-fill {
                height: 100%;
                border-radius: 100px;
                background: linear-gradient(90deg, #4ade80, #22c55e, #16a34a);
                transition: width 1.4s cubic-bezier(0.22, 1, 0.36, 1);
                position: relative;
            }
            .prog-fill::after {
                content: '';
                position: absolute;
                inset: 0;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
                animation: shimmer 2.4s ease-in-out infinite;
            }
            @keyframes shimmer {
                0% {
                    transform: translateX(-100%);
                }
                100% {
                    transform: translateX(100%);
                }
            }

            .att-note {
                display: flex;
                align-items: center;
                gap: 0.45rem;
                margin-top: 0.65rem;
                font-size: 0.8rem;
                font-weight: 500;
                opacity: 0.82;
            }
            .att-note svg {
                width: 17px;
                height: 17px;
                flex-shrink: 0;
            }

            /* ═══ CARDS ═══ */
            .card {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: var(--radius-lg);
                padding: 1.75rem 2rem;
                margin-top: 1.25rem;
                box-shadow: var(--shadow);
            }
            .card-hdr {
                display: flex;
                align-items: center;
                gap: 0.65rem;
                margin-bottom: 0.3rem;
            }
            .card-icon {
                width: 32px;
                height: 32px;
                border-radius: var(--radius-xs);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .card-icon svg {
                width: 17px;
                height: 17px;
            }
            .card-title {
                font-size: 1.1rem;
                font-weight: 750;
                letter-spacing: -0.015em;
            }
            .card-badge {
                font-size: 0.68rem;
                font-weight: 700;
                padding: 0.15rem 0.55rem;
                border-radius: 100px;
                margin-left: 0.35rem;
            }
            .card-desc {
                font-size: 0.82rem;
                color: var(--text-secondary);
                margin-bottom: 1.25rem;
            }

            /* ═══ MODULES ═══ */
            .mod-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 0.85rem;
            }
            .mod {
                border-radius: var(--radius);
                padding: 1.15rem 1.25rem;
                border: 1px solid;
                transition: box-shadow 0.2s, transform 0.2s;
            }
            .mod:hover {
                box-shadow: var(--shadow-md);
                transform: translateY(-1px);
            }
            .mod h3 {
                font-size: 0.92rem;
                font-weight: 700;
                line-height: 1.35;
            }
            .mod .mod-desc {
                font-size: 0.78rem;
                color: var(--text-secondary);
                margin-top: 0.25rem;
                line-height: 1.55;
            }
            .mod-foot {
                display: flex;
                align-items: center;
                gap: 0.4rem;
                margin-top: 0.7rem;
                font-size: 0.76rem;
                font-weight: 600;
            }
            .mod-foot svg {
                width: 15px;
                height: 15px;
                flex-shrink: 0;
            }
            .mod-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.5rem;
            }

            .mod--exempt  {
                background: var(--purple-light);
                border-color: var(--purple-border);
            }
            .mod--exempt .mod-foot {
                color: var(--purple);
            }
            .mod--active  {
                background: var(--surface);
                border-color: var(--border);
            }
            .mod--done    {
                background: var(--green-light);
                border-color: var(--green-border);
            }
            .mod--done .mod-foot {
                color: var(--green);
            }

            .pill {
                display: inline-flex;
                align-items: center;
                padding: 0.18rem 0.6rem;
                border-radius: 100px;
                font-size: 0.68rem;
                font-weight: 700;
                letter-spacing: 0.01em;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .pill--gray   {
                background: #f1f5f9;
                color: #475569;
            }
            .pill--amber  {
                background: var(--amber-badge);
                color: var(--amber);
            }
            .pill--green  {
                background: var(--green-badge);
                color: var(--green);
            }

            .mod-details {
                display: flex;
                gap: 1.25rem;
                margin-top: 0.65rem;
                font-size: 0.78rem;
                color: var(--text-secondary);
            }
            .mod-details strong {
                font-family: 'JetBrains Mono', monospace;
                font-weight: 600;
                font-size: 0.76rem;
                color: var(--text);
            }

            /* Assessments */
            .assess {
                margin-top: 0.85rem;
                padding-top: 0.7rem;
                border-top: 1px solid var(--border-light);
            }
            .assess-lbl {
                font-size: 0.68rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: var(--text-muted);
                margin-bottom: 0.45rem;
            }
            .assess-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.45rem 0.7rem;
                background: var(--surface-raised);
                border-radius: var(--radius-xs);
                font-size: 0.78rem;
                margin-bottom: 0.3rem;
            }
            .score {
                font-family: 'JetBrains Mono', monospace;
                font-weight: 600;
                font-size: 0.72rem;
                padding: 0.15rem 0.5rem;
                border-radius: var(--radius-xs);
            }
            .score--pass {
                background: var(--green-badge);
                color: var(--green);
            }
            .score--fail {
                background: var(--red-badge);
                color: var(--red);
            }
            .score--wait {
                color: var(--text-muted);
                font-weight: 500;
            }

            /* ═══ RESOURCES ═══ */
            .res-grid {
                display: grid;
                gap: 0.75rem;
            }
            .res-link {
                display: flex;
                align-items: center;
                gap: 1.15rem;
                padding: 1.15rem 1.25rem;
                border-radius: var(--radius);
                border: 1px solid;
                text-decoration: none;
                color: inherit;
                transition: box-shadow 0.2s, transform 0.2s, border-color 0.2s;
            }
            .res-link:hover {
                box-shadow: var(--shadow-md);
                transform: translateY(-2px);
            }
            .res-link--blue {
                background: var(--blue-light);
                border-color: var(--blue-border);
            }
            .res-link--blue:hover {
                border-color: var(--blue-vivid);
            }
            .res-link--teal {
                background: var(--teal-light);
                border-color: var(--teal-border);
            }
            .res-link--teal:hover {
                border-color: #2dd4bf;
            }

            .res-ico {
                width: 48px;
                height: 48px;
                border-radius: var(--radius-sm);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .res-ico svg {
                width: 22px;
                height: 22px;
                color: #fff;
            }
            .res-ico--blue {
                background: linear-gradient(135deg, var(--blue), var(--blue-mid));
            }
            .res-ico--teal {
                background: linear-gradient(135deg, var(--teal), #0d9488);
            }

            .res-body h3 {
                font-size: 0.9rem;
                font-weight: 700;
            }
            .res-link--blue .res-body h3 {
                color: var(--blue);
            }
            .res-link--teal .res-body h3 {
                color: var(--teal);
            }
            .res-body p {
                font-size: 0.78rem;
                color: var(--text-secondary);
                margin-top: 0.15rem;
            }

            .res-arrow {
                margin-left: auto;
                flex-shrink: 0;
            }
            .res-arrow svg {
                width: 18px;
                height: 18px;
            }
            .res-link--blue .res-arrow {
                color: var(--blue-mid);
            }
            .res-link--teal .res-arrow {
                color: #0d9488;
            }

            /* ═══ EMPTY ═══ */
            .empty {
                text-align: center;
                padding: 4rem 2rem;
                background: var(--surface);
                border: 2px dashed var(--border);
                border-radius: var(--radius-lg);
                margin-top: 1.25rem;
            }
            .empty svg {
                width: 48px;
                height: 48px;
                color: var(--text-muted);
                margin: 0 auto 0.75rem;
            }
            .empty h3 {
                font-size: 0.95rem;
                font-weight: 700;
                color: var(--text-secondary);
            }
            .empty p {
                font-size: 0.82rem;
                color: var(--text-muted);
                margin-top: 0.25rem;
            }

            /* ═══ RESPONSIVE ═══ */
            @media (max-width: 768px) {
                .page {
                    padding: 1rem 0.75rem 3rem;
                }
                .hero {
                    padding: 1.75rem 1.5rem 2rem;
                    border-radius: var(--radius);
                }
                .hero h1 {
                    font-size: 1.35rem;
                }
                .hero-row {
                    flex-direction: column;
                    gap: 0.75rem;
                }
                .stat-row {
                    grid-template-columns: repeat(2, 1fr);
                }
                .stat-val {
                    font-size: 1.5rem;
                }
                .mod-grid {
                    grid-template-columns: 1fr;
                }
                .card {
                    padding: 1.25rem;
                    border-radius: var(--radius);
                }
            }
            @media (max-width: 420px) {
                .stat-row {
                    gap: 0.5rem;
                }
                .stat {
                    padding: 0.75rem;
                }
                .stat-val {
                    font-size: 1.3rem;
                }
            }
        </style>
    </head>
    <body>
    <div class="page">

        {{-- ═══ HERO ═══ --}}
        <div class="hero">
            <div class="hero-row">
                <div>
                    <h1>{{ $class->name }}</h1>
                    <div class="sub">{{ $class->training->title ?? $class->training->name ?? 'Mentorship Program' }} &bull; {{ $class->training->facility->name ?? '' }}</div>
                </div>
            @auth
                    <a href="{{ route('filament.admin.pages.mentee-dashboard') }}" class="btn-back">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </a>
                @endauth
            </div>

            <div class="stat-row">
                <div class="stat">
                    <div class="stat-val">{{ $totalModules }}</div>
                    <div class="stat-lbl">Total Modules</div>
                </div>
                <div class="stat">
                    <div class="stat-val">{{ $completedCount }}</div>
                    <div class="stat-lbl">Completed</div>
                </div>
                <div class="stat">
                    <div class="stat-val">{{ $exemptedCount }}</div>
                    <div class="stat-lbl">Exempted</div>
                </div>
                <div class="stat">
                    <div class="stat-val">{{ $progressPercentage }}%</div>
                    <div class="stat-lbl">Progress</div>
                </div>
            </div>

            <div class="prog">
                <div class="prog-head">
                    <span>Overall Progress</span>
                    <span>{{ $progressPercentage }}%</span>
                </div>
                <div class="prog-track">
                    <div class="prog-fill" style="width: {{ $progressPercentage }}%"></div>
                </div>
            @if($attendanceRate > 0)
                    <div class="att-note">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Average Attendance: <strong>&nbsp;{{ $attendanceRate }}%</strong>
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══ EXEMPTED MODULES ═══ --}}
    @if($exemptedModules->count() > 0)
            <div class="card">
                <div class="card-hdr">
                    <span class="card-icon" style="background: var(--purple-badge);">
                        <svg fill="none" stroke="var(--purple)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <span class="card-title">Exempted Modules</span>
                    <span class="card-badge" style="background: var(--purple-badge); color: var(--purple);">{{ $exemptedModules->count() }}</span>
                </div>
                <p class="card-desc">Completed in a previous class — you're exempted from these.</p>
                <div class="mod-grid">
                @foreach($exemptedModules as $progress)
                    <div class="mod mod--exempt">
                        <h3>{{ $progress->classModule->programModule->name ?? 'Module' }}</h3>
                        <p class="mod-desc">{{ Str::limit($progress->classModule->programModule->description ?? '', 100) }}</p>
                        <div class="mod-foot">
                            <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Exempted {{ $progress->exempted_at ? $progress->exempted_at->format('M d, Y') : '' }}
                        </div>
                    </div>
                @endforeach
                </div>
            </div>
    @endif

    {{-- ═══ ACTIVE MODULES ═══ --}}
    @if($activeModules->count() > 0)
            <div class="card">
                <div class="card-hdr">
                    <span class="card-icon" style="background: var(--blue-light);">
                        <svg fill="none" stroke="var(--blue)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </span>
                    <span class="card-title">Active Modules</span>
                    <span class="card-badge" style="background: var(--blue-light); color: var(--blue);">{{ $activeModules->count() }}</span>
                </div>
                <div class="mod-grid">
                @foreach($activeModules as $progress)
                    <div class="mod mod--active">
                        <div class="mod-top">
                            <h3>{{ $progress->classModule->programModule->name ?? 'Module' }}</h3>
                            @if($progress->status === 'not_started')
                            <span class="pill pill--gray">Not Started</span>
                            @elseif($progress->status === 'in_progress')
                            <span class="pill pill--amber">In Progress</span>
                            @endif
                        </div>
                        <p class="mod-desc">{{ Str::limit($progress->classModule->programModule->description ?? '', 120) }}</p>
                        @if($progress->attendance_percentage !== null || $progress->assessment_score !== null)
                        <div class="mod-details">
                                @if($progress->attendance_percentage !== null)
                            <span>Attendance: <strong>{{ $progress->attendance_percentage }}%</strong></span>
                                @endif
                                @if($progress->assessment_score !== null)
                            <span>Assessment: <strong>{{ $progress->assessment_score }}%</strong></span>
                                @endif
                        </div>
                        @endif
                        @if($progress->classModule->moduleAssessments && $progress->classModule->moduleAssessments->count() > 0)
                        <div class="assess">
                            <div class="assess-lbl">Assessments</div>
                                @foreach($progress->classModule->moduleAssessments as $assessment)
                                    @php $result = $progress->assessmentResults->firstWhere('module_assessment_id', $assessment->id); @endphp
                            <div class="assess-row">
                                <span>{{ $assessment->title }}</span>
                                        @if($result)
                                <span class="score {{ $result->status === 'passed' ? 'score--pass' : 'score--fail' }}">
                                                {{ $result->score }}% &middot; {{ ucfirst($result->status) }}
                                </span>
                                        @else
                                <span class="score score--wait">Pending</span>
                                        @endif
                            </div>
                                @endforeach
                        </div>
                        @endif
                    </div>
                @endforeach
                </div>
            </div>
    @endif

    {{-- ═══ COMPLETED MODULES ═══ --}}
    @if($completedModules->count() > 0)
            <div class="card">
                <div class="card-hdr">
                    <span class="card-icon" style="background: var(--green-badge);">
                        <svg fill="none" stroke="var(--green)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <span class="card-title">Completed Modules</span>
                    <span class="card-badge" style="background: var(--green-badge); color: var(--green);">{{ $completedModules->count() }}</span>
                </div>
                <div class="mod-grid">
                @foreach($completedModules as $progress)
                    <div class="mod mod--done">
                        <h3>{{ $progress->classModule->programModule->name ?? 'Module' }}</h3>
                        <p class="mod-desc">{{ Str::limit($progress->classModule->programModule->description ?? '', 100) }}</p>
                        <div class="mod-foot">
                            <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Completed {{ $progress->completed_at ? $progress->completed_at->format('M d, Y') : '' }}
                        </div>
                        @if($progress->attendance_percentage || $progress->assessment_score)
                        <div class="mod-details">
                                @if($progress->attendance_percentage)
                            <span>Attendance: <strong>{{ $progress->attendance_percentage }}%</strong></span>
                                @endif
                                @if($progress->assessment_score)
                            <span>Score: <strong>{{ $progress->assessment_score }}%</strong>
                                <span style="color: {{ $progress->assessment_status === 'passed' ? 'var(--green)' : 'var(--red)' }}; font-weight: 700; font-size: 0.72rem; margin-left: 0.2rem;">
                                    ({{ ucfirst($progress->assessment_status) }})
                                </span>
                            </span>
                                @endif
                        </div>
                        @endif
                    </div>
                @endforeach
                </div>
            </div>
    @endif

    {{-- ═══ RESOURCES ═══ --}}
            <div class="card">
                <div class="card-hdr">
                    <span class="card-icon" style="background: var(--amber-badge);">
                        <svg fill="none" stroke="var(--amber)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </span>
                    <span class="card-title">Resources &amp; Learning Materials</span>
                </div>
                <p class="card-desc">Reference guides and tools to support your mentorship journey.</p>
                <div class="res-grid">
                    <a href="https://mnchkenyamentorship.org/resources/infant-child-mentorship-manual" target="_blank" rel="noopener" class="res-link res-link--blue">
                        <div class="res-ico res-ico--blue">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <div class="res-body">
                            <h3>Infant &amp; Child Mentorship Manual</h3>
                            <p>Clinical mentorship guide — protocols, checklists, and evidence-based best practices for infant and child health.</p>
                        </div>
                        <div class="res-arrow">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </div>
                    </a>
                    <a href="https://mnchkenyamentorship.org" target="_blank" rel="noopener" class="res-link res-link--teal">
                        <div class="res-ico res-ico--teal">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                        </div>
                        <div class="res-body">
                            <h3>MNCH Kenya Mentorship Portal</h3>
                            <p>Access all mentorship resources, training materials, and program updates.</p>
                        </div>
                        <div class="res-arrow">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </div>
                    </a>
                </div>
            </div>

    {{-- ═══ EMPTY STATE ═══ --}}
    @if($totalModules === 0)
            <div class="empty">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <h3>No modules yet</h3>
                <p>Modules will appear here once they are added to your class.</p>
            </div>
        @endif

    </div>
</body>
</html>