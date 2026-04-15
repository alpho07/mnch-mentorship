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

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg, position: "relative" }}>
            <div style={{ padding: "20px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: T.text }}>{MENTOR_META.icon} Mentorships</div>
                <div style={{ fontSize: 13, color: T.textSub, marginTop: 2 }}>{user?.name ?? ""}</div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {error && <div style={{ color: "#EF4444", textAlign: "center", paddingTop: 40 }}>{error}</div>}
                {!loading && !error && mentorships?.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>No mentorships assigned yet.</div>
                )}
                {(mentorships ?? []).map(m => <MentorshipCard key={m.id} m={m} onOpen={onOpen} />)}
            </div>

            {onNew && (
                <button onClick={onNew} style={{
                    position: "absolute", bottom: 80, right: 16, zIndex: 10,
                    width: 48, height: 48, borderRadius: "50%", background: T.primary,
                    border: "none", color: "#fff", fontSize: 24, cursor: "pointer", boxShadow: T.shadowMd,
                }}>+</button>
            )}
        </div>
    );
}
