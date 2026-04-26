import { useState, useEffect } from "react";
import { T } from "../constants.js";
import { getScopesFromCache, cacheScopeConfig } from "../scope-config.js";
import { ScopeHubScreen } from "./ScopeHubScreen.jsx";
import { AssessmentsScope } from "../scopes/AssessmentsScope.jsx";
import { MentorshipsScope } from "../scopes/MentorshipsScope.jsx";
import { TrainingsScope }   from "../scopes/TrainingsScope.jsx";

// ── Scope header (shown inside any active scope) ──────────────────────────────
function ScopeHeader({ scope, onSwitch, showSwitch, user, onLogout }) {
    const [showProfile, setShowProfile] = useState(false);

    return (
        <>
            <div style={{
                position: "sticky", top: 0, zIndex: 100,
                background: scope.gradient
                    ? `linear-gradient(135deg, ${scope.gradient[0]}, ${scope.gradient[1]})`
                    : scope.color ?? "#6366F1",
                padding: "12px 16px",
                display: "flex", alignItems: "center", gap: 12,
            }}>
                {showSwitch && (
                    <button
                        onClick={onSwitch}
                        style={{ background: "rgba(255,255,255,0.15)", border: "none", borderRadius: 8, padding: "6px 8px", cursor: "pointer", color: "white", fontSize: 18, lineHeight: 1 }}
                        aria-label="Switch scope"
                    >
                        ⊞
                    </button>
                )}
                <span style={{ flex: 1, color: "white", fontWeight: 700, fontSize: 16 }}>
                    {scope.icon} {scope.label}
                </span>
                <button
                    onClick={() => setShowProfile(true)}
                    style={{ background: "rgba(255,255,255,0.2)", border: "none", borderRadius: "50%", width: 36, height: 36, cursor: "pointer", color: "white", fontWeight: 700, fontSize: 13 }}
                >
                    {user?.initials ?? "?"}
                </button>
            </div>

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
                            <div style={{ width: 48, height: 48, borderRadius: "50%", background: T.gradientPrimary, display: "flex", alignItems: "center", justifyContent: "center", color: "white", fontWeight: 700, fontSize: 16, flexShrink: 0 }}>
                                {user?.initials ?? "?"}
                            </div>
                            <div>
                                <p style={{ color: T.text, fontWeight: 700, fontSize: 15, margin: "0 0 2px" }}>{user?.name}</p>
                                <p style={{ color: T.textSub, fontSize: 13, margin: 0 }}>{user?.email}</p>
                            </div>
                        </div>
                        <button
                            onClick={onLogout}
                            style={{ width: "100%", padding: "13px 0", background: "linear-gradient(135deg, #EF4444, #DC2626)", color: "white", border: "none", borderRadius: 14, fontWeight: 700, fontSize: 15, cursor: "pointer", boxShadow: "0 4px 16px rgba(239,68,68,0.22)" }}
                        >
                            Log Out
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}

// ── ScopeShell ────────────────────────────────────────────────────────────────
export function ScopeShell({ user, onLogout, onUserUpdate }) {
    const [scopes, setScopes]           = useState([]);
    const [activeScope, setActiveScope] = useState(null);
    const [ready, setReady]             = useState(false);

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
        if (resolved.length === 1) {
            setActiveScope(resolved[0]);
        }
        setReady(true);
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
                <div style={{
                    width: 72, height: 72, borderRadius: 22, marginBottom: 20,
                    background: T.primaryGhost, border: `2px solid ${T.border}`,
                    display: "flex", alignItems: "center", justifyContent: "center",
                }}>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="1.8" strokeLinecap="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" />
                        <path d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>
                </div>
                <p style={{ color: T.text, fontWeight: 700, fontSize: 18, marginBottom: 8 }}>No areas configured</p>
                <p style={{ color: T.textSub, fontSize: 14, marginBottom: 32 }}>Contact your administrator to get access.</p>
                <button onClick={onLogout} style={{ padding: "12px 28px", background: T.gradientPrimary, color: "white", border: "none", borderRadius: 12, fontWeight: 600, fontSize: 15, cursor: "pointer", boxShadow: `0 4px 16px ${T.primaryGlow}` }}>Log Out</button>
            </div>
        );
    }

    const showSwitch = scopes.length > 1;

    if (!activeScope) {
        return (
            <ScopeHubScreen
                scopes={scopes}
                user={user}
                onSelect={setActiveScope}
                onLogout={onLogout}
            />
        );
    }

    const scopeProps = {
        user,
        scope: activeScope,
        onLogout,
        onUserUpdate,
        header: (
            <ScopeHeader
                scope={activeScope}
                onSwitch={() => setActiveScope(null)}
                showSwitch={showSwitch}
                user={user}
                onLogout={onLogout}
            />
        ),
    };

    return (
        <>
            {activeScope.id === "assessments" && <AssessmentsScope  {...scopeProps} />}
            {activeScope.id === "mentorships" && <MentorshipsScope  {...scopeProps} />}
            {activeScope.id === "trainings"   && <TrainingsScope    {...scopeProps} />}
        </>
    );
}
