// src/screens/screen-mentorship-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";
import { ResourceCard } from "../components/ResourceCard.jsx";

const CLASS_STATUS = {
    draft:     { bg: "#F3F4F6", color: "#374151" },
    active:    { bg: "#D1FAE5", color: "#065F46" },
    completed: { bg: "#DBEAFE", color: "#1D4ED8" },
    cancelled: { bg: "#FEE2E2", color: "#991B1B" },
};

function InfoRow({ label, value }) {
    if (!value) return null;
    return (
        <div style={{ display: "flex", justifyContent: "space-between", paddingBlock: 7, borderBottom: `1px solid ${T.borderLight}` }}>
            <span style={{ fontSize: 12, color: T.textSub }}>{label}</span>
            <span style={{ fontSize: 12, fontWeight: 600, color: T.text, textAlign: "right", maxWidth: "60%" }}>{value}</span>
        </div>
    );
}

<<<<<<< HEAD
export function MentorshipDetailScreen({ training, onBack, onOpenClass, onAddClass, onEditClass, onDeleteClass }) {
=======
export function MentorshipDetailScreen({ training, onBack, onOpenClass }) {
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
    const [detail, setDetail]       = useState(null);
    const [loading, setLoading]     = useState(true);
    const [resources, setResources] = useState([]);
    const [showResources, setShowResources] = useState(false);

    useEffect(() => {
        api.mentorships.find(training.id)
            .then(d => setDetail(d?.data ?? null))
            .catch(() => setDetail(training))
            .finally(() => setLoading(false));
        api.resources.list("mentorship_manual")
            .then(d => setResources(Array.isArray(d?.data) ? d.data : []))
            .catch(() => {});
    }, [training.id]);

    const data = detail ?? training;
    const classes = data?.classes ?? [];

    const trainingStatus = data.status ?? "draft";
    const statusStyle = CLASS_STATUS[trainingStatus] ?? CLASS_STATUS.draft;

    return (
<<<<<<< HEAD
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* ── Gradient Hero ── */}
            <div style={{
                background: "linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)",
                padding: "44px 20px 20px",
                borderRadius: "0 0 24px 24px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 160, height: 160, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -40, right: -40 }} />
                <button onClick={onBack} style={{ border: "none", background: "rgba(255,255,255,0.12)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, marginBottom: 14, display: "flex", alignItems: "center", gap: 4 }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                </button>
                <div style={{ fontSize: 18, fontWeight: 800, color: "white", lineHeight: 1.25, marginBottom: 8 }}>{data.title}</div>
                <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
                    <span style={{ fontSize: 11, fontWeight: 700, padding: "3px 10px", borderRadius: 20, background: "rgba(255,255,255,0.15)", color: "rgba(255,255,255,0.9)", border: "1px solid rgba(255,255,255,0.2)" }}>
                        {trainingStatus}
                    </span>
                    {data.program && <span style={{ fontSize: 11, color: "rgba(255,255,255,0.6)" }}>{data.program}</span>}
                    {data.facility && <span style={{ fontSize: 11, color: "rgba(255,255,255,0.5)" }}>· {data.facility}</span>}
                </div>
            </div>

            <div style={{ padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
=======
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text, marginBottom: 2 }}>{data.title}</div>
                    <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
                        <span style={{ fontSize: 10, fontWeight: 700, padding: "2px 7px", borderRadius: 5, background: statusStyle.bg, color: statusStyle.color }}>
                            {trainingStatus}
                        </span>
                        {data.program && (
                            <span style={{ fontSize: 11, color: T.textSub }}>{data.program}</span>
                        )}
                    </div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}

                {/* Info card */}
                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "4px 14px", boxShadow: T.shadowCard }}>
                    <InfoRow label="Facility" value={data.facility} />
                    <InfoRow label="County" value={data.county} />
                    <InfoRow label="Mentor" value={data.mentor_name} />
                    <InfoRow label="Start Date" value={data.start_date} />
                    <InfoRow label="End Date" value={data.end_date} />
                    <InfoRow label="Location" value={data.location_type} />
                    <InfoRow label="Classes" value={classes.length > 0 ? `${classes.length} class${classes.length !== 1 ? "es" : ""}` : null} />
                </div>

                {/* Description */}
                {data.description && (
                    <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "12px 14px", boxShadow: T.shadowCard }}>
                        <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>Description</div>
                        <div style={{ fontSize: 13, color: T.text, lineHeight: 1.6 }}>{data.description}</div>
                    </div>
                )}

                {/* Classes */}
                {classes.length > 0 && (
                    <div>
<<<<<<< HEAD
                        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 8 }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5 }}>
                                Classes ({classes.length})
                            </div>
                            {onAddClass && (
                                <button onClick={onAddClass} style={{
                                    padding: "5px 12px", borderRadius: T.radiusSm, border: "none",
                                    background: "linear-gradient(135deg, #3730A3, #6366F1)",
                                    color: "#fff", fontSize: 12, fontWeight: 700, cursor: "pointer",
                                }}>
                                    + Add Class
                                </button>
                            )}
=======
                        <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, marginBottom: 8, textTransform: "uppercase", letterSpacing: 0.5 }}>
                            Classes ({classes.length})
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                        </div>
                        {classes.map(c => {
                            const cs = CLASS_STATUS[c.status] ?? CLASS_STATUS.draft;
                            const pct = c.progress_percentage ?? 0;
                            return (
<<<<<<< HEAD
                                <div
                                    key={c.id}
                                    style={{
                                        background: T.card, border: `1px solid ${T.border}`,
                                        borderRadius: T.radiusSm, boxShadow: T.shadowCard, marginBottom: 8,
                                        overflow: "hidden",
                                    }}
                                >
                                    {/* Tappable body */}
                                    <div
                                        onClick={() => onOpenClass({ ...c, trainingId: data.id })}
                                        style={{ padding: "14px 16px", cursor: "pointer" }}
                                    >
                                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 6 }}>
                                            <div style={{ fontWeight: 700, color: T.text, fontSize: 14 }}>{c.name}</div>
                                            <span style={{ fontSize: 10, fontWeight: 700, padding: "2px 7px", borderRadius: 5, background: cs.bg, color: cs.color, flexShrink: 0, marginLeft: 8 }}>
                                                {c.status}
                                            </span>
                                        </div>
                                        <div style={{ fontSize: 12, color: T.textSub, marginBottom: 8 }}>
                                            {c.participant_count ?? 0} mentees · {c.module_count ?? 0} modules
                                            {c.start_date ? ` · ${c.start_date}` : ""}
                                        </div>
                                        <div style={{ height: 5, borderRadius: 4, background: T.borderLight, overflow: "hidden" }}>
                                            <div style={{ height: "100%", width: `${pct}%`, background: pct === 100 ? "#10B981" : T.gradientPrimary, borderRadius: 4 }} />
                                        </div>
                                        <div style={{ fontSize: 11, color: T.textSub, marginTop: 3 }}>{pct}% complete</div>
                                    </div>
                                    {/* Action row */}
                                    <div style={{
                                        display: "flex", gap: 8, padding: "8px 12px",
                                        borderTop: `1px solid ${T.borderLight}`,
                                        background: T.bg,
                                    }}>
                                        <button
                                            onClick={(e) => { e.stopPropagation(); onOpenClass({ ...c, trainingId: data.id }); }}
                                            style={{
                                                flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                padding: "6px 0", borderRadius: T.radiusXs,
                                                border: "none", background: "linear-gradient(135deg, #3730A3, #6366F1)",
                                                color: "#fff", fontSize: 12, fontWeight: 600, cursor: "pointer",
                                            }}
                                        >
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            View
                                        </button>
                                        {onEditClass && (
                                            <button
                                                onClick={(e) => { e.stopPropagation(); onEditClass({ ...c, trainingId: data.id }); }}
                                                style={{
                                                    flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                    padding: "6px 0", borderRadius: T.radiusXs,
                                                    border: `1px solid ${T.border}`, background: T.card,
                                                    color: T.primary, fontSize: 12, fontWeight: 600, cursor: "pointer",
                                                }}
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                                Edit
                                            </button>
                                        )}
                                        {onDeleteClass && (
                                            <button
                                                onClick={(e) => { e.stopPropagation(); onDeleteClass({ ...c, trainingId: data.id }); }}
                                                style={{
                                                    flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                    padding: "6px 0", borderRadius: T.radiusXs,
                                                    border: "1px solid #FECACA", background: "#FEF2F2",
                                                    color: "#DC2626", fontSize: 12, fontWeight: 600, cursor: "pointer",
                                                }}
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
                                                </svg>
                                                Delete
                                            </button>
                                        )}
                                    </div>
                                </div>
=======
                                <button
                                    key={c.id}
                                    onClick={() => onOpenClass({ ...c, trainingId: data.id })}
                                    style={{
                                        width: "100%", background: T.card, border: `1px solid ${T.border}`,
                                        borderRadius: T.radiusSm, padding: "14px 16px",
                                        textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard, marginBottom: 8,
                                    }}
                                >
                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 6 }}>
                                        <div style={{ fontWeight: 700, color: T.text, fontSize: 14 }}>{c.name}</div>
                                        <span style={{ fontSize: 10, fontWeight: 700, padding: "2px 7px", borderRadius: 5, background: cs.bg, color: cs.color, flexShrink: 0, marginLeft: 8 }}>
                                            {c.status}
                                        </span>
                                    </div>
                                    <div style={{ fontSize: 12, color: T.textSub, marginBottom: 8 }}>
                                        {c.participant_count ?? 0} mentees · {c.module_count ?? 0} modules
                                        {c.start_date ? ` · ${c.start_date}` : ""}
                                    </div>
                                    <div style={{ height: 5, borderRadius: 4, background: T.borderLight, overflow: "hidden" }}>
                                        <div style={{ height: "100%", width: `${pct}%`, background: pct === 100 ? "#10B981" : T.gradientPrimary, borderRadius: 4 }} />
                                    </div>
                                    <div style={{ fontSize: 11, color: T.textSub, marginTop: 3 }}>{pct}% complete</div>
                                </button>
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                            );
                        })}
                    </div>
                )}

                {!loading && classes.length === 0 && (
<<<<<<< HEAD
                    <div style={{ textAlign: "center", paddingTop: 20 }}>
                        <div style={{ color: T.textSub, marginBottom: 12, fontSize: 14 }}>No classes yet.</div>
                        {onAddClass && (
                            <button onClick={onAddClass} style={{
                                padding: "10px 24px", borderRadius: T.radiusSm, border: "none",
                                background: "linear-gradient(135deg, #3730A3, #6366F1)",
                                color: "#fff", fontSize: 13, fontWeight: 700, cursor: "pointer",
                                boxShadow: "0 4px 12px rgba(55,48,163,0.3)",
                            }}>
                                + Add First Class
                            </button>
                        )}
                    </div>
=======
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 20 }}>No classes yet.</div>
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                )}

                {/* Resources */}
                {resources.length > 0 && (
                    <div>
                        <button
                            onClick={() => setShowResources(v => !v)}
                            style={{
                                width: "100%", display: "flex", justifyContent: "space-between", alignItems: "center",
                                background: T.card, border: `1px solid ${T.border}`, borderRadius: T.radiusSm,
                                padding: "12px 14px", cursor: "pointer", marginBottom: showResources ? 8 : 0,
                            }}
                        >
                            <span style={{ fontSize: 12, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5 }}>
                                Resources & Manuals ({resources.length})
                            </span>
                            <span style={{ fontSize: 13, color: T.textSub }}>{showResources ? "▲" : "▼"}</span>
                        </button>
                        {showResources && resources.map(r => <ResourceCard key={r.id} resource={r} />)}
                    </div>
                )}
            </div>
        </div>
    );
}
