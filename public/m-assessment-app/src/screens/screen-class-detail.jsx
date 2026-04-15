// src/screens/screen-class-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const STATUS_STYLE = {
    not_started: { bg: "#F3F4F6", color: "#6B7280" },
    in_progress:  { bg: "#FEF3C7", color: "#92400E" },
    completed:    { bg: "#D1FAE5", color: "#065F46" },
    draft:        { bg: "#F3F4F6", color: "#6B7280" },
    active:       { bg: "#DBEAFE", color: "#1E40AF" },
    cancelled:    { bg: "#FEE2E2", color: "#991B1B" },
};

function StatusBadge({ status }) {
    const s = STATUS_STYLE[status] ?? STATUS_STYLE.not_started;
    return (
        <span style={{ fontSize: 11, fontWeight: 700, padding: "2px 8px", borderRadius: 6, background: s.bg, color: s.color, flexShrink: 0 }}>
            {status?.replace(/_/g, " ")}
        </span>
    );
}

export function ClassDetailScreen({ cls, onBack, onOpenModule }) {
    const [detail, setDetail]   = useState(null);
    const [loading, setLoading] = useState(true);
    const [tab, setTab]         = useState("modules"); // "modules" | "mentees"

    useEffect(() => {
        // Load full class detail (has mentees + modules)
        const trainingId = cls.trainingId;
        const classId    = cls.id;

        if (trainingId) {
            api.mentorships.classDetail(trainingId, classId)
                .then(d => setDetail(d?.data ?? null))
                .catch(() => {})
                .finally(() => setLoading(false));
        } else {
            // Fallback: just load modules
            api.modules.list(classId)
                .then(d => setDetail({ ...cls, modules: Array.isArray(d?.data) ? d.data : [] }))
                .catch(() => setDetail(cls))
                .finally(() => setLoading(false));
        }
    }, [cls.id]);

    const data    = detail ?? cls;
    const modules = data?.modules ?? [];
    const mentees = data?.mentees ?? [];

    const pct = data?.progress_percentage ?? 0;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{data.name}</div>
                    <div style={{ display: "flex", gap: 8, alignItems: "center", marginTop: 2, flexWrap: "wrap" }}>
                        <StatusBadge status={data.status} />
                        <span style={{ fontSize: 12, color: T.textSub }}>
                            {data.participant_count ?? mentees.length} mentees · {modules.length} modules
                        </span>
                    </div>
                </div>
            </div>

            {/* Progress bar */}
            <div style={{ background: T.card, padding: "10px 20px", borderBottom: `1px solid ${T.border}` }}>
                <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 4 }}>
                    <span style={{ fontSize: 11, color: T.textSub }}>Progress</span>
                    <span style={{ fontSize: 11, fontWeight: 700, color: pct === 100 ? "#10B981" : T.primary }}>{pct}%</span>
                </div>
                <div style={{ height: 6, borderRadius: 4, background: T.borderLight, overflow: "hidden" }}>
                    <div style={{ height: "100%", width: `${pct}%`, background: pct === 100 ? "#10B981" : T.gradientPrimary, borderRadius: 4, transition: "width 0.6s" }} />
                </div>
                {(data.start_date || data.end_date) && (
                    <div style={{ fontSize: 11, color: T.textMuted, marginTop: 5 }}>
                        {data.start_date ?? "—"} → {data.end_date ?? "—"}
                    </div>
                )}
            </div>

            {/* Tabs */}
            <div style={{ display: "flex", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                {["modules", "mentees"].map(t => (
                    <button key={t} onClick={() => setTab(t)} style={{
                        flex: 1, padding: "10px 0", border: "none", background: "none",
                        fontSize: 13, fontWeight: tab === t ? 700 : 500,
                        color: tab === t ? T.primary : T.textSub, cursor: "pointer",
                        borderBottom: tab === t ? `2px solid ${T.primary}` : "2px solid transparent",
                    }}>
                        {t === "modules" ? `Modules (${modules.length})` : `Mentees (${mentees.length || (data.participant_count ?? 0)})`}
                    </button>
                ))}
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}

                {/* Modules tab */}
                {!loading && tab === "modules" && (
                    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                        {modules.length === 0 && (
                            <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>No modules.</div>
                        )}
                        {modules.map(m => (
                            <button
                                key={m.id}
                                onClick={() => onOpenModule({ ...m, classId: cls.id })}
                                style={{
                                    background: T.card, border: `1px solid ${T.border}`,
                                    borderRadius: T.radiusSm, padding: "14px 16px",
                                    textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                                }}
                            >
                                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 4 }}>
                                    <div style={{ fontWeight: 700, color: T.text, fontSize: 14, flex: 1, marginRight: 8 }}>
                                        {m.order_sequence}. {m.name}
                                    </div>
                                    <StatusBadge status={m.status} />
                                </div>
                                <div style={{ fontSize: 12, color: T.textSub, display: "flex", gap: 12, flexWrap: "wrap" }}>
                                    <span>{m.session_count ?? 0} sessions</span>
                                    {m.requires_assessment && <span style={{ color: "#7C3AED" }}>· Assessment required</span>}
                                    {m.started_at && <span>· Started {new Date(m.started_at).toLocaleDateString()}</span>}
                                    {m.completed_at && <span>· Completed {new Date(m.completed_at).toLocaleDateString()}</span>}
                                </div>
                            </button>
                        ))}
                    </div>
                )}

                {/* Mentees tab */}
                {!loading && tab === "mentees" && (
                    <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                        {mentees.length === 0 && (
                            <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>No mentees enrolled.</div>
                        )}
                        {mentees.map(m => (
                            <div key={m.id} style={{
                                background: T.card, borderRadius: T.radiusSm, padding: "12px 14px",
                                boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                                display: "flex", alignItems: "center", gap: 12,
                            }}>
                                <div style={{
                                    width: 36, height: 36, borderRadius: "50%",
                                    background: T.primaryGhost, display: "flex", alignItems: "center",
                                    justifyContent: "center", fontWeight: 700, fontSize: 13, color: T.primary, flexShrink: 0,
                                }}>
                                    {(m.name ?? "?").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <div style={{ fontWeight: 600, color: T.text, fontSize: 14 }}>{m.name}</div>
                                    {m.email && <div style={{ fontSize: 11, color: T.textSub }}>{m.email}</div>}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
