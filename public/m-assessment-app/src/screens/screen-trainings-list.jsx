import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function TrainingsListScreen({ user, onOpen }) {
    const [trainings, setTrainings] = useState(null);
    const [loading, setLoading]     = useState(true);

    useEffect(() => {
        api.trainings.list()
            .then(d => setTrainings(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setTrainings([]))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "20px 20px 8px", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: T.text }}>🏛️ Trainings</div>
                <div style={{ fontSize: 13, color: T.textSub, marginTop: 2 }}>National &amp; County programmes</div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {!loading && trainings?.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>No trainings found.</div>
                )}
                {(trainings ?? []).map(t => (
                    <button
                        key={t.id}
                        onClick={() => onOpen(t)}
                        style={{
                            background: T.card, border: `1px solid ${T.border}`,
                            borderRadius: T.radiusSm, padding: "14px 16px",
                            textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                        }}
                    >
                        <div style={{ fontWeight: 700, color: T.text }}>{t.title}</div>
                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 3 }}>
                            {t.county ?? ""} · {t.start_date ?? ""}
                        </div>
                        <div style={{
                            display: "inline-block", marginTop: 6, fontSize: 11, fontWeight: 700,
                            padding: "2px 8px", borderRadius: 6,
                            background: t.status === "active" ? "#D1FAE5" : "#F3F4F6",
                            color: t.status === "active" ? "#065F46" : "#6B7280",
                        }}>
                            {t.status}
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
