import { useState, useEffect, useMemo } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const STATUS_MAP = {
    active:    { bg: "#D1FAE5", color: "#065F46", stripe: "#10B981" },
    draft:     { bg: "#FEF3C7", color: "#92400E", stripe: "#F59E0B" },
    completed: { bg: "#F3F4F6", color: "#6B7280", stripe: "#9CA3AF" },
    cancelled: { bg: "#FEE2E2", color: "#991B1B", stripe: "#EF4444" },
};

const FILTER_TABS = [
    { key: "all",       label: "All" },
    { key: "active",    label: "Active" },
    { key: "draft",     label: "Draft" },
    { key: "completed", label: "Completed" },
];

function MentorshipCard({ m, onOpen, onEdit }) {
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
                {/* Top row: status + program + edit */}
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 8, gap: 8 }}>
                    <span style={{ fontSize: 10, fontWeight: 700, padding: "3px 8px", borderRadius: 20, background: s.bg, color: s.color, flexShrink: 0 }}>
                        {m.status}
                    </span>
                    <div style={{ display: "flex", alignItems: "center", gap: 6, flex: 1, justifyContent: "flex-end" }}>
                        {m.program && (
                            <span style={{ fontSize: 11, color: T.textSub, background: T.bg, padding: "2px 8px", borderRadius: 10, border: `1px solid ${T.border}` }}>
                                {m.program}
                            </span>
                        )}
                        {onEdit && (
                            <button
                                onClick={(e) => { e.stopPropagation(); onEdit(m); }}
                                style={{
                                    padding: "4px 8px", borderRadius: 8, border: `1px solid ${T.border}`,
                                    background: T.bg, cursor: "pointer", display: "flex", alignItems: "center",
                                    flexShrink: 0,
                                }}
                            >
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke={T.textSub} strokeWidth="2.5">
                                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                        )}
                    </div>
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

export function MentorshipsListScreen({ user, onOpen, onNew, onEdit }) {
    const [mentorships, setMentorships] = useState(null);
    const [loading, setLoading]         = useState(true);
    const [error, setError]             = useState(null);
    const [search, setSearch]           = useState("");
    const [activeTab, setActiveTab]     = useState("all");

    useEffect(() => {
        api.mentorships.list()
            .then(d => setMentorships(Array.isArray(d?.data) ? d.data : []))
            .catch(e => setError(e.message))
            .finally(() => setLoading(false));
    }, []);

    const all = mentorships ?? [];

    const counts = useMemo(() => ({
        all:       all.length,
        active:    all.filter(m => m.status === "active").length,
        draft:     all.filter(m => m.status === "draft").length,
        completed: all.filter(m => m.status === "completed").length,
    }), [all]);

    const displayed = useMemo(() => {
        const q = search.trim().toLowerCase();
        let result = q
            ? all.filter(m =>
                (m.title ?? "").toLowerCase().includes(q) ||
                (m.program ?? "").toLowerCase().includes(q) ||
                (m.facility ?? "").toLowerCase().includes(q) ||
                (m.county ?? "").toLowerCase().includes(q) ||
                (m.mentor_name ?? "").toLowerCase().includes(q)
              )
            : all;
        if (activeTab !== "all") result = result.filter(m => m.status === activeTab);
        return result;
    }, [all, search, activeTab]);

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg, position: "relative" }}>
            {/* ── Gradient Hero ── */}
            <div style={{ height: 6, background: T.bg }} />
            <div style={{
                background: T.gradientHero,
                padding: "28px 20px 22px",
                borderRadius: "0 0 28px 28px",
                margin: "0 6px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 180, height: 180, borderRadius: "50%", background: "radial-gradient(circle, rgba(38,198,218,0.15) 0%, transparent 70%)", top: -50, right: -50 }} />
                <div style={{ position: "absolute", width: 100, height: 100, borderRadius: "50%", background: "radial-gradient(circle, rgba(0,151,167,0.12) 0%, transparent 70%)", bottom: 0, left: -20 }} />

                <div style={{ color: "white", fontSize: 22, fontWeight: 800, letterSpacing: -0.3, animation: "fadeInUp 0.4s ease both" }}>
                    My Mentorships
                </div>
                <div style={{ color: "rgba(255,255,255,0.45)", fontSize: 13, marginTop: 3, fontWeight: 500, animation: "fadeInUp 0.4s ease 0.05s both" }}>
                    {counts.all} total · {counts.active} active
                </div>

                <div style={{ display: "flex", gap: 8, marginTop: 16, animation: "fadeInUp 0.4s ease 0.1s both" }}>
                    {[
                        { label: "Active",    count: counts.active,    bg: "rgba(38,198,218,0.25)", border: "rgba(0,151,167,0.3)" },
                        { label: "Draft",     count: counts.draft,     bg: "rgba(255,255,255,0.08)", border: "rgba(255,255,255,0.12)" },
                        { label: "Completed", count: counts.completed, bg: "rgba(255,255,255,0.08)", border: "rgba(255,255,255,0.12)" },
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

            {/* ── Search ── */}
            <div style={{ padding: "12px 16px 0" }}>
                <div style={{ position: "relative" }}>
                    <input
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search by title, program, facility, county…"
                        style={{
                            width: "100%", padding: "10px 36px 10px 38px",
                            borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
                            fontSize: 14, background: T.card, color: T.text,
                            boxSizing: "border-box", outline: "none", fontFamily: "inherit",
                        }}
                    />
                    <svg style={{ position: "absolute", left: 12, top: "50%", transform: "translateY(-50%)", pointerEvents: "none" }}
                        width="15" height="15" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    {search && (
                        <button onClick={() => setSearch("")} style={{ position: "absolute", right: 10, top: "50%", transform: "translateY(-50%)", background: "none", border: "none", cursor: "pointer", color: T.textMuted, fontSize: 18, lineHeight: 1, padding: 2 }}>×</button>
                    )}
                </div>
            </div>

            {/* ── Filter Tabs ── */}
            <div style={{ display: "flex", gap: 6, padding: "10px 16px 0", overflowX: "auto" }}>
                {FILTER_TABS.map(tab => {
                    const isActive = activeTab === tab.key;
                    const count = counts[tab.key];
                    return (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            style={{
                                padding: "7px 14px", borderRadius: 20, cursor: "pointer", flexShrink: 0,
                                border: isActive ? "none" : `1px solid ${T.border}`,
                                background: isActive ? T.primary : T.card,
                                color: isActive ? "#fff" : T.textSub,
                                fontWeight: isActive ? 700 : 500, fontSize: 13,
                                fontFamily: "inherit",
                                boxShadow: isActive ? `0 2px 8px ${T.primaryGlow}` : "none",
                            }}
                        >
                            {tab.label}
                            {count > 0 && (
                                <span style={{
                                    marginLeft: 6, fontSize: 11, fontWeight: 700,
                                    background: isActive ? "rgba(255,255,255,0.25)" : T.bg,
                                    color: isActive ? "#fff" : T.textSub,
                                    padding: "1px 6px", borderRadius: 10,
                                }}>
                                    {count}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {/* ── List ── */}
            <div style={{ padding: "12px 16px 80px", display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {error   && <div style={{ color: "#EF4444", textAlign: "center", paddingTop: 40 }}>{error}</div>}
                {!loading && !error && all.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>No mentorships assigned yet.</div>
                )}
                {!loading && !error && all.length > 0 && displayed.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>
                        {search ? "No mentorships match your search." : `No ${activeTab} mentorships.`}
                    </div>
                )}
                {displayed.map(m => <MentorshipCard key={m.id} m={m} onOpen={onOpen} onEdit={onEdit} />)}
            </div>

            {onNew && (
                <button onClick={onNew} style={{
                    position: "fixed", bottom: 80, right: 16, zIndex: 10,
                    width: 52, height: 52, borderRadius: "50%",
                    background: T.gradientPrimary,
                    border: "none", color: "#fff", fontSize: 26, cursor: "pointer",
                    boxShadow: `0 6px 20px ${T.primaryGlow}`,
                    display: "flex", alignItems: "center", justifyContent: "center",
                }}>+</button>
            )}
        </div>
    );
}
