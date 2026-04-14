import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function SessionNotesScreen({ session, onBack, onSaved }) {
    const [actualDate, setActualDate] = useState(session.actual_date ?? "");
    const [startTime, setStartTime] = useState(session.actual_time ?? "");
    const [location, setLocation] = useState(session.location ?? "");
    const [notes, setNotes] = useState(session.notes ?? "");
    const [status, setStatus] = useState(session.status ?? "scheduled");
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const handleSave = async () => {
        setSaving(true);
        setError(null);
        try {
            await api.sessions.update(session.id, {
                actual_date: actualDate || null,
                actual_time: startTime || null,
                location: location || null,
                notes: notes || null,
                status,
            });
            onSaved?.({ ...session, actual_date: actualDate, actual_time: startTime, location, notes, status });
        } catch (e) {
            setError(e.message ?? "Failed to save.");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 16px 12px", background: T.surface, borderBottom: `1px solid ${T.borderLight}`, display: "flex", alignItems: "center", gap: 12 }}>
                <button onClick={onBack} style={{ background: "none", border: "none", fontSize: 20, cursor: "pointer", color: T.textSecondary }}>←</button>
                <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 700, fontSize: 16, color: T.text }}>{session.title ?? "Session Notes"}</div>
                    <div style={{ fontSize: 12, color: T.textMuted }}>Session {session.session_number}</div>
                </div>
                <button onClick={handleSave} disabled={saving}
                    style={{ padding: "8px 16px", borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 14, fontWeight: 600, cursor: "pointer" }}>
                    {saving ? "Saving..." : "Save"}
                </button>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radius, padding: 12, marginBottom: 12, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}
                {!navigator.onLine && (
                    <div style={{ background: "#FFFBEB", border: "1px solid #FCD34D", borderRadius: T.radius, padding: 10, marginBottom: 12, fontSize: 13, color: "#92400E" }}>
                        Offline — notes will sync when you reconnect.
                    </div>
                )}

                <Field label="Status">
                    <select value={status} onChange={e => setStatus(e.target.value)}
                        style={{ width: "100%", padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, background: T.surface }}>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </Field>
                <Field label="Actual Date">
                    <input type="date" value={actualDate} onChange={e => setActualDate(e.target.value)}
                        style={inputStyle} />
                </Field>
                <Field label="Start Time">
                    <input type="time" value={startTime} onChange={e => setStartTime(e.target.value)}
                        style={inputStyle} />
                </Field>
                <Field label="Location">
                    <input placeholder="Ward, room, or facility" value={location} onChange={e => setLocation(e.target.value)}
                        style={inputStyle} />
                </Field>
                <Field label="Notes / Observations">
                    <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={4} placeholder="What was observed, discussed, or noted..."
                        style={{ ...inputStyle, resize: "vertical" }} />
                </Field>
            </div>
        </div>
    );
}

function Field({ label, children }) {
    return (
        <div style={{ marginBottom: 14 }}>
            <label style={{ display: "block", fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>{label}</label>
            {children}
        </div>
    );
}

const inputStyle = {
    width: "100%",
    padding: "10px 12px",
    borderRadius: T.radius,
    border: `1px solid ${T.border}`,
    fontSize: 15,
    boxSizing: "border-box",
    background: "#fff",
};
