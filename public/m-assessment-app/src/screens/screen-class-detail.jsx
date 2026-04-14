// src/screens/screen-class-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const STATUS_COLOR = {
    not_started: { bg: "#F3F4F6", text: "#6B7280" },
    in_progress:  { bg: "#DBEAFE", text: "#1E40AF" },
    completed:    { bg: "#D1FAE5", text: "#065F46" },
};

export function ClassDetailScreen({ cls, onBack, onOpenModule }) {
    const [modules, setModules] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.modules.list(cls.id)
            .then(d => setModules(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setModules([]))
            .finally(() => setLoading(false));
    }, [cls.id]);

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{cls.name}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>{cls.participant_count ?? 0} mentees enrolled</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}
                {(modules ?? []).map(m => {
                    const colors = STATUS_COLOR[m.status] ?? STATUS_COLOR.not_started;
                    return (
                        <button
                            key={m.id}
                            onClick={() => onOpenModule({ ...m, classId: cls.id })}
                            style={{
                                background: T.card, border: `1px solid ${T.border}`,
                                borderRadius: T.radiusSm, padding: "14px 16px",
                                textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                                display: "flex", justifyContent: "space-between", alignItems: "center",
                            }}
                        >
                            <div>
                                <div style={{ fontWeight: 700, color: T.text }}>{m.name}</div>
                                <div style={{ fontSize: 12, color: T.textSub, marginTop: 3 }}>
                                    {m.session_count ?? 0} sessions
                                </div>
                            </div>
                            <div style={{ fontSize: 11, fontWeight: 700, padding: "3px 10px", borderRadius: 6, background: colors.bg, color: colors.text }}>
                                {m.status.replace("_", " ")}
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
