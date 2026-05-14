import { useState, useEffect } from "react";
import { T } from "../constants.js";
import { getScopesFromCache, cacheScopeConfig } from "../scope-config.js";
import { AssessmentsScope } from "../scopes/AssessmentsScope.jsx";
import { MentorshipsScope } from "../scopes/MentorshipsScope.jsx";
import { TrainingsScope }   from "../scopes/TrainingsScope.jsx";

const SCOPE_COMPONENTS = {
    assessments: AssessmentsScope,
    mentorships: MentorshipsScope,
    trainings:   TrainingsScope,
};

function ScopeIcon({ id, size = 20 }) {
    const w = "1.8";
    if (id === "assessments") return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" />
            <rect x="9" y="3" width="6" height="4" rx="1" />
            <path d="M9 12l2 2 4-4" /><line x1="9" y1="17" x2="13" y2="17" />
        </svg>
    );
    if (id === "mentorships") return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">
            <circle cx="8" cy="7" r="3" /><circle cx="17" cy="7" r="3" />
            <path d="M2 21v-2a4 4 0 014-4h5" /><path d="M17 14l2 2 3-3" />
        </svg>
    );
    if (id === "trainings") return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
            <path d="M6 12v5c3 3 9 3 12 0v-5" />
        </svg>
    );
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w}>
            <circle cx="12" cy="12" r="8" /><path d="M12 8v4l3 3" strokeLinecap="round" />
        </svg>
    );
}

function headerGradient(scope) {
    if (scope?.gradient?.length === 2) {
        return `linear-gradient(135deg, ${scope.gradient[0]}, ${scope.gradient[1]})`;
    }
    return scope?.color ?? "#0097A7";
}

export function ScopeShell({ user, onLogout, onUserUpdate }) {
    const [scopes, setScopes]           = useState([]);
    const [activeIdx, setActiveIdx]     = useState(0);
    const [ready, setReady]             = useState(false);
    const [showProfile, setShowProfile] = useState(false);
    const [animating, setAnimating]     = useState(false);

    useEffect(() => {
        const userScopes = user?.scopes;
        if (Array.isArray(userScopes) && userScopes.length > 0) {
            cacheScopeConfig(userScopes);
            applyScopes(userScopes);
        } else {
            getScopesFromCache().then(applyScopes);
        }
    }, [user?.id]);

    function applyScopes(resolved) {
        setScopes(resolved);
        setReady(true);
    }

    function switchTab(idx) {
        if (idx === activeIdx || animating) return;
        setAnimating(true);
        setActiveIdx(idx);
        setTimeout(() => setAnimating(false), 400);
    }

    if (!ready) {
        return (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", background: T.bg }}>
                <div style={{ color: T.textSub, fontSize: 14 }}>Loading…</div>
            </div>
        );
    }

    if (scopes.length === 0) {
        return (
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", height: "100vh", background: T.bg, padding: 32, textAlign: "center" }}>
                <div style={{ width: 72, height: 72, borderRadius: 22, marginBottom: 20, background: T.primaryGhost, border: `2px solid ${T.border}`, display: "flex", alignItems: "center", justifyContent: "center" }}>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="1.8" strokeLinecap="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" />
                        <path d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>
                </div>
                <p style={{ color: T.text, fontWeight: 700, fontSize: 18, marginBottom: 8 }}>No areas configured</p>
                <p style={{ color: T.textSub, fontSize: 14, marginBottom: 32 }}>Contact your administrator to get access.</p>
                <button onClick={onLogout} style={{ padding: "12px 28px", background: T.gradientPrimary, color: "white", border: "none", borderRadius: 12, fontWeight: 600, fontSize: 15, cursor: "pointer", boxShadow: `0 4px 16px ${T.primaryGlow}` }}>
                    Log Out
                </button>
            </div>
        );
    }

    const activeScope    = scopes[activeIdx] ?? scopes[0];
    const showTabs       = scopes.length > 1;
    const trackTranslate = `translateX(calc(-${activeIdx} * 100% / ${scopes.length}))`;
    const trackWidth     = `${scopes.length * 100}%`;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100vh", overflow: "hidden" }}>

            {/* ── Sticky header ── */}
            <div style={{
                flexShrink: 0,
                background: headerGradient(activeScope),
                transition: "background 0.35s ease",
                zIndex: 100,
            }}>
                {/* Title row */}
                <div style={{ padding: "14px 16px 0", display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                        <ScopeIcon id={activeScope.id} size={20} />
                        <span style={{ color: "white", fontWeight: 800, fontSize: 17, letterSpacing: -0.3 }}>
                            MNCH Kenya
                        </span>
                    </div>
                    <button
                        onClick={() => setShowProfile(true)}
                        style={{
                            background: "rgba(255,255,255,0.2)",
                            border: "1px solid rgba(255,255,255,0.25)",
                            borderRadius: "50%",
                            width: 36, height: 36,
                            color: "white", fontWeight: 700, fontSize: 13,
                            cursor: "pointer",
                            display: "flex", alignItems: "center", justifyContent: "center",
                        }}
                    >
                        {user?.initials ?? "?"}
                    </button>
                </div>

                {/* Segmented control — hidden when only one scope */}
                {showTabs && (
                    <div style={{ padding: "10px 14px 12px" }}>
                        <div style={{
                            display: "flex",
                            background: "rgba(255,255,255,0.18)",
                            borderRadius: 12,
                            padding: 3,
                            gap: 3,
                        }}>
                            {scopes.map((scope, idx) => {
                                const isActive = idx === activeIdx;
                                return (
                                    <button
                                        key={scope.id}
                                        onClick={() => switchTab(idx)}
                                        style={{
                                            flex: 1,
                                            padding: "8px 4px",
                                            border: "none",
                                            borderRadius: 10,
                                            cursor: "pointer",
                                            fontWeight: 700,
                                            fontSize: 12,
                                            transition: "all 0.25s ease",
                                            background: isActive ? "white" : "transparent",
                                            color: isActive ? (activeScope.color ?? "#0097A7") : "rgba(255,255,255,0.65)",
                                        }}
                                    >
                                        {scope.label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}
                {!showTabs && <div style={{ height: 12 }} />}
            </div>

            {/* ── Carousel ── */}
            <div style={{ flex: 1, overflow: "hidden", position: "relative" }}>
                <div style={{
                    display: "flex",
                    width: trackWidth,
                    height: "100%",
                    transform: trackTranslate,
                    transition: "transform 0.38s cubic-bezier(0.4, 0, 0.2, 1)",
                }}>
                    {scopes.map((scope) => {
                        const ScopeComponent = SCOPE_COMPONENTS[scope.id];
                        if (!ScopeComponent) return (
                            <div key={scope.id} style={{ width: `calc(100% / ${scopes.length})`, flexShrink: 0, display: "flex", alignItems: "center", justifyContent: "center" }}>
                                <p style={{ color: T.textSub }}>Unknown scope: {scope.id}</p>
                            </div>
                        );
                        return (
                            <div key={scope.id} style={{ width: `calc(100% / ${scopes.length})`, flexShrink: 0, overflowY: "auto", height: "100%" }}>
                                <ScopeComponent
                                    user={user}
                                    scope={scope}
                                    onLogout={onLogout}
                                    onUserUpdate={onUserUpdate}
                                />
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* ── Profile bottom sheet ── */}
            {showProfile && (
                <div
                    style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.4)", zIndex: 200, display: "flex", alignItems: "flex-end" }}
                    onClick={() => setShowProfile(false)}
                >
                    <div
                        style={{ background: "white", width: "100%", borderRadius: "20px 20px 0 0", padding: "8px 20px 40px", boxShadow: "0 -8px 40px rgba(0,0,0,0.12)" }}
                        onClick={e => e.stopPropagation()}
                    >
                        <div style={{ width: 40, height: 4, borderRadius: 2, background: T.border, margin: "12px auto 20px" }} />
                        <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 24 }}>
                            <div style={{ width: 50, height: 50, borderRadius: "50%", background: T.gradientPrimary, display: "flex", alignItems: "center", justifyContent: "center", color: "white", fontWeight: 700, fontSize: 17, flexShrink: 0 }}>
                                {user?.initials ?? "?"}
                            </div>
                            <div>
                                <p style={{ color: T.text, fontWeight: 700, fontSize: 16, margin: "0 0 2px" }}>{user?.name}</p>
                                <p style={{ color: T.textSub, fontSize: 13, margin: 0 }}>{user?.email}</p>
                            </div>
                        </div>
                        <button
                            onClick={onLogout}
                            style={{ width: "100%", padding: "14px 0", background: "linear-gradient(135deg, #EF4444 0%, #DC2626 100%)", color: "white", border: "none", borderRadius: 14, fontWeight: 700, fontSize: 15, cursor: "pointer", boxShadow: "0 4px 16px rgba(239,68,68,0.22)", letterSpacing: 0.2 }}
                        >
                            Log Out
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
