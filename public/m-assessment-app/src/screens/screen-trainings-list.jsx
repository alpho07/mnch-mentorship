import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const STATUS_MAP = {
    upcoming:  { bg: "#DBEAFE", color: "#1D4ED8", dot: "#3B82F6" },
    active:    { bg: "#D1FAE5", color: "#065F46", dot: "#10B981" },
    ongoing:   { bg: "#D1FAE5", color: "#065F46", dot: "#10B981" },
    completed: { bg: "#F3F4F6", color: "#6B7280", dot: "#9CA3AF" },
    cancelled: { bg: "#FEE2E2", color: "#991B1B", dot: "#EF4444" },
    draft:     { bg: "#FEF3C7", color: "#92400E", dot: "#F59E0B" },
};

function TrainingCard({ training, onOpen }) {
    const s = STATUS_MAP[training.status] ?? STATUS_MAP.draft;
    const location = training.facility ?? training.county ?? null;

    return (
        <button
            onClick={() => onOpen(training)}
            style={{
                width: "100%", background: T.card, border: `1px solid ${T.border}`,
                borderRadius: T.radius, padding: 0, textAlign: "left",
                cursor: "pointer", boxShadow: T.shadowCard, overflow: "hidden",
                display: "block",
            }}
        >
            {/* Accent stripe */}
            <div style={{ height: 3, background: `linear-gradient(90deg, ${s.dot}, ${s.dot}88)` }} />

            <div style={{ padding: "14px 16px" }}>
                {/* Header row */}
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 8, gap: 8 }}>
                    <div style={{ fontSize: 14, fontWeight: 700, color: T.text, flex: 1, lineHeight: 1.35 }}>
                        {training.title}
                    </div>
                    <span style={{
                        fontSize: 10, fontWeight: 700, padding: "3px 8px", borderRadius: 20,
                        background: s.bg, color: s.color, flexShrink: 0, whiteSpace: "nowrap",
                    }}>
                        {training.status}
                    </span>
                </div>

                {/* Meta row */}
                <div style={{ display: "flex", flexWrap: "wrap", gap: 10, fontSize: 12, color: T.textSub }}>
                    {location && (
                        <span style={{ display: "flex", alignItems: "center", gap: 3 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            {location}
                        </span>
                    )}
                    {training.location_type && (
                        <span style={{ textTransform: "capitalize" }}>{training.location_type.replace(/_/g, " ")}</span>
                    )}
                    {training.start_date && (
                        <span style={{ display: "flex", alignItems: "center", gap: 3 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            {training.start_date}{training.end_date ? ` — ${training.end_date}` : ""}
                        </span>
                    )}
                </div>
            </div>
        </button>
    );
}

export function TrainingsListScreen({ user, onOpen }) {
    const [trainings, setTrainings] = useState(null);
    const [loading, setLoading]     = useState(true);
    const [filter, setFilter]       = useState("all");

    useEffect(() => {
        api.trainings.list()
            .then(d => setTrainings(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setTrainings([]))
            .finally(() => setLoading(false));
    }, []);

    const all = trainings ?? [];
    const filtered = filter === "all" ? all
        : filter === "active" ? all.filter(t => ["active","ongoing","upcoming"].includes(t.status))
        : all.filter(t => t.status === filter);

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ padding: "20px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: T.text }}>🏛️ Trainings</div>
                <div style={{ fontSize: 13, color: T.textSub, marginTop: 2 }}>National & County programmes</div>
            </div>

            {/* Filter tabs */}
            <div style={{ background: T.card, padding: "8px 16px", borderBottom: `1px solid ${T.border}`, display: "flex", gap: 6 }}>
                {[
                    { key: "all", label: `All (${all.length})` },
                    { key: "active", label: "Active" },
                    { key: "completed", label: "Done" },
                ].map(tab => (
                    <button key={tab.key} onClick={() => setFilter(tab.key)} style={{
                        padding: "5px 12px", borderRadius: 20, border: "none", fontSize: 12, fontWeight: 600,
                        cursor: "pointer",
                        background: filter === tab.key ? T.primary : T.bg,
                        color: filter === tab.key ? "#fff" : T.textSub,
                        transition: "all 0.2s",
                    }}>
                        {tab.label}
                    </button>
                ))}
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {!loading && filtered.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>No trainings found.</div>
                )}
                {filtered.map(t => <TrainingCard key={t.id} training={t} onOpen={onOpen} />)}
            </div>
        </div>
    );
}
