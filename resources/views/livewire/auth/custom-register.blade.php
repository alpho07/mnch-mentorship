{{-- resources/views/livewire/auth/custom-register.blade.php --}}
<div class="fi-simple-page">
    <div class="auth-shell">

        {{-- ═══════════════════════════════════════════════════════════════
             LEFT — Hero Panel
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="auth-hero">
            <div class="hero-bg"></div>
            <div class="hero-gradient"></div>
            <div class="hero-content">
                <div class="hero-badge"><span class="hero-dot"></span> Ministry of Health · Kenya</div>
                <h2 class="hero-title"><em>MNCH</em> Mentorship<br>Platform</h2>
                <p class="hero-desc">
                    Join Kenya's largest network of maternal, newborn and child health mentors.
                    Register to create and manage evidence-based mentorship programmes at your facility.
                </p>
                <div class="hero-stats">
                    <div><div class="stat-val">47</div><div class="stat-lbl">Counties</div></div>
                    <div><div class="stat-val">2,400+</div><div class="stat-lbl">Health workers</div></div>
                    <div><div class="stat-val">580+</div><div class="stat-lbl">Facilities</div></div>
                </div>

                {{-- Benefit bullets --}}
                <div class="hero-benefits">
                    <div class="benefit-item">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>Create &amp; manage mentorship programmes</span>
                    </div>
                    <div class="benefit-item">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>Track attendance &amp; module completion</span>
                    </div>
                    <div class="benefit-item">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>Invite &amp; onboard mentees seamlessly</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             RIGHT — Registration Form
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="auth-right">
            <div class="auth-box">

                {{-- Icon mark --}}
                <div class="auth-mark">
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </div>

                <h1 class="auth-h1">Create Mentor Account</h1>
                <p class="auth-sub">Register to start managing mentorship programmes on the MNCH platform.</p>

                {{-- Info callout --}}
                <div class="auth-infobox">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>Your account will be activated immediately. You can then log in and start creating mentorships.</span>
                </div>

                {{-- Filament form --}}
                <form wire:submit="register">
                    {{ $this->form }}

                    <button type="submit" class="auth-btn" wire:loading.attr="disabled">
                        <span class="btn-idle" wire:loading.remove wire:target="register">
                            Create Account
                            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        </span>
                        <span class="btn-loading" wire:loading wire:target="register">
                            <svg class="spin" viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5">
                            <circle cx="12" cy="12" r="10" style="opacity:0.25;stroke:currentColor"/>
                            <path d="M4 12a8 8 0 018-8" style="opacity:0.85;stroke:currentColor"/>
                            </svg>
                            Creating account…
                        </span>
                    </button>
                </form>

                <a href="{{ route('filament.admin.auth.login') }}" class="auth-back auth-back-center">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Already have an account? Sign in
                </a>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         STYLES  (self-contained — same design system as login / pw-reset)
         ═══════════════════════════════════════════════════════════════════ --}}
    <style>
        /* ── Resets ───────────────────────────────────────────────────── */
        html,body{
            height:100%!important;
            margin:0!important;
            padding:0!important;
            overflow:hidden!important
        }
        .fi-simple-page,.fi-simple-main,.fi-simple-layout,.fi-simple{
            max-width:none!important;
            width:100%!important;
            padding:0!important;
            margin:0!important;
            background:transparent!important;
            min-height:100vh!important;
        }
        *,*::before,*::after{
            box-sizing:border-box
        }

        /* ── Shell — full-viewport split ──────────────────────────────── */
        .auth-shell{
            display:flex;
            height:100vh;
            width:100vw;
            font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,system-ui,sans-serif;
            -webkit-font-smoothing:antialiased;
        }

        /* ── Hero (left panel) ────────────────────────────────────────── */
        .auth-hero{
            flex:0 0 58%;
            position:relative;
            overflow:hidden;
            display:flex;
            align-items:center;
        }
        .hero-bg{
            position:absolute;
            inset:0;
            background:url('https://images.unsplash.com/photo-1584515933487-779824d29309?w=1200&q=80') center/cover no-repeat;
        }
        .hero-gradient{
            position:absolute;
            inset:0;
            background:linear-gradient(135deg,rgba(6,78,59,.92) 0%,rgba(21,128,61,.85) 40%,rgba(22,163,74,.78) 100%);
        }
        .hero-content{
            position:relative;
            z-index:2;
            padding:3.5rem;
            max-width:540px;
            color:#fff;
        }

        /* Badge */
        .hero-badge{
            display:inline-flex;
            align-items:center;
            gap:6px;
            font-size:.72rem;
            font-weight:600;
            letter-spacing:.06em;
            text-transform:uppercase;
            background:rgba(255,255,255,.12);
            backdrop-filter:blur(6px);
            padding:.35rem .85rem;
            border-radius:999px;
            margin-bottom:1.5rem;
            border:1px solid rgba(255,255,255,.15);
        }
        .hero-dot{
            width:6px;
            height:6px;
            border-radius:50%;
            background:#4ade80;
            box-shadow:0 0 6px #4ade80;
            animation:pulse-dot 2s ease-in-out infinite;
        }
        @keyframes pulse-dot{
            0%,100%{
                opacity:1
            }
            50%{
                opacity:.4
            }
        }

        /* Title */
        .hero-title{
            font-size:2.25rem;
            font-weight:800;
            line-height:1.15;
            letter-spacing:-.035em;
            margin:0 0 1rem;
        }
        .hero-title em{
            font-style:normal;
            color:#86efac
        }

        /* Description */
        .hero-desc{
            font-size:.92rem;
            line-height:1.65;
            opacity:.82;
            margin-bottom:2rem;
            font-weight:400;
        }

        /* Stats */
        .hero-stats{
            display:flex;
            gap:2.5rem;
            padding-top:1.25rem;
            border-top:1px solid rgba(255,255,255,.15);
        }
        .stat-val{
            font-size:1.5rem;
            font-weight:800;
            letter-spacing:-.02em
        }
        .stat-lbl{
            font-size:.72rem;
            text-transform:uppercase;
            letter-spacing:.05em;
            opacity:.6;
            margin-top:.15rem;
            font-weight:500;
        }

        /* Benefits list */
        .hero-benefits{
            margin-top:2rem;
            display:flex;
            flex-direction:column;
            gap:.65rem;
        }
        .benefit-item{
            display:flex;
            align-items:center;
            gap:.6rem;
            font-size:.84rem;
            font-weight:500;
            opacity:.85;
        }
        .benefit-item svg{
            width:18px;
            height:18px;
            flex-shrink:0;
            stroke:#86efac;
            fill:none;
            stroke-width:2;
            stroke-linecap:round;
            stroke-linejoin:round;
        }

        /* ── Right panel ──────────────────────────────────────────────── */
        .auth-right{
            flex:1;
            display:flex;
            align-items:flex-start;
            justify-content:center;
            background:#f9fafb;
            overflow-y:auto;
            padding:2rem 1.5rem;
        }
        .auth-box{
            width:100%;
            max-width:480px;
            padding:1.5rem 0 3rem;
        }

        /* ── Icon mark ────────────────────────────────────────────────── */
        .auth-mark{
            width:48px;
            height:48px;
            border-radius:14px;
            background:linear-gradient(135deg,#166534,#22c55e);
            display:flex;
            align-items:center;
            justify-content:center;
            margin-bottom:1.25rem;
            box-shadow:0 4px 14px rgba(22,101,52,.25);
        }
        .auth-mark svg{
            width:22px;
            height:22px;
            stroke:#fff;
            fill:none;
            stroke-width:2;
            stroke-linecap:round;
            stroke-linejoin:round;
        }

        /* ── Typography ───────────────────────────────────────────────── */
        .auth-h1{
            font-size:1.5rem;
            font-weight:800;
            color:#111827;
            letter-spacing:-.03em;
            margin:0 0 .35rem;
            line-height:1.2;
        }
        .auth-sub{
            font-size:.87rem;
            color:#6b7280;
            margin:0 0 1.25rem;
            line-height:1.5;
        }

        /* ── Info box ─────────────────────────────────────────────────── */
        .auth-infobox{
            display:flex;
            align-items:flex-start;
            gap:.6rem;
            padding:.75rem 1rem;
            border-radius:10px;
            margin-bottom:1.5rem;
            background:#f0fdf4;
            border:1px solid #bbf7d0;
        }
        .auth-infobox svg{
            width:16px;
            height:16px;
            flex-shrink:0;
            margin-top:1px;
            stroke:#16a34a;
            fill:none;
            stroke-width:2;
            stroke-linecap:round;
            stroke-linejoin:round;
        }
        .auth-infobox span{
            font-size:.8rem;
            color:#166534;
            line-height:1.5
        }

        /* ── Submit button ────────────────────────────────────────────── */
        .auth-btn{
            width:100%;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:.5rem;
            padding:.8rem 1.5rem;
            margin-top:1.25rem;
            background:linear-gradient(135deg,#166534,#16a34a);
            color:#fff;
            border:none;
            border-radius:12px;
            cursor:pointer;
            font-family:inherit;
            font-size:.9rem;
            font-weight:700;
            transition:all .2s;
            box-shadow:0 4px 14px rgba(22,101,52,.3);
        }
        .auth-btn:hover{
            transform:translateY(-1px);
            box-shadow:0 8px 24px rgba(22,101,52,.35);
        }
        .auth-btn:active{
            transform:translateY(0)
        }
        .auth-btn:disabled{
            opacity:.6;
            cursor:wait;
            transform:none!important
        }
        .auth-btn svg{
            width:16px;
            height:16px;
            stroke:currentColor;
            fill:none;
            stroke-width:2;
            stroke-linecap:round;
            stroke-linejoin:round;
        }
        .btn-idle{
            display:flex;
            align-items:center;
            gap:.4rem
        }
        .btn-loading{
            display:none;
            align-items:center;
            gap:.4rem
        }

        /* ── Back link ────────────────────────────────────────────────── */
        .auth-back{
            display:inline-flex;
            align-items:center;
            gap:.4rem;
            font-size:.84rem;
            font-weight:600;
            color:#6b7280;
            text-decoration:none;
            transition:color .2s;
            margin-top:1.5rem;
        }
        .auth-back:hover{
            color:#166534
        }
        .auth-back svg{
            width:14px;
            height:14px;
            stroke:currentColor;
            fill:none;
            stroke-width:2.5;
            stroke-linecap:round;
            stroke-linejoin:round;
        }
        .auth-back-center{
            display:flex;
            justify-content:center;
            width:100%
        }

        /* ── Spinner ──────────────────────────────────────────────────── */
        .spin{
            animation:spin .8s linear infinite
        }
        @keyframes spin{
            to{
                transform:rotate(360deg)
            }
        }

        /* ══════════════════════════════════════════════════════════════
           Filament form component overrides
           ══════════════════════════════════════════════════════════════ */

        /* Sections — compact cards */
        .auth-box .fi-section{
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:12px;
            box-shadow:0 1px 3px rgba(0,0,0,.04);
            margin-bottom:.75rem;
        }
        .auth-box .fi-section-header{
            padding:.65rem 1rem!important
        }
        .auth-box .fi-section-header-heading{
            font-size:.82rem;
            font-weight:700;
            color:#374151
        }
        .auth-box .fi-section-header-description{
            font-size:.73rem;
            color:#9ca3af
        }
        .auth-box .fi-section-content{
            padding:.75rem 1rem 1rem!important
        }

        /* Field spacing */
        .auth-box .fi-fo-field-wrp{
            margin-bottom:.15rem
        }

        /* Inputs */
        .auth-box .fi-input-wrp{
            border-radius:10px!important;
            border-color:#d1d5db!important;
            transition:all .2s!important;
        }
        .auth-box .fi-input-wrp:focus-within{
            border-color:#22c55e!important;
            box-shadow:0 0 0 3px rgba(34,197,94,.12)!important;
        }

        /* Checkbox list */
        .auth-box .fi-checkbox-list-option-label{
            font-size:.84rem!important
        }

        /* Display-name placeholder */
        .auth-box .fi-fo-placeholder .fi-fo-placeholder-content{
            font-size:.82rem;
            padding:.45rem .75rem;
            background:#f0fdf4;
            border-radius:8px;
            border:1px solid #dcfce7;
            color:#166534;
            font-weight:600;
        }

        /* ── Responsive ───────────────────────────────────────────────── */
        @media(max-width:900px){
            .auth-shell{
                flex-direction:column;
                height:auto;
                min-height:100vh
            }
            html,body{
                overflow:auto!important
            }
            .auth-hero{
                flex:none;
                min-height:280px
            }
            .hero-content{
                padding:2rem
            }
            .hero-title{
                font-size:1.6rem
            }
            .hero-stats{
                gap:1.5rem
            }
            .hero-benefits{
                display:none
            }
            .auth-right{
                padding:1.5rem 1rem;
                overflow-y:visible
            }
        }
        @media(max-width:600px){
            .hero-content{
                padding:1.5rem
            }
            .hero-title{
                font-size:1.3rem
            }
            .stat-val{
                font-size:1.2rem
            }
            .auth-box{
                padding:1rem 0 2rem
            }
        }
                </style>
</div>