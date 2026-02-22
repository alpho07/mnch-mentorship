<div class="fi-simple-page">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .fi-simple-page {
            min-height: 100vh !important;
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            width: 100% !important;
            background: none !important;
            display: block !important;
        }
        .fi-simple-main {
            padding: 0 !important;
            max-width: none !important;
            width: 100% !important;
            min-height: 100vh !important;
            display: flex !important;
            align-items: stretch !important;
        }

        .auth-shell {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* ── LEFT ────────────────────────────────────────────────────── */
        .auth-hero {
            position: relative;
            width: 47%;
            flex-shrink: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 52px 56px;
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            background-image: url('https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=1400&q=85&auto=format&fit=crop');
            background-size: cover;
            background-position: center 30%;
        }
        .hero-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(175deg, rgba(10,15,40,0.38) 0%, rgba(10,15,40,0.58) 40%, rgba(10,15,40,0.90) 100%);
        }
        .hero-content {
            position: relative;
            z-index: 2;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.22);
            backdrop-filter: blur(12px);
            color: #fff;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            padding: 7px 16px;
            border-radius: 100px;
            margin-bottom: 24px;
        }
        .hero-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #34d399;
            animation: blink 2.4s ease-in-out infinite;
        }
        @keyframes blink {
            0%,100%{
                opacity:1
            }
            50%{
                opacity:.35
            }
        }
        .hero-title {
            font-size: 2.85rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.14;
            letter-spacing: -0.5px;
            margin-bottom: 18px;
        }
        .hero-title em {
            font-style: normal;
            color: #6ee7b7;
        }
        .hero-desc {
            font-size: 1rem;
            color: rgba(255,255,255,0.72);
            line-height: 1.7;
            max-width: 360px;
            margin-bottom: 44px;
        }
        .hero-stats {
            display: flex;
            gap: 36px;
            padding-top: 32px;
            border-top: 1px solid rgba(255,255,255,0.18);
        }
        .stat-val {
            font-size: 1.6rem;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            letter-spacing: -0.5px;
        }
        .stat-lbl {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.5);
            margin-top: 5px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        /* ── RIGHT ───────────────────────────────────────────────────── */
        .auth-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 60px 40px;
            overflow-y: auto;
        }
        .auth-box {
            width: 100%;
            max-width: 408px;
        }

        .auth-mark {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            box-shadow: 0 8px 22px rgba(79,70,229,.28);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 36px;
        }
        .auth-mark svg {
            width: 24px;
            height: 24px;
            stroke: #fff;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .auth-h1 {
            font-size: 1.95rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.4px;
            line-height: 1.2;
            margin-bottom: 7px;
        }
        .auth-sub {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.55;
            margin-bottom: 36px;
        }

        /* ── Filament input overrides ─────────────────────────────────── */
        .fi-fo-field-wrp {
            margin-bottom: 22px !important;
        }
        .fi-fo-field-wrp-label label, .fi-label-wrp label, .fi-fo-field-wrp > div > label, [data-field-wrapper] label {
            font-size: 0.8125rem !important;
            font-weight: 600 !important;
            color: #334155 !important;
            letter-spacing: 0.05px !important;
            margin-bottom: 7px !important;
            display: block !important;
        }
        .fi-input-wrp {
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 11px !important;
            background: #f8fafc !important;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease !important;
            overflow: hidden !important;
            box-shadow: none !important;
        }
        .fi-input-wrp:focus-within {
            border-color: #4f46e5 !important;
            background: #ffffff !important;
            box-shadow: 0 0 0 3.5px rgba(79,70,229,0.10) !important;
        }
        .fi-input {
            height: 52px !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            outline: none !important;
            padding: 0 16px !important;
            font-size: 0.9375rem !important;
            color: #0f172a !important;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif !important;
            font-weight: 500 !important;
            width: 100% !important;
        }
        .fi-input::placeholder {
            color: #94a3b8 !important;
            font-weight: 400 !important;
            font-size: 0.9rem !important;
        }
        .fi-fo-field-wrp-validation-error {
            font-size: 0.8rem !important;
            color: #ef4444 !important;
            margin-top: 6px !important;
        }

        /* ── Step indicator ──────────────────────────────────────────── */
        .steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 32px;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #cbd5e1;
            flex: 1;
        }
        .step.active {
            color: #4f46e5;
        }
        .step.done {
            color: #22c55e;
        }
        .step-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: 2px solid currentColor;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .step.active .step-num {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
        }
        .step.done .step-num {
            background: #22c55e;
            color: #fff;
            border-color: #22c55e;
        }
        .step-line {
            flex: 1;
            height: 1.5px;
            background: #e2e8f0;
            margin: 0 8px;
        }
        .step.done + .step-line {
            background: #22c55e;
        }

        /* ── Info box ────────────────────────────────────────────────── */
        .auth-infobox {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 18px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            margin-bottom: 28px;
            font-size: 0.875rem;
            color: #1e40af;
            line-height: 1.55;
        }
        .auth-infobox svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .auth-submit {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 11px;
            background: linear-gradient(135deg, #4f46e5 0%, #6d28d9 100%);
            color: #fff;
            font-size: 0.9375rem;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(79,70,229,0.30);
            transition: opacity 0.18s, transform 0.15s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 4px;
        }
        .auth-submit:hover {
            opacity: 0.91;
            transform: translateY(-1.5px);
            box-shadow: 0 8px 24px rgba(79,70,229,0.35);
        }
        .auth-submit:active {
            transform: translateY(0);
            opacity: 1;
        }
        .auth-submit:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        .auth-submit svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            transition: transform 0.2s;
        }
        .auth-submit:hover svg {
            transform: translateX(3px);
        }

        .auth-back {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            transition: color 0.15s;
        }
        .auth-back:hover {
            color: #4f46e5;
        }
        .auth-back svg {
            width: 15px;
            height: 15px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        @media (max-width: 860px) {
            .auth-hero {
                display: none;
            }
            .auth-right {
                background: linear-gradient(145deg, #f0f4ff 0%, #faf5ff 100%);
                padding: 40px 24px;
            }
            .auth-box {
                background: #fff;
                border-radius: 20px;
                padding: 40px 32px;
                box-shadow: 0 10px 50px rgba(79,70,229,0.10);
            }
        }
        @media (max-width: 480px) {
            .auth-right {
                padding: 24px 16px;
            }
            .auth-box {
                padding: 32px 24px;
            }
            .auth-h1 {
                font-size: 1.6rem;
            }
        }
    </style>

    <div class="auth-shell">

        {{-- LEFT --}}
        <div class="auth-hero">
            <div class="hero-bg"></div>
            <div class="hero-gradient"></div>
            <div class="hero-content">
                <div class="hero-badge"><span class="hero-dot"></span> Ministry of Health · Kenya</div>
                <h2 class="hero-title"><em>MNCH</em> Mentorship<br>Platform</h2>
                <p class="hero-desc">Transforming maternal, newborn and child health outcomes through structured, evidence-based mentorship across Kenya's health facilities.</p>
                <div class="hero-stats">
                    <div><div class="stat-val">47</div><div class="stat-lbl">Counties</div></div>
                    <div><div class="stat-val">2,400+</div><div class="stat-lbl">Health workers</div></div>
                    <div><div class="stat-val">580+</div><div class="stat-lbl">Facilities</div></div>
                </div>
            </div>
        </div>

    {{-- RIGHT --}}
        <div class="auth-right">
            <div class="auth-box">

                <div class="auth-mark">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                </div>

            {{-- Step indicator --}}
                <div class="steps">
                    <div class="step active">
                        <span class="step-num">1</span>
                        <span>Enter email</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <span class="step-num">2</span>
                        <span>Check inbox</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <span class="step-num">3</span>
                        <span>Set password</span>
                    </div>
                </div>

                <h1 class="auth-h1">Forgot your password?</h1>
                <p class="auth-sub">Enter the email address linked to your account and we'll send you a reset link.</p>

                <div class="auth-infobox">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>We'll only send a reset link if an account exists for this email address.</span>
                </div>

                <form wire:submit="sendResetLink">
                {{ $this->form }}

                    <button type="submit"
                            class="auth-submit"
                            wire:loading.attr="disabled"
                            wire:target="request">
                        <span wire:loading.remove wire:target="request">Send reset link</span>
                        <span wire:loading wire:target="request">Sending…</span>
                        <svg wire:loading.remove wire:target="request" viewBox="0 0 24 24">
                        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </form>

                <a href="{{ route('filament.admin.auth.login') }}" class="auth-back">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Back to sign in
                </a>

            </div>
        </div>
    </div>
</div>