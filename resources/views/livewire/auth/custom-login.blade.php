{{-- resources/views/livewire/auth/custom-login.blade.php --}}
<div class="fi-simple-page">
    <div class="auth-shell">

        {{-- ═══════════════════════════════════════════════════════════════
             LEFT — Rich Hero Panel
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="auth-hero">
            <div class="hero-bg"></div>
            <div class="hero-gradient"></div>
            <div class="hero-orb hero-orb-1"></div>
            <div class="hero-orb hero-orb-2"></div>
            <div class="hero-orb hero-orb-3"></div>

            <div class="hero-content">
                <div class="hero-badge"><span class="hero-dot"></span> Ministry of Health · Kenya</div>

                <h2 class="hero-title"><em>MNCH</em> Mentorship<br>Platform</h2>
                <p class="hero-desc">
                    Kenya's digital backbone for maternal, newborn &amp; child health mentorship — connecting
                    mentors and health workers through structured, trackable, evidence-based programmes.
                </p>

                <div class="hero-stats">
                    <div><div class="stat-val">47</div><div class="stat-lbl">Counties</div></div>
                    <div><div class="stat-val">2,400+</div><div class="stat-lbl">Health workers</div></div>
                    <div><div class="stat-val">580+</div><div class="stat-lbl">Facilities</div></div>
                    <div><div class="stat-val">98%</div><div class="stat-lbl">Completion rate</div></div>
                </div>

                <div class="hero-features">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div class="feature-text">
                            <strong>Structured Mentorship</strong>
                            <span>Create programmes with classes, modules &amp; curriculum tracking from enrolment to completion</span>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </div>
                        <div class="feature-text">
                            <strong>Real-Time Attendance</strong>
                            <span>Mentees self-confirm via secure links, mentors track participation with immutable records</span>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div class="feature-text">
                            <strong>Progress Analytics</strong>
                            <span>Module completion rates, attendance tracking &amp; facility-level performance dashboards</span>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div class="feature-text">
                            <strong>Role-Based Access</strong>
                            <span>Facility mentors, national mentors, admins &amp; mentees — each with tailored dashboards</span>
                        </div>
                    </div>
                </div>

                <div class="hero-workflow">
                    <div class="workflow-label">How It Works</div>
                    <div class="workflow-steps">
                        <div class="wf-step"><div class="wf-num">1</div><span>Create Mentorship</span></div>
                        <div class="wf-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
                        <div class="wf-step"><div class="wf-num">2</div><span>Add Classes &amp; Modules</span></div>
                        <div class="wf-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
                        <div class="wf-step"><div class="wf-num">3</div><span>Enrol &amp; Track</span></div>
                        <div class="wf-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
                        <div class="wf-step"><div class="wf-num">4</div><span>Complete &amp; Report</span></div>
                    </div>
                </div>

                <div class="hero-trust">
                    <span class="trust-label">Supported by</span>
                    <span class="trust-item">Division of RMNCAH</span>
                    <span class="trust-sep">·</span>
                    <span class="trust-item">County Health Departments</span>
                    <span class="trust-sep">·</span>
                    <span class="trust-item">Development Partners</span>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             RIGHT — Login Form
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="auth-right">
            <div class="auth-box">

                {{-- Ministry of Health Logo --}}
                <div class="moh-logo-wrap">
                    <img src="{{ asset('moh_logo.png') }}" alt="Ministry of Health — Republic of Kenya" class="moh-logo-img">
                </div>

                <h1 class="auth-h1">Welcome back</h1>
                <p class="auth-sub">Sign in to your MNCH Mentorship account to continue.</p>

                <form wire:submit.prevent="authenticate">
                    {{ $this->form }}

                    <div class="login-footer">
                        <a href="{{ route('filament.admin.auth.password-reset.request') }}" class="forgot-link">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="auth-btn" wire:loading.attr="disabled">
                        <span class="btn-idle" wire:loading.remove wire:target="authenticate">
                            Sign in
                            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </span>
                        <span class="btn-loading" wire:loading wire:target="authenticate">
                            <svg class="spin" viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5">
                                <circle cx="12" cy="12" r="10" style="opacity:0.25"/>
                                <path d="M4 12a8 8 0 018-8" style="opacity:0.85"/>
                            </svg>
                            Signing in…
                        </span>
                    </button>

                    <div class="login-footer login-footer-center">
                        <a href="{{ route('filament.admin.auth.register') }}" class="forgot-link">
                            New to the platform? Create Account.
                        </a>
                    </div>

                    <div class="login-footer login-footer-center" style="margin-top:.75rem">
                        <a href="{{ url('/') }}" class="forgot-link">
                            <svg viewBox="0 0 24 24" style="width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;display:inline;vertical-align:middle;margin-right:3px"><polyline points="15 18 9 12 15 6"/></svg>
                            Back to home
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        injectPasswordToggle('login-password');
        document.addEventListener('livewire:navigated', function () {
            setTimeout(function () { injectPasswordToggle('login-password'); }, 100);
        });
    });

    function injectPasswordToggle(inputId) {
        setTimeout(function () {
            const input = document.getElementById(inputId);
            if (!input) return;
            const wrapper = input.closest('.fi-input-wrp') || input.parentElement;
            wrapper.querySelectorAll('.pw-toggle-btn').forEach(b => b.remove());
            wrapper.style.display    = 'flex';
            wrapper.style.alignItems = 'center';
            wrapper.style.position   = 'relative';
            const eyeOpen = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
            const eyeOff  = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pw-toggle-btn';
            btn.setAttribute('aria-label', 'Toggle password visibility');
            btn.innerHTML = eyeOpen;
            btn.isRevealed = false;
            Object.assign(btn.style, {
                display:'inline-flex', alignItems:'center', justifyContent:'center',
                width:'36px', minWidth:'36px', height:'36px',
                background:'none', border:'none', cursor:'pointer',
                color:'#94a3b8', borderRadius:'6px',
                transition:'color 0.15s, background 0.15s',
                flexShrink:'0', marginRight:'2px',
            });
            btn.addEventListener('mouseenter', function () {
                this.style.color = '#1245A8'; this.style.background = 'rgba(18,69,168,0.07)';
            });
            btn.addEventListener('mouseleave', function () {
                this.style.color = '#94a3b8'; this.style.background = 'none';
            });
            btn.addEventListener('click', function () {
                this.isRevealed = !this.isRevealed;
                input.type     = this.isRevealed ? 'text' : 'password';
                this.innerHTML = this.isRevealed ? eyeOff : eyeOpen;
            });
            wrapper.appendChild(btn);
        }, 150);
    }
    </script>
    @endpush

    <style>
        /* ── Resets ────────────────────────────────────────────────────── */
        html,body{height:100%!important;margin:0!important;padding:0!important;overflow:hidden!important}
        .fi-simple-page,.fi-simple-main,.fi-simple-layout,.fi-simple{max-width:none!important;width:100%!important;padding:0!important;margin:0!important;background:transparent!important;min-height:100vh!important}
        *,*::before,*::after{box-sizing:border-box}

        /* ── Shell ─────────────────────────────────────────────────────── */
        .auth-shell{display:flex;height:100vh;width:100vw;font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,system-ui,sans-serif;-webkit-font-smoothing:antialiased}

        /* ── Hero (left) ───────────────────────────────────────────────── */
        .auth-hero{flex:0 0 58%;position:relative;overflow:hidden;overflow-y:auto;display:flex;align-items:flex-start}
        .hero-bg{position:fixed;left:0;top:0;width:58%;height:100vh;background:url('https://images.unsplash.com/photo-1584515933487-779824d29309?w=1200&q=80') center/cover no-repeat}
        .hero-gradient{position:fixed;left:0;top:0;width:58%;height:100vh;background:linear-gradient(135deg,rgba(10,34,104,.95) 0%,rgba(18,69,168,.90) 40%,rgba(37,99,235,.84) 100%)}

        .hero-orb{position:fixed;border-radius:50%;pointer-events:none;background:radial-gradient(circle,rgba(147,197,253,.12) 0%,transparent 70%)}
        .hero-orb-1{width:400px;height:400px;top:-80px;left:-60px;animation:float-orb 8s ease-in-out infinite}
        .hero-orb-2{width:300px;height:300px;bottom:60px;left:30%;animation:float-orb 10s ease-in-out infinite reverse}
        .hero-orb-3{width:200px;height:200px;top:40%;left:50%;animation:float-orb 7s ease-in-out infinite 2s}
        @keyframes float-orb{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(15px,-20px) scale(1.05)}66%{transform:translate(-10px,15px) scale(0.95)}}

        .hero-content{position:relative;z-index:2;padding:2.5rem 3rem 3rem;max-width:600px;color:#fff}

        .hero-badge{display:inline-flex;align-items:center;gap:6px;font-size:.7rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;background:rgba(255,255,255,.1);backdrop-filter:blur(6px);padding:.3rem .8rem;border-radius:999px;margin-bottom:1.25rem;border:1px solid rgba(255,255,255,.12)}
        .hero-dot{width:6px;height:6px;border-radius:50%;background:#60a5fa;box-shadow:0 0 6px #60a5fa;animation:pulse-dot 2s ease-in-out infinite}
        @keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.4}}

        .hero-title{font-size:2.1rem;font-weight:800;line-height:1.15;letter-spacing:-.035em;margin:0 0 .75rem}
        .hero-title em{font-style:normal;color:#93c5fd}
        .hero-desc{font-size:.88rem;line-height:1.6;opacity:.78;margin-bottom:1.5rem;font-weight:400}

        .hero-stats{display:flex;gap:0;border-top:1px solid rgba(255,255,255,.12);border-bottom:1px solid rgba(255,255,255,.12);padding:.9rem 0;margin-bottom:1.5rem}
        .hero-stats > div{flex:1;text-align:center;border-right:1px solid rgba(255,255,255,.1)}
        .hero-stats > div:last-child{border-right:none}
        .stat-val{font-size:1.35rem;font-weight:800;letter-spacing:-.02em}
        .stat-lbl{font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;margin-top:.1rem;font-weight:500}

        .hero-features{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1.5rem}
        .feature-card{display:flex;align-items:flex-start;gap:.6rem;padding:.7rem .75rem;border-radius:10px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.08);transition:all .25s}
        .feature-card:hover{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.15);transform:translateY(-1px)}
        .feature-icon{width:32px;height:32px;border-radius:8px;flex-shrink:0;background:rgba(147,197,253,.15);display:flex;align-items:center;justify-content:center}
        .feature-icon svg{width:16px;height:16px;stroke:#93c5fd;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .feature-text{display:flex;flex-direction:column;gap:.15rem;min-width:0}
        .feature-text strong{font-size:.76rem;font-weight:700;color:#fff;line-height:1.2}
        .feature-text span{font-size:.68rem;line-height:1.4;opacity:.6;font-weight:400}

        .hero-workflow{background:rgba(0,0,0,.15);backdrop-filter:blur(8px);border-radius:10px;padding:.75rem 1rem;margin-bottom:1.25rem;border:1px solid rgba(255,255,255,.06)}
        .workflow-label{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;opacity:.5;margin-bottom:.5rem}
        .workflow-steps{display:flex;align-items:center}
        .wf-step{display:flex;align-items:center;gap:.35rem;flex:1}
        .wf-num{width:20px;height:20px;border-radius:50%;flex-shrink:0;background:rgba(147,197,253,.2);border:1.5px solid rgba(147,197,253,.4);display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;color:#93c5fd}
        .wf-step span{font-size:.66rem;font-weight:600;opacity:.75;line-height:1.2}
        .wf-arrow{flex-shrink:0;margin:0 .15rem;opacity:.3}
        .wf-arrow svg{width:12px;height:12px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}

        .hero-trust{display:flex;align-items:center;flex-wrap:wrap;gap:.35rem;font-size:.65rem;opacity:.45;font-weight:500;padding-top:.75rem;border-top:1px solid rgba(255,255,255,.08)}
        .trust-label{font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-right:.15rem}
        .trust-sep{opacity:.4}

        /* ── Right panel ───────────────────────────────────────────────── */
        .auth-right{
            flex:1;display:flex;align-items:center;justify-content:center;
            background:#f8faff;
            background-image:radial-gradient(circle,rgba(26,84,200,.05) 1px,transparent 1px);
            background-size:22px 22px;
            overflow-y:auto;padding:2rem 1.5rem;
        }
        .auth-box{width:100%;max-width:420px}

        /* ── MoH Logo ──────────────────────────────────────────────────── */
        .moh-logo-wrap{
            display:flex;
            align-items:center;
            justify-content:flex-start;
            padding-bottom:1.4rem;
            margin-bottom:1.5rem;
            border-bottom:2px solid #DBEAFE;
        }
        .moh-logo-img{
            height:72px;
            width:auto;
            max-width:100%;
            object-fit:contain;
            object-position:left center;
            display:block;
        }

        /* ── Auth headings ─────────────────────────────────────────────── */
        .auth-h1{font-size:1.5rem;font-weight:800;color:#111827;letter-spacing:-.03em;margin:0 0 .35rem;line-height:1.2}
        .auth-sub{font-size:.87rem;color:#6b7280;margin:0 0 1.5rem;line-height:1.5}

        /* ── Submit button ─────────────────────────────────────────────── */
        .auth-btn{width:100%;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.8rem 1.5rem;margin-top:1.25rem;background:linear-gradient(135deg,#1245A8,#1A54C8);color:#fff;border:none;border-radius:12px;cursor:pointer;font-family:inherit;font-size:.9rem;font-weight:700;transition:all .2s;box-shadow:0 4px 14px rgba(18,69,168,.3)}
        .auth-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(18,69,168,.4)}
        .auth-btn:active{transform:translateY(0)}
        .auth-btn:disabled{opacity:.6;cursor:wait;transform:none!important}
        .auth-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .btn-idle{display:flex;align-items:center;gap:.4rem}
        .btn-loading{display:none;align-items:center;gap:.4rem}

        /* ── Footer links ──────────────────────────────────────────────── */
        .login-footer{display:flex;justify-content:flex-end;margin-top:.5rem;margin-bottom:.1rem}
        .login-footer-center{justify-content:center;margin-top:1.25rem}
        .forgot-link{font-size:.76rem;font-weight:600;color:#1245A8;text-decoration:none;transition:color .15s}
        .forgot-link:hover{color:#0D3A8E}

        /* ── Spinner ───────────────────────────────────────────────────── */
        .spin{animation:spin .8s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}

        /* ── Filament overrides ────────────────────────────────────────── */
        .auth-box .fi-fo-field-wrp{margin-bottom:.25rem}
        .auth-box .fi-input-wrp{border-radius:10px!important;border-color:#d1d5db!important;transition:all .2s!important}
        .auth-box .fi-input-wrp:focus-within{border-color:#1A54C8!important;box-shadow:0 0 0 3px rgba(26,84,200,.12)!important}

        /* ── Responsive ────────────────────────────────────────────────── */
        @media(max-width:1100px){.hero-features{grid-template-columns:1fr}.hero-content{padding:2rem 2.5rem 2.5rem}}
        @media(max-width:900px){
            .auth-shell{flex-direction:column;height:auto;min-height:100vh}
            html,body{overflow:auto!important}
            .auth-hero{flex:none;min-height:auto;overflow-y:visible}
            .hero-bg,.hero-gradient{position:absolute;width:100%;height:100%}
            .hero-orb{display:none}
            .hero-content{padding:2rem}
            .hero-title{font-size:1.6rem}
            .hero-features{grid-template-columns:1fr 1fr}
            .hero-workflow,.hero-trust{display:none}
            .auth-right{padding:1.5rem 1rem}
        }
        @media(max-width:600px){
            .hero-content{padding:1.5rem}
            .hero-title{font-size:1.3rem}
            .stat-val{font-size:1.15rem}
            .hero-features{grid-template-columns:1fr}
            .moh-logo-img{height:56px}
        }
    </style>
</div>
