// src/screens/screen-module-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function ModuleDetailScreen({ module: mod, user, onBack, onOpenAttendance, onOpenSession }) {
    const [busy, setBusy] = useState(false);
    const [localStatus, setLocalStatus] = useState(mod.status);
    const [error, setError] = useState(null);
    const [sessions, setSessions] = useState([]);

    useEffect(() => {
        if (mod?.id) {
            api.modules.sessions(mod.id)
                .then(d => setSessions(d?.data ?? d ?? []))
                .catch(() => {});
        }
    }, [mod?.id]);

    const handleStart = async () => {
        setBusy(true);
        setError(null);
        try {
            await api.modules.start(mod.id);
            setLocalStatus("in_progress");
        } catch (e) {
            setError(e.message ?? "Failed to start module.");
        } finally {
            setBusy(false);
        }
    };

    const handleComplete = async () => {
        setBusy(true);
        setError(null);
        try {
            await api.modules.complete(mod.id);
            setLocalStatus("completed");
        } catch (e) {
            setError(e.message ?? "Failed to complete module.");
        } finally {
            setBusy(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{mod.name}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>Module {mod.order_sequence}</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 20, display: "flex", flexDirection: "column", gap: 16 }}>
                {error && (
                    <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusXs, padding: "10px 14px", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard }}>
                    <div style={{ fontSize: 12, color: T.textSub, marginBottom: 4 }}>Status</div>
                    <div style={{ fontWeight: 700, fontSize: 15, color: T.text, textTransform: "capitalize" }}>
                        {localStatus.replace("_", " ")}
                    </div>
                </div>

                {localStatus === "not_started" && (
                    <button
                        onClick={handleStart}
                        disabled={busy}
                        style={{
                            width: "100%", background: "#10B981", border: "none", borderRadius: T.radiusSm,
                            color: "#fff", fontWeight: 700, fontSize: 15, padding: "14px 0",
                            cursor: busy ? "not-allowed" : "pointer", opacity: busy ? 0.7 : 1,
                        }}
                    >
                        {busy ? "Starting…" : "Start Module"}
                    </button>
                )}

                {localStatus === "in_progress" && (
                    <>
                        <button
                            onClick={() => onOpenAttendance(mod)}
                            style={{
                                width: "100%", background: "#6C5CE7", border: "none", borderRadius: T.radiusSm,
                                color: "#fff", fontWeight: 700, fontSize: 15, padding: "14px 0", cursor: "pointer",
                            }}
                        >
                            Mark Attendance
                        </button>
                        <button
                            onClick={handleComplete}
                            disabled={busy}
                            style={{
                                width: "100%", background: T.card, border: `1.5px solid ${T.border}`,
                                borderRadius: T.radiusSm, color: T.text,
                                fontWeight: 700, fontSize: 15, padding: "14px 0",
                                cursor: busy ? "not-allowed" : "pointer", opacity: busy ? 0.7 : 1,
                            }}
                        >
                            {busy ? "Completing…" : "Complete Module"}
                        </button>
                    </>
                )}

                {localStatus === "completed" && (
                    <div style={{ textAlign: "center", color: "#10B981", fontWeight: 700, fontSize: 15 }}>
                        ✓ Module completed
                    </div>
                )}

                {sessions.length > 0 && (
                    <div style={{ marginTop: 16 }}>
                        <div style={{ fontSize: 13, fontWeight: 600, color: T.textSecondary, marginBottom: 8 }}>SESSIONS</div>
                        {sessions.map(s => (
                            <div key={s.id} onClick={() => onOpenSession?.(s)}
                                style={{ padding: "12px 14px", background: T.surface, borderRadius: T.radius, border: `1px solid ${T.border}`, marginBottom: 8, cursor: "pointer" }}>
                                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                                    <div>
                                        <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{s.title ?? "Session " + s.session_number}</div>
                                        <div style={{ fontSize: 12, color: T.textMuted, marginTop: 2 }}>
                                            {s.actual_date ?? s.scheduled_date ?? "Not scheduled"}
                                        </div>
                                    </div>
                                    <span style={{ fontSize: 12, padding: "3px 8px", borderRadius: 10, background: s.status === "completed" ? "#D1FAE5" : "#F3F4F6", color: s.status === "completed" ? "#065F46" : T.textSecondary }}>
                                        {s.status ?? "scheduled"}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
