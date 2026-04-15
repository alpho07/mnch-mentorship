// src/screens/screen-module-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const SESSION_STATUS = {
    scheduled:   { bg: "#F3F4F6", color: "#6B7280" },
    in_progress: { bg: "#FEF3C7", color: "#92400E" },
    completed:   { bg: "#D1FAE5", color: "#065F46" },
    cancelled:   { bg: "#FEE2E2", color: "#991B1B" },
};

function SessionCard({ session, onOpen }) {
    const s = SESSION_STATUS[session.status] ?? SESSION_STATUS.scheduled;
    const displayDate = session.actual_date ?? session.scheduled_date ?? null;
    const displayTime = session.actual_time ?? session.scheduled_time ?? null;

    return (
        <div
            onClick={() => onOpen?.(session)}
            style={{
                background: T.card, borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
                marginBottom: 8, cursor: onOpen ? "pointer" : "default", overflow: "hidden",
            }}
        >
            {/* Top accent */}
            <div style={{ height: 3, background: session.status === "completed" ? "#10B981" : session.status === "in_progress" ? "#F59E0B" : T.borderLight }} />
            <div style={{ padding: "12px 14px" }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 6 }}>
                    <div style={{ flex: 1, marginRight: 8 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, color: T.text }}>
                            {session.title ?? `Session ${session.session_number}`}
                        </div>
                        {session.description && (
                            <div style={{ fontSize: 12, color: T.textSub, marginTop: 2 }}>{session.description}</div>
                        )}
                    </div>
                    <span style={{ fontSize: 10, fontWeight: 700, padding: "2px 7px", borderRadius: 5, background: s.bg, color: s.color, flexShrink: 0 }}>
                        {session.status?.replace(/_/g, " ")}
                    </span>
                </div>

                <div style={{ display: "flex", flexWrap: "wrap", gap: 12, fontSize: 12, color: T.textSub }}>
                    {displayDate && (
                        <span style={{ display: "flex", alignItems: "center", gap: 4 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            {displayDate}{displayTime ? ` · ${displayTime}` : ""}
                        </span>
                    )}
                    {session.location && (
                        <span style={{ display: "flex", alignItems: "center", gap: 4 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            {session.location}
                        </span>
                    )}
                    {session.facilitator && (
                        <span style={{ display: "flex", alignItems: "center", gap: 4 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            {session.facilitator}
                        </span>
                    )}
                    {session.duration_minutes && (
                        <span>{session.duration_minutes} min</span>
                    )}
                </div>

                {session.notes && (
                    <div style={{ marginTop: 8, fontSize: 12, color: T.textSub, background: T.bg, borderRadius: 6, padding: "6px 10px", borderLeft: `3px solid ${T.border}` }}>
                        {session.notes}
                    </div>
                )}

                {session.attendance_taken && (
                    <div style={{ marginTop: 6, fontSize: 11, color: "#10B981", fontWeight: 600 }}>✓ Attendance recorded</div>
                )}
            </div>
        </div>
    );
}

export function ModuleDetailScreen({ module: mod, user, onBack, onOpenAttendance, onOpenSession }) {
    const [busy, setBusy]               = useState(false);
    const [localStatus, setLocalStatus] = useState(mod.status);
    const [error, setError]             = useState(null);
    const [sessions, setSessions]       = useState([]);
    const [loadingSessions, setLoadingSessions] = useState(true);

    useEffect(() => {
        if (mod?.id) {
            api.modules.sessions(mod.id)
                .then(d => setSessions(d?.data ?? d ?? []))
                .catch(() => {})
                .finally(() => setLoadingSessions(false));
        }
    }, [mod?.id]);

    const handleStart = async () => {
        setBusy(true); setError(null);
        try {
            await api.modules.start(mod.id);
            setLocalStatus("in_progress");
        } catch (e) {
            setError(e.message ?? "Failed to start module.");
        } finally { setBusy(false); }
    };

    const handleComplete = async () => {
        setBusy(true); setError(null);
        try {
            await api.modules.complete(mod.id);
            setLocalStatus("completed");
        } catch (e) {
            setError(e.message ?? "Failed to complete module.");
        } finally { setBusy(false); }
    };

    const completedSessions = sessions.filter(s => s.status === "completed").length;
    const progressPct = sessions.length > 0 ? Math.round((completedSessions / sessions.length) * 100) : 0;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{mod.name}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>
                        Module {mod.order_sequence}
                        {mod.requires_assessment ? " · Assessment required" : ""}
                    </div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
                {error && (
                    <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusXs, padding: "10px 14px", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                {/* Status + progress */}
                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "12px 16px", boxShadow: T.shadowCard }}>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 8 }}>
                        <div>
                            <div style={{ fontSize: 11, color: T.textSub, marginBottom: 2 }}>Status</div>
                            <div style={{ fontWeight: 700, fontSize: 14, color: T.text, textTransform: "capitalize" }}>
                                {localStatus.replace(/_/g, " ")}
                            </div>
                        </div>
                        {sessions.length > 0 && (
                            <div style={{ textAlign: "right" }}>
                                <div style={{ fontSize: 11, color: T.textSub, marginBottom: 2 }}>Sessions</div>
                                <div style={{ fontWeight: 700, fontSize: 14, color: T.text }}>
                                    {completedSessions}/{sessions.length}
                                </div>
                            </div>
                        )}
                    </div>
                    {sessions.length > 0 && (
                        <div style={{ height: 5, borderRadius: 4, background: T.borderLight, overflow: "hidden" }}>
                            <div style={{ height: "100%", width: `${progressPct}%`, background: progressPct === 100 ? "#10B981" : T.gradientPrimary, borderRadius: 4 }} />
                        </div>
                    )}
                </div>

                {/* Action buttons */}
                {localStatus === "not_started" && (
                    <button onClick={handleStart} disabled={busy} style={{
                        width: "100%", background: "#10B981", border: "none", borderRadius: T.radiusSm,
                        color: "#fff", fontWeight: 700, fontSize: 15, padding: "14px 0",
                        cursor: busy ? "not-allowed" : "pointer", opacity: busy ? 0.7 : 1,
                    }}>
                        {busy ? "Starting…" : "Start Module"}
                    </button>
                )}

                {localStatus === "in_progress" && (
                    <>
                        <button onClick={() => onOpenAttendance(mod)} style={{
                            width: "100%", background: "#6C5CE7", border: "none", borderRadius: T.radiusSm,
                            color: "#fff", fontWeight: 700, fontSize: 15, padding: "14px 0", cursor: "pointer",
                        }}>
                            Mark Attendance
                        </button>
                        <button onClick={handleComplete} disabled={busy} style={{
                            width: "100%", background: T.card, border: `1.5px solid ${T.border}`,
                            borderRadius: T.radiusSm, color: T.text,
                            fontWeight: 700, fontSize: 15, padding: "14px 0",
                            cursor: busy ? "not-allowed" : "pointer", opacity: busy ? 0.7 : 1,
                        }}>
                            {busy ? "Completing…" : "Complete Module"}
                        </button>
                    </>
                )}

                {localStatus === "completed" && (
                    <div style={{ textAlign: "center", color: "#10B981", fontWeight: 700, fontSize: 15 }}>
                        ✓ Module completed
                    </div>
                )}

                {/* Sessions */}
                <div>
                    <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, marginBottom: 8, textTransform: "uppercase", letterSpacing: 0.5 }}>
                        Sessions {sessions.length > 0 ? `(${sessions.length})` : ""}
                    </div>
                    {loadingSessions && <div style={{ color: T.textSub, fontSize: 13 }}>Loading sessions…</div>}
                    {!loadingSessions && sessions.length === 0 && (
                        <div style={{ color: T.textSub, fontSize: 13 }}>No sessions scheduled.</div>
                    )}
                    {sessions.map(s => (
                        <SessionCard key={s.id} session={s} onOpen={onOpenSession} />
                    ))}
                </div>
            </div>
        </div>
    );
}
