import { useState, useEffect } from "react";
import { T, MENTOR_META } from "../constants.js";
import api from "../services/api.service.js";

const STATUS_MAP = {
    active:    { bg: "#D1FAE5", color: "#065F46", stripe: "#10B981" },
    draft:     { bg: "#FEF3C7", color: "#92400E", stripe: "#F59E0B" },
    completed: { bg: "#F3F4F6", color: "#6B7280", stripe: "#9CA3AF" },
    cancelled: { bg: "#FEE2E2", color: "#991B1B", stripe: "#EF4444" },
};

function MentorshipCard({ m, onOpen }) {
    const s = STATUS_MAP[m.status] ?? STATUS_MAP.draft;
    return (
        <button
            onClick={() => onOpen(m)}
            style={{
                width: "100%", background: T.card, border: `1px solid ${T.border}`,
                borderRadius: T.radius, padding: 0, textAlign: "left",
                cursor: "pointer", boxShadow: T.shadowCard, overflow: "hidden", display: "block",
            }}
        >
            <div style={{ height: 3, background: `linear-gradient(90deg, ${s.stripe}, ${s.stripe}88)` }} />
            <div style={{ padding: "14px 16px" }}>
                {/* Top row: status + program */}
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 8, gap: 8 }}>
                    <span style={{ fontSize: 10, fontWeight: 700, padding: "3px 8px", borderRadius: 20, background: s.bg, color: s.color, flexShrink: 0 }}>
                        {m.status}
                    </span>
                    {m.program && (
                        <span style={{ fontSize: 11, color: T.textSub, background: T.bg, padding: "2px 8px", borderRadius: 10, border: `1px solid ${T.border}` }}>
                            {m.program}
                        </span>
                    )}
                </div>

                {/* Title */}
                <div style={{ fontSize: 15, fontWeight: 700, color: T.text, lineHeight: 1.3, marginBottom: 8 }}>
                    {m.title}
                </div>

                {/* Meta */}
                <div style={{ display: "flex", flexWrap: "wrap", gap: 10, fontSize: 12, color: T.textSub }}>
                    {(m.facility || m.county) && (
                        <span style={{ display: "flex", alignItems: "center", gap: 3 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            {m.facility ?? m.county}
                        </span>
                    )}
                    {m.mentor_name && (
                        <span style={{ display: "flex", alignItems: "center", gap: 3 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            {m.mentor_name}
                        </span>
                    )}
                    {m.start_date && (
                        <span style={{ display: "flex", alignItems: "center", gap: 3 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            {m.start_date}{m.end_date ? ` — ${m.end_date}` : ""}
                        </span>
                    )}
                    <span>{m.class_count} class{m.class_count !== 1 ? "es" : ""}</span>
                </div>
            </div>
        </button>
    );
}

export function MentorshipsListScreen({ user, onOpen, onNew }) {
    const [mentorships, setMentorships] = useState(null);
    const [loading, setLoading]         = useState(true);
    const [error, setError]             = useState(null);

    useEffect(() => {
        api.mentorships.list()
            .then(d => setMentorships(Array.isArray(d?.data) ? d.data : []))
            .catch(e => setError(e.message))
            .finally(() => setLoading(false));
    }, []);

    const all = mentorships ?? [];
    const active    = all.filter(m => m.status === "active");
    const draft     = all.filter(m => m.status === "draft");
    const completed = all.filter(m => m.status === "completed");

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg, position: "relative" }}>
            {/* ── Gradient Hero ── */}
            <div style={{
                background: "linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)",
                padding: "52px 20px 22px",
                borderRadius: "24px 24px 28px 28px",
                position: "relative", overflow: "hidden",
            }}>
                {/* Decorative blobs */}
                <div style={{ position: "absolute", width: 180, height: 180, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -50, right: -50 }} />
                <div style={{ position: "absolute", width: 100, height: 100, borderRadius: "50%", background: "radial-gradient(circle, rgba(129,140,248,0.1) 0%, transparent 70%)", bottom: 0, left: -20 }} />

                <div style={{ color: "white", fontSize: 22, fontWeight: 800, letterSpacing: -0.3, animation: "fadeInUp 0.4s ease both" }}>
                    My Mentorships
                </div>
                <div style={{ color: "rgba(255,255,255,0.45)", fontSize: 13, marginTop: 3, fontWeight: 500, animation: "fadeInUp 0.4s ease 0.05s both" }}>
                    {all.length} total · {active.length} active
                </div>

                {/* Stat pills */}
                <div style={{ display: "flex", gap: 8, marginTop: 16, animation: "fadeInUp 0.4s ease 0.1s both" }}>
                    {[
                        { label: "Active",    count: active.length,    bg: "rgba(129,140,248,0.25)", border: "rgba(165,180,252,0.3)" },
                        { label: "Draft",     count: draft.length,     bg: "rgba(255,255,255,0.08)", border: "rgba(255,255,255,0.12)" },
                        { label: "Completed", count: completed.length, bg: "rgba(255,255,255,0.08)", border: "rgba(255,255,255,0.12)" },
                    ].map(p => (
                        <div key={p.label} style={{
                            flex: 1, padding: "10px 8px", borderRadius: 14, textAlign: "center",
                            background: p.bg, border: `1px solid ${p.border}`,
                            backdropFilter: "blur(6px)",
                        }}>
                            <div style={{ color: "white", fontSize: 18, fontWeight: 800, lineHeight: 1 }}>{p.count}</div>
                            <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 10, fontWeight: 600, marginTop: 3 }}>{p.label}</div>
                        </div>
                    ))}
                </div>
            </div>

            <div style={{ padding: "16px 16px 80px", display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {error && <div style={{ color: "#EF4444", textAlign: "center", paddingTop: 40 }}>{error}</div>}
                {!loading && !error && all.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>No mentorships assigned yet.</div>
                )}
                {all.map(m => <MentorshipCard key={m.id} m={m} onOpen={onOpen} />)}
            </div>

            {onNew && (
                <button onClick={onNew} style={{
                    position: "fixed", bottom: 80, right: 16, zIndex: 10,
                    width: 52, height: 52, borderRadius: "50%",
                    background: "linear-gradient(135deg,#4F46E5,#818CF8)",
                    border: "none", color: "#fff", fontSize: 26, cursor: "pointer",
                    boxShadow: "0 6px 20px rgba(79,70,229,0.4)",
                    display: "flex", alignItems: "center", justifyContent: "center",
                }}>+</button>
            )}
        </div>
    );
}
