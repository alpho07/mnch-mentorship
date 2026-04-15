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

export function ClassDetailScreen({
    cls,
    onBack,
    onOpenModule,
    onManageMentees,
    onEditClass,
    onAddModule,
    confirm = (o) => Promise.resolve(window.confirm(o?.title ?? "Confirm?")),
}) {
    const [detail, setDetail]     = useState(null);
    const [loading, setLoading]   = useState(true);
    const [modules, setModules]   = useState([]);
    const [tab, setTab]           = useState("modules");
    const [acting, setActing]     = useState(null); // moduleId being started/completed/deleted

    useEffect(() => {
        const trainingId = cls.trainingId;
        const classId    = cls.id;

        if (trainingId) {
            api.mentorships.classDetail(trainingId, classId)
                .then(d => {
                    const data = d?.data ?? null;
                    setDetail(data);
                    setModules(data?.modules ?? []);
                })
                .catch(() => {
                    setDetail(cls);
                    setModules(cls.modules ?? []);
                })
                .finally(() => setLoading(false));
        } else {
            api.modules.list(classId)
                .then(d => {
                    const mods = Array.isArray(d?.data) ? d.data : [];
                    setDetail({ ...cls, modules: mods });
                    setModules(mods);
                })
                .catch(() => {
                    setDetail(cls);
                    setModules(cls.modules ?? []);
                })
                .finally(() => setLoading(false));
        }
    }, [cls.id]);

    // Called by parent after a module is added via the picker
    // Parent passes updated class data with new module appended
    useEffect(() => {
        if (cls.modules) setModules(cls.modules);
    }, [cls.modules]);

    const data    = detail ?? cls;
    const mentees = data?.mentees ?? [];
    const pct     = data?.progress_percentage ?? 0;

    const handleStart = async (mod) => {
        setActing(mod.id);
        try {
            const res = await api.modules.start(mod.id);
            const updated = res?.data ?? {};
            setModules(prev => prev.map(m => m.id === mod.id ? { ...m, ...updated } : m));
        } catch (e) {
            alert(e.message ?? "Failed to start module.");
        } finally {
            setActing(null);
        }
    };

    const handleComplete = async (mod) => {
        setActing(mod.id);
        try {
            const res = await api.modules.complete(mod.id);
            const updated = res?.data ?? {};
            setModules(prev => prev.map(m => m.id === mod.id ? { ...m, ...updated } : m));
        } catch (e) {
            alert(e.message ?? "Failed to complete module.");
        } finally {
            setActing(null);
        }
    };

    const handleDelete = async (mod) => {
        const ok = await confirm({
            title: `Delete "${mod.name}"?`,
            message: "This module and its sessions will be permanently removed. Only modules that haven't been started can be deleted.",
            confirmLabel: "Delete Module",
            danger: true,
        });
        if (!ok) return;
        setActing(mod.id);
        try {
            await api.modules.remove(mod.id);
            setModules(prev => prev.filter(m => m.id !== mod.id));
        } catch (e) {
            alert(e.message ?? "Failed to delete module.");
        } finally {
            setActing(null);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* ── Gradient Hero ── */}
            <div style={{
                background: "linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)",
                padding: "44px 20px 16px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 150, height: 150, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -30, right: -30 }} />
                <div style={{ display: "flex", alignItems: "flex-start", gap: 10, marginBottom: 12 }}>
                    <button onClick={onBack} style={{ border: "none", background: "rgba(255,255,255,0.12)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, flexShrink: 0, display: "flex", alignItems: "center", gap: 4 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                    </button>
                    {onEditClass && (
                        <button onClick={onEditClass} style={{ marginLeft: "auto", border: "none", background: "rgba(255,255,255,0.12)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4 }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Edit</span>
                        </button>
                    )}
                </div>
                <div style={{ fontSize: 18, fontWeight: 800, color: "white", lineHeight: 1.25, marginBottom: 6 }}>{data.name}</div>
                <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
                    <span style={{ fontSize: 11, fontWeight: 700, padding: "2px 9px", borderRadius: 20, background: "rgba(255,255,255,0.15)", color: "rgba(255,255,255,0.9)", border: "1px solid rgba(255,255,255,0.2)" }}>
                        {data.status}
                    </span>
                    <span style={{ fontSize: 12, color: "rgba(255,255,255,0.55)" }}>
                        {data.participant_count ?? mentees.length} mentees · {modules.length} modules
                    </span>
                </div>
                <div style={{ marginTop: 14 }}>
                    <div style={{ height: 5, borderRadius: 4, background: "rgba(255,255,255,0.15)", overflow: "hidden" }}>
                        <div style={{ height: "100%", width: `${pct}%`, background: pct === 100 ? "#34D399" : "rgba(255,255,255,0.8)", borderRadius: 4, transition: "width 0.6s" }} />
                    </div>
                    <div style={{ display: "flex", justifyContent: "space-between", marginTop: 4 }}>
                        <span style={{ fontSize: 11, color: "rgba(255,255,255,0.5)" }}>{data.start_date ?? ""}{data.end_date ? ` → ${data.end_date}` : ""}</span>
                        <span style={{ fontSize: 11, fontWeight: 700, color: pct === 100 ? "#34D399" : "rgba(255,255,255,0.8)" }}>{pct}%</span>
                    </div>
                </div>
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
                        {onAddModule && (
                            <button onClick={onAddModule} style={{
                                padding: "10px 14px", borderRadius: T.radiusSm, border: `1.5px dashed ${T.primary}`,
                                background: T.primaryGhost, color: T.primary, fontSize: 13, fontWeight: 700,
                                cursor: "pointer", textAlign: "center",
                            }}>
                                + Add Module
                            </button>
                        )}
                        {modules.length === 0 && (
                            <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>No modules.</div>
                        )}
                        {modules.map(m => {
                            const isActing = acting === m.id;
                            const canStart    = m.status === "not_started";
                            const canComplete = m.status === "in_progress";
                            const canDelete   = m.status === "not_started";

                            return (
                                <div
                                    key={m.id}
                                    style={{
                                        background: T.card, border: `1px solid ${T.border}`,
                                        borderRadius: T.radiusSm, boxShadow: T.shadowCard,
                                        overflow: "hidden", opacity: isActing ? 0.7 : 1,
                                    }}
                                >
                                    {/* Tappable body */}
                                    <div
                                        onClick={() => onOpenModule({ ...m, classId: cls.id })}
                                        style={{ padding: "14px 16px", cursor: "pointer" }}
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
                                    </div>

                                    {/* Action row */}
                                    <div style={{
                                        display: "flex", gap: 8, padding: "8px 12px",
                                        borderTop: `1px solid ${T.borderLight}`,
                                        background: T.bg,
                                    }}>
                                        {/* View */}
                                        <button
                                            onClick={() => onOpenModule({ ...m, classId: cls.id })}
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

                                        {/* Start / Complete */}
                                        {canStart && (
                                            <button
                                                onClick={(e) => { e.stopPropagation(); handleStart(m); }}
                                                disabled={isActing}
                                                style={{
                                                    flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                    padding: "6px 0", borderRadius: T.radiusXs,
                                                    border: "1px solid #6EE7B7", background: "#ECFDF5",
                                                    color: "#065F46", fontSize: 12, fontWeight: 600,
                                                    cursor: isActing ? "not-allowed" : "pointer",
                                                }}
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                    <polygon points="5 3 19 12 5 21 5 3"/>
                                                </svg>
                                                {isActing ? "…" : "Start"}
                                            </button>
                                        )}
                                        {canComplete && (
                                            <button
                                                onClick={(e) => { e.stopPropagation(); handleComplete(m); }}
                                                disabled={isActing}
                                                style={{
                                                    flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                    padding: "6px 0", borderRadius: T.radiusXs,
                                                    border: "1px solid #93C5FD", background: "#EFF6FF",
                                                    color: "#1D4ED8", fontSize: 12, fontWeight: 600,
                                                    cursor: isActing ? "not-allowed" : "pointer",
                                                }}
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                                {isActing ? "…" : "Complete"}
                                            </button>
                                        )}
                                        {m.status === "completed" && (
                                            <div style={{
                                                flex: 1, display: "flex", alignItems: "center", justifyContent: "center",
                                                fontSize: 12, color: "#065F46", fontWeight: 600,
                                            }}>
                                                ✓ Done
                                            </div>
                                        )}

                                        {/* Delete */}
                                        {canDelete ? (
                                            <button
                                                onClick={(e) => { e.stopPropagation(); handleDelete(m); }}
                                                disabled={isActing}
                                                style={{
                                                    flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                    padding: "6px 0", borderRadius: T.radiusXs,
                                                    border: "1px solid #FECACA", background: "#FEF2F2",
                                                    color: "#DC2626", fontSize: 12, fontWeight: 600,
                                                    cursor: isActing ? "not-allowed" : "pointer",
                                                }}
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
                                                </svg>
                                                Delete
                                            </button>
                                        ) : (
                                            <div style={{ flex: 1 }} />
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Mentees tab */}
                {!loading && tab === "mentees" && (
                    <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                        {onManageMentees && (
                            <button onClick={onManageMentees} style={{
                                padding: "10px 14px", borderRadius: T.radiusSm, border: `1.5px solid ${T.primary}`,
                                background: T.primaryGhost, color: T.primary, fontSize: 13, fontWeight: 700,
                                cursor: "pointer", textAlign: "center",
                            }}>
                                Manage Mentees
                            </button>
                        )}
                        {mentees.length === 0 && (
                            <div style={{ color: T.textSub, textAlign: "center", paddingTop: 20 }}>No mentees enrolled.</div>
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
