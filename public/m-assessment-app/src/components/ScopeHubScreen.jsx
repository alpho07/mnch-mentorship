import { useState, useEffect } from "react";
import api from "../services/api.service.js";

function ScopeCard({ scope, onSelect, index }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => setVisible(true), index * 80);
        return () => clearTimeout(t);
    }, [index]);

    const grad = scope.gradient
        ? `linear-gradient(135deg, ${scope.gradient[0]}, ${scope.gradient[1]})`
        : scope.color;

    const summaryText = () => {
        const s = scope.summary ?? {};
        if (s.in_progress != null)    return `${s.in_progress} in progress · ${s.completed ?? 0} done`;
        if (s.active_classes != null) return `${s.active_classes} active class${s.active_classes !== 1 ? "es" : ""}`;
        if (s.upcoming != null)       return `${s.upcoming} upcoming`;
        return null;
    };

    return (
        <button
            onClick={() => onSelect(scope)}
            style={{
                background: grad,
                border: "none",
                borderRadius: 16,
                padding: "24px 20px",
                cursor: "pointer",
                textAlign: "left",
                opacity: visible ? 1 : 0,
                transform: visible ? "scale(1)" : "scale(0.92)",
                transition: "opacity 0.3s ease, transform 0.3s cubic-bezier(0.34,1.56,0.64,1)",
                boxShadow: "0 4px 20px rgba(0,0,0,0.3)",
                minHeight: 130,
                display: "flex",
                flexDirection: "column",
                justifyContent: "space-between",
            }}
        >
            <span style={{ fontSize: 36 }}>{scope.icon}</span>
            <div>
                <p style={{ color: "white", fontWeight: 700, fontSize: 17, margin: "0 0 4px" }}>
                    {scope.label}
                </p>
                {summaryText() && (
                    <p style={{ color: "rgba(255,255,255,0.75)", fontSize: 12, margin: 0 }}>
                        {summaryText()}
                    </p>
                )}
            </div>
        </button>
    );
}

export function ScopeHubScreen({ scopes, user, onSelect, onLogout }) {
    const [enriched, setEnriched] = useState(scopes);
    const [showProfile, setShowProfile] = useState(false);

    // Fetch live summaries — non-blocking, updates cards after render
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
        <div style={{ minHeight: "100vh", background: "#0f172a", padding: "0 0 32px" }}>
            {/* Header */}
            <div style={{ background: "linear-gradient(135deg, #1e1b4b, #1e293b)", padding: "48px 20px 28px" }}>
                <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between" }}>
                    <div>
                        <p style={{ color: "rgba(255,255,255,0.55)", fontSize: 13, margin: "0 0 4px" }}>
                            {greeting},
                        </p>
                        <p style={{ color: "white", fontWeight: 700, fontSize: 22, margin: "0 0 2px" }}>
                            {user?.name?.split(" ")[0] ?? "there"}
                        </p>
                        <p style={{ color: "rgba(255,255,255,0.35)", fontSize: 12, margin: 0 }}>
                            MNCH Mentorship Platform
                        </p>
                    </div>
                    <button
                        onClick={() => setShowProfile(true)}
                        style={{ background: "rgba(255,255,255,0.15)", border: "none", borderRadius: "50%", width: 42, height: 42, color: "white", fontWeight: 700, fontSize: 14, cursor: "pointer", flexShrink: 0 }}
                    >
                        {user?.initials ?? "?"}
                    </button>
                </div>
            </div>

            {/* Scope cards */}
            <div style={{ padding: "24px 16px 0" }}>
                <p style={{ color: "rgba(255,255,255,0.5)", fontSize: 13, fontWeight: 600, letterSpacing: "0.08em", textTransform: "uppercase", marginBottom: 16 }}>
                    Choose your area
                </p>
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

            {/* Profile sheet */}
            {showProfile && (
                <div
                    style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.6)", zIndex: 200, display: "flex", alignItems: "flex-end" }}
                    onClick={() => setShowProfile(false)}
                >
                    <div
                        style={{ background: "#1e293b", width: "100%", borderRadius: "16px 16px 0 0", padding: "24px 20px 32px" }}
                        onClick={e => e.stopPropagation()}
                    >
                        <p style={{ color: "white", fontWeight: 700, fontSize: 16, marginBottom: 4 }}>{user?.name}</p>
                        <p style={{ color: "rgba(255,255,255,0.45)", fontSize: 13, marginBottom: 28 }}>{user?.email}</p>
                        <button
                            onClick={onLogout}
                            style={{ width: "100%", padding: "13px 0", background: "#ef4444", color: "white", border: "none", borderRadius: 10, fontWeight: 600, fontSize: 15, cursor: "pointer" }}
                        >
                            Log Out
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
