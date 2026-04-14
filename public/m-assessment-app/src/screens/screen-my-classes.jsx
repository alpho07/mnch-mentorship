import { useState, useEffect } from "react";
import { T, MENTEE_META } from "../constants.js";
import api from "../services/api.service.js";

export function MyClassesScreen({ user, onOpen }) {
    const [classes, setClasses] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.me.classes()
            .then(d => setClasses(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setClasses([]))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "20px 20px 8px", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: T.text }}>
                    {MENTEE_META.icon} My Classes
                </div>
                <div style={{ fontSize: 13, color: T.textSub, marginTop: 2 }}>{user?.name ?? ""}</div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {!loading && classes?.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>
                        You are not enrolled in any classes yet.
                    </div>
                )}
                {(classes ?? []).map(c => (
                    <button
                        key={c.id}
                        onClick={() => onOpen(c)}
                        style={{
                            background: T.card, border: `1px solid ${T.border}`,
                            borderRadius: T.radiusSm, padding: "14px 16px",
                            textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                        }}
                    >
                        <div style={{ fontWeight: 700, color: T.text }}>{c.name}</div>
                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 3 }}>
                            {c.training_title ?? ""}
                        </div>
                        <div style={{ marginTop: 8, height: 4, borderRadius: 4, background: T.border, overflow: "hidden" }}>
                            <div style={{ height: "100%", width: (c.progress_percentage ?? 0) + "%", background: "#0EA5E9" }} />
                        </div>
                        <div style={{ fontSize: 11, color: T.textSub, marginTop: 4 }}>
                            {c.progress_percentage ?? 0}% complete · {c.module_count ?? 0} modules
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
