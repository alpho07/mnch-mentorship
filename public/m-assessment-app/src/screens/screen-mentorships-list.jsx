import { useState, useEffect } from "react";
import { T, MENTOR_META } from "../constants.js";
import api from "../services/api.service.js";

export function MentorshipsListScreen({ user, onOpen }) {
    const [mentorships, setMentorships] = useState(null);
    const [loading, setLoading]         = useState(true);
    const [error, setError]             = useState(null);

    useEffect(() => {
        api.mentorships.list()
            .then(d => setMentorships(Array.isArray(d?.data) ? d.data : []))
            .catch(e => setError(e.message))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ padding: "20px 20px 8px", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: T.text }}>
                    {MENTOR_META.icon} Mentorships
                </div>
                <div style={{ fontSize: 13, color: T.textSub, marginTop: 2 }}>
                    {user?.name ?? ""}
                </div>
            </div>

            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {error && <div style={{ color: "#EF4444", textAlign: "center", paddingTop: 40 }}>{error}</div>}
                {!loading && !error && mentorships?.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>
                        No mentorships assigned yet.
                    </div>
                )}
                {(mentorships ?? []).map(m => (
                    <button
                        key={m.id}
                        onClick={() => onOpen(m)}
                        style={{
                            background: T.card, border: `1px solid ${T.border}`,
                            borderRadius: T.radiusSm, padding: "14px 16px",
                            textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                        }}
                    >
                        <div style={{ fontWeight: 700, color: T.text, fontSize: 15 }}>{m.title}</div>
                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 4 }}>
                            {m.facility ?? m.county ?? ""} · {m.class_count} class{m.class_count !== 1 ? "es" : ""}
                        </div>
                        <div style={{
                            display: "inline-block", marginTop: 6,
                            fontSize: 11, fontWeight: 700, padding: "2px 8px",
                            borderRadius: 6,
                            background: m.status === "active" ? "#D1FAE5" : "#F3F4F6",
                            color: m.status === "active" ? "#065F46" : T.textSub,
                        }}>
                            {m.status}
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
