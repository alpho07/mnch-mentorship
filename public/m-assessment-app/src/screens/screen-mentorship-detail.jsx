// src/screens/screen-mentorship-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function MentorshipDetailScreen({ training, onBack, onOpenClass }) {
    const [detail, setDetail] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.mentorships.find(training.id)
            .then(d => setDetail(d?.data ?? null))
            .catch(() => setDetail(training)) // fallback to list-level data
            .finally(() => setLoading(false));
    }, [training.id]);

    const data = detail ?? training;
    const classes = data?.classes ?? [];

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{data.title}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>{data.facility ?? data.county ?? ""}</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}
                {classes.map(c => (
                    <button
                        key={c.id}
                        onClick={() => onOpenClass({ ...c, trainingId: data.id })}
                        style={{
                            background: T.card, border: `1px solid ${T.border}`,
                            borderRadius: T.radiusSm, padding: "14px 16px",
                            textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                        }}
                    >
                        <div style={{ fontWeight: 700, color: T.text }}>{c.name}</div>
                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 4 }}>
                            {c.participant_count ?? 0} mentees · {c.module_count ?? 0} modules
                        </div>
                        <div style={{ marginTop: 8, height: 4, borderRadius: 4, background: T.border, overflow: "hidden" }}>
                            <div style={{ height: "100%", width: (c.progress_percentage ?? 0) + "%", background: T.gradientSuccess }} />
                        </div>
                        <div style={{ fontSize: 11, color: T.textSub, marginTop: 4 }}>{c.progress_percentage ?? 0}% complete</div>
                    </button>
                ))}
                {!loading && classes.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>No classes yet.</div>
                )}
            </div>
        </div>
    );
}
