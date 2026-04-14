import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function MentorshipFormScreen({ user, onBack, onCreated }) {
    const [step, setStep] = useState(1);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    // Step 1 fields
    const [programs, setPrograms] = useState([]);
    const [programId, setProgramId] = useState("");
    const [facilityId, setFacilityId] = useState(user?.facility_id ?? "");
    const [facilityName, setFacilityName] = useState(user?.facility ?? "");
    const [startDate, setStartDate] = useState("");
    const [endDate, setEndDate] = useState("");
    const [maxParticipants, setMaxParticipants] = useState(20);

    // Step 2 fields
    const [availableModules, setAvailableModules] = useState([]);
    const [selectedModuleIds, setSelectedModuleIds] = useState([]);

    // Step 3 fields
    const [menteeSearch, setMenteeSearch] = useState("");
    const [menteeResults, setMenteeResults] = useState([]);
    const [selectedMentees, setSelectedMentees] = useState([]);
    const [menteeSearching, setMenteeSearching] = useState(false);

    useEffect(() => {
        api.lookups.programs().then(setPrograms).catch(() => {});
    }, []);

    useEffect(() => {
        if (programId) {
            api.lookups.programModules(programId).then(setAvailableModules).catch(() => {});
        }
    }, [programId]);

    const searchMentees = async (q) => {
        if (q.length < 2) { setMenteeResults([]); return; }
        setMenteeSearching(true);
        try {
            const results = await api.lookups.userSearch(q, facilityId || null);
            const data = results?.data ?? results ?? [];
            setMenteeResults(data.filter(u => !selectedMentees.find(s => s.id === u.id)));
        } catch { setMenteeResults([]); }
        finally { setMenteeSearching(false); }
    };

    const toggleModule = (id) => {
        setSelectedModuleIds(prev =>
            prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
        );
    };

    const addMentee = (u) => {
        setSelectedMentees(prev => [...prev, u]);
        setMenteeResults(prev => prev.filter(r => r.id !== u.id));
        setMenteeSearch("");
    };

    const removeMentee = (id) => setSelectedMentees(prev => prev.filter(u => u.id !== id));

    const handleSave = async (startNow) => {
        setSaving(true);
        setError(null);
        try {
            const res = await api.mentorshipCreate.create({
                program_id: parseInt(programId),
                facility_id: parseInt(facilityId),
                start_date: startDate,
                end_date: endDate,
                max_participants: maxParticipants,
                module_ids: selectedModuleIds,
            });
            const newTraining = res?.data ?? res;

            // Enroll selected mentees
            if (selectedMentees.length > 0 && newTraining?.class?.id) {
                for (const mentee of selectedMentees) {
                    await api.classLifecycle.enrollMentee(newTraining.class.id, mentee.id).catch(() => {});
                }
            }

            // Optionally start the class
            if (startNow && newTraining?.class?.id) {
                await api.classLifecycle.start(newTraining.class.id).catch(() => {});
            }

            onCreated(newTraining);
        } catch (e) {
            setError(e.message ?? "Failed to save mentorship.");
        } finally {
            setSaving(false);
        }
    };

    const stepLabel = ["Setup", "Modules", "Mentees", "Review"];

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ padding: "16px 16px 0", background: T.surface, borderBottom: `1px solid ${T.borderLight}` }}>
                <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 12 }}>
                    <button onClick={onBack} style={{ background: "none", border: "none", fontSize: 20, cursor: "pointer", color: T.textSecondary }}>←</button>
                    <span style={{ fontWeight: 700, fontSize: 17, color: T.text }}>New Mentorship</span>
                </div>
                {/* Step indicators */}
                <div style={{ display: "flex", gap: 4, paddingBottom: 12 }}>
                    {stepLabel.map((label, i) => (
                        <div key={i} style={{ flex: 1, textAlign: "center" }}>
                            <div style={{
                                height: 4, borderRadius: 2,
                                background: step > i + 1 ? T.primary : step === i + 1 ? T.primaryLight : T.borderLight,
                                marginBottom: 4,
                            }} />
                            <span style={{ fontSize: 10, color: step === i + 1 ? T.primary : T.textMuted }}>{label}</span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radius, padding: 12, marginBottom: 12, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                {/* Step 1: Setup */}
                {step === 1 && (
                    <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>Program *
                            <select value={programId} onChange={e => setProgramId(e.target.value)}
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, background: T.surface }}>
                                <option value="">Select program...</option>
                                {programs.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                        </label>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>Facility
                            <input value={facilityName} readOnly
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, background: "#F9FAFB", boxSizing: "border-box" }} />
                        </label>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>Start Date *
                            <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)}
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, boxSizing: "border-box" }} />
                        </label>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>End Date *
                            <input type="date" value={endDate} min={startDate} onChange={e => setEndDate(e.target.value)}
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, boxSizing: "border-box" }} />
                        </label>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>Max Participants
                            <input type="number" value={maxParticipants} min={1} onChange={e => setMaxParticipants(parseInt(e.target.value) || 20)}
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, boxSizing: "border-box" }} />
                        </label>
                    </div>
                )}

                {/* Step 2: Modules */}
                {step === 2 && (
                    <div>
                        <p style={{ fontSize: 13, color: T.textSecondary, marginBottom: 12 }}>
                            Select modules to include. Sessions are auto-created from program templates.
                        </p>
                        {availableModules.length === 0 && (
                            <div style={{ textAlign: "center", color: T.textMuted, padding: 32 }}>
                                {programId ? "No modules available for this program." : "Select a program first."}
                            </div>
                        )}
                        {availableModules.map(m => {
                            const selected = selectedModuleIds.includes(m.id);
                            return (
                                <div key={m.id} onClick={() => toggleModule(m.id)}
                                    style={{ display: "flex", alignItems: "center", gap: 12, padding: "12px 14px", marginBottom: 8, borderRadius: T.radius, border: `1px solid ${selected ? T.primary : T.border}`, background: selected ? T.primaryLight + "20" : T.surface, cursor: "pointer" }}>
                                    <div style={{ width: 20, height: 20, borderRadius: 4, border: `2px solid ${selected ? T.primary : T.border}`, background: selected ? T.primary : "transparent", display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>
                                        {selected && <span style={{ color: "#fff", fontSize: 12 }}>✓</span>}
                                    </div>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{m.name}</div>
                                        {m.session_count > 0 && <div style={{ fontSize: 12, color: T.textMuted }}>{m.session_count} session{m.session_count !== 1 ? "s" : ""}</div>}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Step 3: Mentees */}
                {step === 3 && (
                    <div>
                        {!navigator.onLine && (
                            <div style={{ background: "#FFFBEB", border: "1px solid #FCD34D", borderRadius: T.radius, padding: 12, marginBottom: 14, display: "flex", gap: 10 }}>
                                <span style={{ fontSize: 18 }}>🔒</span>
                                <div>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: "#92400E" }}>Mobile data required</div>
                                    <div style={{ fontSize: 13, color: "#78350F", marginTop: 2 }}>Turn on mobile data to search and add mentees. You can skip and enroll them after saving.</div>
                                </div>
                            </div>
                        )}
                        {navigator.onLine && (
                            <>
                                <input placeholder="Search by name..." value={menteeSearch}
                                    onChange={e => { setMenteeSearch(e.target.value); searchMentees(e.target.value); }}
                                    style={{ width: "100%", padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, marginBottom: 8, boxSizing: "border-box" }} />
                                {menteeSearching && <div style={{ textAlign: "center", color: T.textMuted, padding: 8, fontSize: 13 }}>Searching...</div>}
                                {menteeResults.map(u => (
                                    <div key={u.id} onClick={() => addMentee(u)}
                                        style={{ padding: "10px 14px", borderRadius: T.radius, border: `1px solid ${T.border}`, marginBottom: 6, cursor: "pointer", background: T.surface }}>
                                        <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{u.name}</div>
                                        <div style={{ fontSize: 12, color: T.textMuted }}>{u.facility_name}</div>
                                    </div>
                                ))}
                            </>
                        )}
                        {selectedMentees.length > 0 && (
                            <div style={{ marginTop: 12 }}>
                                <div style={{ fontSize: 12, color: T.textSecondary, marginBottom: 6 }}>Added ({selectedMentees.length})</div>
                                {selectedMentees.map(u => (
                                    <div key={u.id} style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "8px 14px", borderRadius: T.radius, background: T.primaryLight + "15", marginBottom: 6 }}>
                                        <span style={{ fontSize: 14, color: T.text }}>{u.name}</span>
                                        <button onClick={() => removeMentee(u.id)} style={{ background: "none", border: "none", color: T.textMuted, fontSize: 18, cursor: "pointer" }}>×</button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Step 4: Review */}
                {step === 4 && (
                    <div>
                        <div style={{ background: T.surface, borderRadius: T.radius, border: `1px solid ${T.border}`, padding: 16, marginBottom: 12 }}>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Program</div>
                            <div style={{ fontSize: 15, fontWeight: 500, color: T.text, marginBottom: 12 }}>
                                {programs.find(p => p.id == programId)?.name ?? "—"}
                            </div>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Facility</div>
                            <div style={{ fontSize: 15, color: T.text, marginBottom: 12 }}>{facilityName}</div>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Dates</div>
                            <div style={{ fontSize: 15, color: T.text, marginBottom: 12 }}>{startDate} → {endDate}</div>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Modules</div>
                            <div style={{ fontSize: 15, color: T.text, marginBottom: 12 }}>{selectedModuleIds.length} selected</div>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Mentees</div>
                            <div style={{ fontSize: 15, color: T.text }}>{selectedMentees.length} added</div>
                        </div>
                        <button disabled={saving || !programId || !startDate || !endDate}
                            onClick={() => handleSave(false)}
                            style={{ width: "100%", padding: 14, borderRadius: T.radius, background: T.surface, border: `1px solid ${T.border}`, color: T.text, fontSize: 15, fontWeight: 600, cursor: "pointer", marginBottom: 8 }}>
                            {saving ? "Saving..." : "Save as Draft"}
                        </button>
                        <button disabled={saving || !programId || !startDate || !endDate || selectedMentees.length === 0}
                            onClick={() => handleSave(true)}
                            style={{ width: "100%", padding: 14, borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 15, fontWeight: 600, cursor: "pointer", opacity: selectedMentees.length === 0 ? 0.5 : 1 }}>
                            {saving ? "Starting..." : "Save & Start Class"}
                        </button>
                        {selectedMentees.length === 0 && (
                            <div style={{ textAlign: "center", fontSize: 12, color: T.textMuted, marginTop: 6 }}>Add at least one mentee to start immediately</div>
                        )}
                    </div>
                )}
            </div>

            {/* Footer navigation */}
            {step < 4 && (
                <div style={{ padding: "12px 16px", background: T.surface, borderTop: `1px solid ${T.borderLight}`, display: "flex", gap: 10 }}>
                    {step > 1 && (
                        <button onClick={() => setStep(s => s - 1)}
                            style={{ flex: 1, padding: 12, borderRadius: T.radius, background: T.surface, border: `1px solid ${T.border}`, color: T.text, fontSize: 15, cursor: "pointer" }}>
                            Back
                        </button>
                    )}
                    {step === 3 ? (
                        <button onClick={() => setStep(4)}
                            style={{ flex: 2, padding: 12, borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 15, fontWeight: 600, cursor: "pointer" }}>
                            {selectedMentees.length > 0 ? "Continue" : "Skip & Continue"}
                        </button>
                    ) : (
                        <button onClick={() => setStep(s => s + 1)}
                            disabled={step === 1 && (!programId || !startDate || !endDate)}
                            style={{ flex: 2, padding: 12, borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 15, fontWeight: 600, cursor: "pointer", opacity: (step === 1 && (!programId || !startDate || !endDate)) ? 0.5 : 1 }}>
                            Continue
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
