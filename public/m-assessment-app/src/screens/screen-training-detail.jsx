import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function TrainingDetailScreen({ training, onBack }) {
    const [detail, setDetail]           = useState(null);
    const [participants, setParticipants] = useState(null);
    const [loading, setLoading]         = useState(true);

    useEffect(() => {
        Promise.allSettled([
            api.trainings.find(training.id),
            api.trainings.participants(training.id),
        ]).then(([detailRes, partsRes]) => {
            if (detailRes.status === "fulfilled") setDetail(detailRes.value?.data ?? training);
            if (partsRes.status === "fulfilled") setParticipants(Array.isArray(partsRes.value?.data) ? partsRes.value.data : []);
        }).finally(() => setLoading(false));
    }, [training.id]);

    const data = detail ?? training;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{data.title}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>{data.county ?? ""}</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}

                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard, marginBottom: 16 }}>
                    {[
                        ["Start", data.start_date],
                        ["End", data.end_date],
                        ["Location", data.location_type],
                        ["Max Participants", data.max_participants],
                    ].filter(([, v]) => v != null).map(([label, value]) => (
                        <div key={label} style={{ display: "flex", justifyContent: "space-between", paddingBlock: 6, borderBottom: `1px solid ${T.borderLight}` }}>
                            <span style={{ fontSize: 13, color: T.textSub }}>{label}</span>
                            <span style={{ fontSize: 13, fontWeight: 600, color: T.text }}>{value}</span>
                        </div>
                    ))}
                </div>

                {participants && (
                    <>
                        <div style={{ fontSize: 13, fontWeight: 700, color: T.textSub, marginBottom: 8 }}>
                            PARTICIPANTS ({participants.length})
                        </div>
                        {participants.map(p => (
                            <div key={p.id} style={{
                                background: T.card, borderRadius: T.radiusSm, padding: "10px 14px",
                                marginBottom: 8, boxShadow: T.shadowCard,
                                display: "flex", justifyContent: "space-between",
                            }}>
                                <span style={{ fontSize: 14, color: T.text }}>{p.name}</span>
                                <span style={{ fontSize: 12, color: T.textSub }}>{p.completion_status}</span>
                            </div>
                        ))}
                    </>
                )}
            </div>
        </div>
    );
}
