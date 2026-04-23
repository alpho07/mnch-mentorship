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
                    style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.5)", zIndex: 200, display: "flex", alignItems: "flex-end" }}
                    onClick={() => setShowProfile(false)}
                >
                    <div
                        style={{ background: "#1e293b", width: "100%", borderRadius: "16px 16px 0 0", padding: 24 }}
                        onClick={e => e.stopPropagation()}
                    >
                        <p style={{ color: "white", fontWeight: 700, marginBottom: 4 }}>{user?.name}</p>
                        <p style={{ color: "rgba(255,255,255,0.5)", fontSize: 13, marginBottom: 24 }}>{user?.email}</p>
                        <button
                            onClick={onLogout}
                            style={{ width: "100%", padding: "12px 0", background: "#ef4444", color: "white", border: "none", borderRadius: 10, fontWeight: 600, cursor: "pointer" }}
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
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", background: "#0f172a" }}>
                <div style={{ color: "white", fontSize: 14, opacity: 0.6 }}>Loading…</div>
            </div>
        );
    }

    if (scopes.length === 0) {
        return (
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", height: "100vh", background: "#0f172a", padding: 32, textAlign: "center" }}>
                <div style={{ fontSize: 48, marginBottom: 16 }}>🔒</div>
                <p style={{ color: "white", fontWeight: 700, fontSize: 18, marginBottom: 8 }}>No areas configured</p>
                <p style={{ color: "rgba(255,255,255,0.5)", fontSize: 14, marginBottom: 32 }}>Contact your administrator to get access.</p>
                <button onClick={onLogout} style={{ padding: "10px 24px", background: "#374151", color: "white", border: "none", borderRadius: 8, cursor: "pointer" }}>Log Out</button>
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
