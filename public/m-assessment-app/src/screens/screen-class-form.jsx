// screen-class-form.jsx — Add / Edit a MentorshipClass
import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const inputStyle = {
    width: "100%", padding: "11px 13px", borderRadius: T.radiusSm,
    border: `1px solid ${T.border}`, fontSize: 14, boxSizing: "border-box",
    background: "#fff", color: T.text, fontFamily: "inherit", outline: "none",
};

function Field({ label, required, children }) {
    return (
        <div style={{ marginBottom: 16 }}>
            <label style={{ display: "block", fontSize: 12, fontWeight: 700, color: T.textSub, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>
                {label}{required && <span style={{ color: "#EF4444", marginLeft: 2 }}>*</span>}
            </label>
            {children}
        </div>
    );
}

export function ClassFormScreen({ trainingId, existingClass, onBack, onSaved }) {
    const isEdit = !!existingClass;

    const [name, setName]           = useState(existingClass?.name ?? "");
    const [startDate, setStartDate] = useState(existingClass?.start_date ?? "");
    const [endDate, setEndDate]     = useState(existingClass?.end_date ?? "");
    const [notes, setNotes]         = useState(existingClass?.notes ?? "");
    const [saving, setSaving]       = useState(false);
    const [error, setError]         = useState(null);

    const handleSave = async () => {
        if (!name.trim()) { setError("Class name is required."); return; }
        setSaving(true); setError(null);
        try {
            const payload = {
                name: name.trim(),
                start_date: startDate || null,
                end_date: endDate || null,
                notes: notes || null,
            };
            let result;
            if (isEdit) {
                result = await api.mentorships.updateClass(trainingId, existingClass.id, payload);
            } else {
                result = await api.mentorships.createClass(trainingId, payload);
            }
            onSaved?.(result?.data);
        } catch (e) {
            setError(e.message ?? "Failed to save class.");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ height: 3, background: T.gradientPrimary }} />
                <div style={{ padding: "14px 16px", display: "flex", alignItems: "center", gap: 12 }}>
                    <button onClick={onBack} style={{ background: "none", border: "none", cursor: "pointer", padding: 4 }}>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    </button>
                    <div style={{ flex: 1, fontWeight: 800, fontSize: 16, color: T.text }}>
                        {isEdit ? "Edit Class" : "New Class"}
                    </div>
                    <button onClick={handleSave} disabled={saving} style={{
                        padding: "8px 20px", borderRadius: T.radiusSm, border: "none",
                        background: saving ? T.border : T.gradientPrimary,
                        color: "#fff", fontSize: 13, fontWeight: 700, cursor: saving ? "not-allowed" : "pointer",
                        boxShadow: saving ? "none" : `0 4px 12px ${T.primaryGlow}`,
                    }}>
                        {saving ? "Saving…" : isEdit ? "Update" : "Create"}
                    </button>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 14, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard }}>
                    <Field label="Class Name" required>
                        <input
                            value={name}
                            onChange={e => setName(e.target.value)}
                            placeholder="e.g. Cohort A — Nairobi West"
                            style={inputStyle}
                        />
                    </Field>
                    <Field label="Start Date">
                        <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} style={inputStyle} />
                    </Field>
                    <Field label="End Date">
                        <input type="date" value={endDate} onChange={e => setEndDate(e.target.value)} style={inputStyle} />
                    </Field>
                    <Field label="Notes">
                        <textarea
                            value={notes}
                            onChange={e => setNotes(e.target.value)}
                            rows={3}
                            placeholder="Optional notes about this class…"
                            style={{ ...inputStyle, resize: "vertical" }}
                        />
                    </Field>
                </div>
            </div>
        </div>
    );
}
