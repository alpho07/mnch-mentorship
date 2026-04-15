<div class="fi-simple-page">
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
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                </div>

            {{-- Steps --}}
                <div class="steps">
                    <div class="step {{ !$linkSent ? 'active' : 'done' }}">
                        <span class="step-num">
                        @if($linkSent)
                            <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:#fff;fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round"><polyline points="20 6 9 17 4 12"/></svg>
                        @else
                            1
                        @endif
                        </span>
                        <span>Enter email</span>
                    </div>
                    <div class="step-connector {{ $linkSent ? 'done' : '' }}"></div>
                    <div class="step {{ $linkSent ? 'active' : '' }}">
                        <span class="step-num">2</span>
                        <span>Check inbox</span>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step">
                        <span class="step-num">3</span>
                        <span>Set password</span>
                    </div>
                </div>

            {{-- ── STEP 1 — Enter email ── --}}
            @if (!$linkSent)

                <h1 class="auth-h1">Forgot your password?</h1>
                <p class="auth-sub">Enter the email address linked to your account and we'll send you a reset link.</p>

                <div class="auth-infobox">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>We'll only send a reset link if an account exists for this email address.</span>
                </div>

                <form wire:submit.prevent="sendResetLink">
                    {{ $this->form }}

                    <button type="submit" class="auth-btn">
                        <span class="btn-idle" wire:loading.remove wire:target="sendResetLink">
                            Send reset link
                            <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </span>
                        <span class="btn-loading" wire:loading wire:target="sendResetLink">
                            <svg class="spin" viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5">
                            <circle cx="12" cy="12" r="10" style="opacity:0.25;stroke:currentColor"/>
                            <path d="M4 12a8 8 0 018-8" style="opacity:0.85;stroke:currentColor"/>
                            </svg>
                            Sending...
                        </span>
                    </button>
                </form>

                <a href="{{ route('filament.admin.auth.login') }}" class="auth-back">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Back to sign in
                </a>

            {{-- ── STEP 2 — Check inbox ── --}}
            @else

                <div class="inbox-panel">
                    <div class="inbox-icon">
                        <svg viewBox="0 0 24 24">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>

                    <p class="inbox-title">Check your inbox</p>
                    <p class="inbox-sub">We sent a password reset link to</p>
                    <p class="inbox-email">{{ $sentToEmail }}</p>

                    <p class="inbox-hint">
                        Didn't receive it? Check your spam folder or
                        <button wire:click="$set('linkSent', false)">try another email</button>
                    </p>

                    <a href="{{ route('filament.admin.auth.login') }}" class="auth-back auth-back-center">
                        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        Back to sign in
                    </a>
                </div>

                @endif

            </div>
        </div>
    </div>

    <style>
        .btn-idle    {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-loading {
            display: none;
            align-items: center;
            gap: 0.4rem;
        }
    </style>
</div>