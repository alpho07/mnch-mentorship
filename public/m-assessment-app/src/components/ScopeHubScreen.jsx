import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

// SVG icons per scope id — all white stroke for use on colored cards
function ScopeIcon({ id, size = 24 }) {
    const w = "1.8";
    if (id === "assessments") return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" />
            <rect x="9" y="3" width="6" height="4" rx="1" />
            <path d="M9 12l2 2 4-4" />
            <line x1="9" y1="17" x2="13" y2="17" />
        </svg>
    );
    if (id === "mentorships") return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">
            <circle cx="8" cy="7" r="3" />
            <circle cx="17" cy="7" r="3" />
            <path d="M2 21v-2a4 4 0 014-4h5" />
            <path d="M17 14l2 2 3-3" />
            <path d="M14 21v-1.5A3.5 3.5 0 0010.5 16" />
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
            <circle cx="12" cy="12" r="8" />
            <path d="M12 8v4l3 3" strokeLinecap="round" />
        </svg>
    );
}

function ScopeCard({ scope, onSelect, index }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => setVisible(true), index * 90);
        return () => clearTimeout(t);
    }, [index]);

    const grad = scope.gradient
        ? `linear-gradient(140deg, ${scope.gradient[0]}, ${scope.gradient[1]})`
        : scope.color ?? T.gradientPrimary;

    const summaryText = () => {
        const s = scope.summary ?? {};
        if (s.in_progress != null)    return `${s.in_progress} in progress · ${s.completed ?? 0} done`;
        if (s.active_classes != null) return `${s.active_classes} active class${s.active_classes !== 1 ? "es" : ""}`;
        if (s.upcoming != null)       return `${s.upcoming} upcoming`;
        return null;
    };
    const summary = summaryText();

    return (
        <button
            onClick={() => onSelect(scope)}
            style={{
                background: grad,
                border: "none",
                borderRadius: 20,
                padding: "20px 18px",
                cursor: "pointer",
                textAlign: "left",
                opacity: visible ? 1 : 0,
                transform: visible ? "translateY(0) scale(1)" : "translateY(18px) scale(0.95)",
                transition: "opacity 0.4s ease, transform 0.4s cubic-bezier(0.34,1.4,0.64,1)",
                boxShadow: "0 6px 24px rgba(0,0,0,0.13), 0 2px 6px rgba(0,0,0,0.07)",
                minHeight: 148,
                display: "flex",
                flexDirection: "column",
                justifyContent: "space-between",
                position: "relative",
                overflow: "hidden",
            }}
        >
            {/* Decorative glow in top-right */}
            <div style={{
                position: "absolute", top: -30, right: -30,
                width: 100, height: 100, borderRadius: "50%",
                background: "radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 65%)",
                pointerEvents: "none",
            }} />
            {/* Bottom-left shimmer */}
            <div style={{
                position: "absolute", bottom: -20, left: -20,
                width: 70, height: 70, borderRadius: "50%",
                background: "radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%)",
                pointerEvents: "none",
            }} />

            {/* Icon */}
            <div style={{
                width: 48, height: 48, borderRadius: 14,
                background: "rgba(255,255,255,0.2)",
                border: "1px solid rgba(255,255,255,0.28)",
                display: "flex", alignItems: "center", justifyContent: "center",
                backdropFilter: "blur(4px)",
            }}>
                <ScopeIcon id={scope.id} size={24} />
            </div>

            <div>
                <p style={{ color: "white", fontWeight: 800, fontSize: 16, margin: "0 0 5px", letterSpacing: -0.3 }}>
                    {scope.label}
                </p>
                {summary ? (
                    <p style={{ color: "rgba(255,255,255,0.72)", fontSize: 12, margin: 0, fontWeight: 500 }}>
                        {summary}
                    </p>
                ) : (
                    <p style={{ color: "rgba(255,255,255,0.48)", fontSize: 11, margin: 0, fontWeight: 500 }}>
                        Tap to open →
                    </p>
                )}
            </div>
        </button>
    );
}

export function ScopeHubScreen({ scopes, user, onSelect, onLogout }) {
    const [enriched, setEnriched] = useState(scopes);
    const [showProfile, setShowProfile] = useState(false);

    useEffect(() => {
        api.scopes?.getConfig?.()
            .then(data => {
                if (Array.isArray(data?.scopes)) setEnriched(data.scopes);
            })
            .catch(() => {});
    }, []);

    const hour = new Date().getHours();
    const greeting = hour < 12 ? "Good morning" : hour < 17 ? "Good afternoon" : "Good evening";

    const rows = [];
    for (let i = 0; i < enriched.length; i += 2) {
        rows.push(enriched.slice(i, i + 2));
    }

    return (
        <div style={{ minHeight: "100vh", background: T.bg, paddingBottom: 40 }}>
            <style>{`
                @keyframes hubFadeDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
            `}</style>

            {/* Status bar gap */}
            <div style={{ height: 6, background: T.bg }} />

            {/* Hero header */}
            <div style={{
                background: T.gradientHero,
                padding: "28px 20px 36px",
                borderRadius: "0 0 28px 28px",
                margin: "0 6px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 200, height: 200, borderRadius: "50%", background: "radial-gradient(circle, rgba(38,198,218,0.18) 0%, transparent 70%)", top: -60, right: -50, pointerEvents: "none" }} />
                <div style={{ position: "absolute", width: 110, height: 110, borderRadius: "50%", background: "radial-gradient(circle, rgba(0,151,167,0.15) 0%, transparent 70%)", bottom: -30, left: -10, pointerEvents: "none" }} />

                <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between" }}>
                    <div style={{ animation: "hubFadeDown 0.4s ease both" }}>
                        <p style={{ color: "rgba(255,255,255,0.58)", fontSize: 13, margin: "0 0 2px", fontWeight: 500 }}>
                            {greeting},
                        </p>
                        <p style={{ color: "white", fontWeight: 800, fontSize: 24, margin: "0 0 3px", letterSpacing: -0.5 }}>
                            {user?.name?.split(" ")[0] ?? "there"}
                        </p>
                        <p style={{ color: "rgba(255,255,255,0.38)", fontSize: 12, margin: 0 }}>
                            MNCH Mentorship Platform
                        </p>
                    </div>
                    <button
                        onClick={() => setShowProfile(true)}
                        style={{
                            background: "rgba(255,255,255,0.15)",
                            border: "1px solid rgba(255,255,255,0.22)",
                            borderRadius: "50%",
                            width: 44, height: 44,
                            color: "white", fontWeight: 700, fontSize: 15,
                            cursor: "pointer", flexShrink: 0,
                            backdropFilter: "blur(8px)",
                            display: "flex", alignItems: "center", justifyContent: "center",
                        }}
                    >
                        {user?.initials ?? "?"}
                    </button>
                </div>
            </div>

            {/* Section label + cards */}
            <div style={{ padding: "24px 16px 0" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 16 }}>
                    <div style={{ width: 3, height: 16, borderRadius: 2, background: T.gradientPrimary }} />
                    <p style={{ color: T.textSub, fontSize: 12, fontWeight: 700, letterSpacing: "0.08em", textTransform: "uppercase", margin: 0 }}>
                        Choose your area
                    </p>
                </div>
                <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
                    {rows.map((row, ri) => (
                        <div key={ri} style={{ display: "grid", gridTemplateColumns: row.length === 1 ? "1fr" : "1fr 1fr", gap: 12 }}>
                            {row.map((scope, si) => (
                                <ScopeCard
                                    key={scope.id}
                                    scope={scope}
                                    onSelect={onSelect}
                                    index={ri * 2 + si}
                                />
                            ))}
                        </div>
                    ))}
                </div>
            </div>

            {/* Profile bottom sheet */}
            {showProfile && (
                <div
                    style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.4)", zIndex: 200, display: "flex", alignItems: "flex-end" }}
                    onClick={() => setShowProfile(false)}
                >
                    <div
                        style={{
                            background: "white",
                            width: "100%",
                            borderRadius: "20px 20px 0 0",
                            padding: "8px 20px 40px",
                            boxShadow: "0 -8px 40px rgba(0,0,0,0.12)",
                        }}
                        onClick={e => e.stopPropagation()}
                    >
                        {/* Drag handle */}
                        <div style={{ width: 40, height: 4, borderRadius: 2, background: T.border, margin: "12px auto 20px" }} />

                        <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 24 }}>
                            <div style={{
                                width: 50, height: 50, borderRadius: "50%",
                                background: T.gradientPrimary,
                                display: "flex", alignItems: "center", justifyContent: "center",
                                color: "white", fontWeight: 700, fontSize: 17,
                                flexShrink: 0,
                            }}>
                                {user?.initials ?? "?"}
                            </div>
                            <div>
                                <p style={{ color: T.text, fontWeight: 700, fontSize: 16, margin: "0 0 2px" }}>{user?.name}</p>
                                <p style={{ color: T.textSub, fontSize: 13, margin: 0 }}>{user?.email}</p>
                            </div>
                        </div>

                        <button
                            onClick={onLogout}
                            style={{
                                width: "100%", padding: "14px 0",
                                background: "linear-gradient(135deg, #EF4444 0%, #DC2626 100%)",
                                color: "white", border: "none", borderRadius: 14,
                                fontWeight: 700, fontSize: 15, cursor: "pointer",
                                boxShadow: "0 4px 16px rgba(239,68,68,0.22)",
                                letterSpacing: 0.2,
                            }}
                        >
                            Log Out
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
