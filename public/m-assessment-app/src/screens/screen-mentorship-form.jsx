<<<<<<< HEAD
import { useState, useEffect, useRef } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const inputStyle = {
    display: "block", width: "100%", padding: "11px 13px",
    borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
    fontSize: 14, background: "#fff", color: T.text,
    fontFamily: "inherit", outline: "none", boxSizing: "border-box",
};

const selectStyle = {
    ...inputStyle,
    appearance: "none", WebkitAppearance: "none",
    backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E")`,
    backgroundRepeat: "no-repeat", backgroundPosition: "right 12px center",
    paddingRight: 36,
};

function Field({ label, required, hint, children }) {
    return (
        <div style={{ marginBottom: 16 }}>
            <label style={{ display: "block", fontSize: 12, fontWeight: 700, color: T.textSub, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>
                {label}{required && <span style={{ color: "#EF4444", marginLeft: 2 }}>*</span>}
            </label>
            {children}
            {hint && <div style={{ fontSize: 11, color: T.textMuted, marginTop: 4 }}>{hint}</div>}
        </div>
    );
}

=======
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
export function MentorshipFormScreen({ user, onBack, onCreated }) {
    const [step, setStep] = useState(1);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

<<<<<<< HEAD
    // ── Step 1: Setup ──────────────────────────────────────────────────────
    const [programs, setPrograms]         = useState([]);
    const [programId, setProgramId]       = useState("");

    // County → Facility cascade
    const [counties, setCounties]         = useState([]);
    const [countyId, setCountyId]         = useState(user?.county_id ? String(user.county_id) : "");
    const [facilities, setFacilities]     = useState([]);
    const [facilityId, setFacilityId]     = useState(user?.facility_id ? String(user.facility_id) : "");
    const [facilityLabel, setFacilityLabel] = useState(user?.facility ?? "");
    const [facilitiesLoading, setFacilitiesLoading] = useState(false);

    const [startDate, setStartDate]           = useState("");
    const [endDate, setEndDate]               = useState("");
    const [maxParticipants, setMaxParticipants] = useState(20);

    // ── Step 2: Modules ────────────────────────────────────────────────────
    const [availableModules, setAvailableModules]   = useState([]);
    const [selectedModuleIds, setSelectedModuleIds] = useState([]);
    const [modulesLoading, setModulesLoading]       = useState(false);

    // ── Step 3: Mentees ────────────────────────────────────────────────────
    const [menteeSearch, setMenteeSearch]     = useState("");
    const [menteeResults, setMenteeResults]   = useState([]);
    const [selectedMentees, setSelectedMentees] = useState([]);
    const [menteeSearching, setMenteeSearching] = useState(false);
    const searchTimer = useRef(null);

    // ── Initial data load ──────────────────────────────────────────────────
    useEffect(() => {
        api.lookups.programs()
            .then(d => setPrograms(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []))
            .catch(() => {});
        api.lookups.counties()
            .then(d => setCounties(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []))
            .catch(() => {});
    }, []);

    // Load facilities when county changes
    useEffect(() => {
        if (!countyId) { setFacilities([]); setFacilityId(""); setFacilityLabel(""); return; }
        setFacilitiesLoading(true);
        api.lookups.facilitiesByCounty(countyId)
            .then(d => {
                const list = Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [];
                setFacilities(list);
                // If user's facility is in this county, keep it pre-selected
                const match = list.find(f => String(f.id) === String(user?.facility_id));
                if (match && String(countyId) === String(user?.county_id)) {
                    setFacilityId(String(match.id));
                    setFacilityLabel(match.label);
                } else {
                    // County changed — clear selection unless only one facility
                    if (list.length === 1) {
                        setFacilityId(String(list[0].id));
                        setFacilityLabel(list[0].label);
                    } else {
                        setFacilityId("");
                        setFacilityLabel("");
                    }
                }
            })
            .catch(() => setFacilities([]))
            .finally(() => setFacilitiesLoading(false));
    }, [countyId]);

    // Load modules when program changes
    useEffect(() => {
        if (!programId) { setAvailableModules([]); return; }
        setModulesLoading(true);
        api.lookups.programModules(programId)
            .then(d => setAvailableModules(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []))
            .catch(() => setAvailableModules([]))
            .finally(() => setModulesLoading(false));
    }, [programId]);

    // Debounced mentee search
    useEffect(() => {
        clearTimeout(searchTimer.current);
        if (menteeSearch.trim().length < 2) { setMenteeResults([]); return; }
        searchTimer.current = setTimeout(() => {
            setMenteeSearching(true);
            api.lookups.userSearch(menteeSearch.trim(), facilityId || null)
                .then(d => {
                    const list = Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [];
                    setMenteeResults(list.filter(u => !selectedMentees.find(s => s.id === u.id)));
                })
                .catch(() => setMenteeResults([]))
                .finally(() => setMenteeSearching(false));
        }, 400);
        return () => clearTimeout(searchTimer.current);
    }, [menteeSearch]);

    const toggleModule = (id) =>
        setSelectedModuleIds(prev => prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]);
=======
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
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5

    const addMentee = (u) => {
        setSelectedMentees(prev => [...prev, u]);
        setMenteeResults(prev => prev.filter(r => r.id !== u.id));
        setMenteeSearch("");
    };

    const removeMentee = (id) => setSelectedMentees(prev => prev.filter(u => u.id !== id));

<<<<<<< HEAD
    const step1Valid = programId && countyId && facilityId && startDate && endDate;

    const handleSave = async (startNow) => {
        setSaving(true); setError(null);
        try {
            const res = await api.mentorshipCreate.create({
                program_id:       parseInt(programId),
                facility_id:      parseInt(facilityId),
                county_id:        parseInt(countyId),
                start_date:       startDate,
                end_date:         endDate,
                max_participants: maxParticipants,
                module_ids:       selectedModuleIds,
            });
            const newTraining = res?.data ?? res;

=======
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
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
            if (selectedMentees.length > 0 && newTraining?.class?.id) {
                for (const mentee of selectedMentees) {
                    await api.classLifecycle.enrollMentee(newTraining.class.id, mentee.id).catch(() => {});
                }
            }

<<<<<<< HEAD
=======
            // Optionally start the class
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
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

<<<<<<< HEAD
    const stepLabels = ["Setup", "Modules", "Mentees", "Review"];
    const selectedProgram = programs.find(p => String(p.id) === String(programId));

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* ── Gradient Header ── */}
            <div style={{
                background: "linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)",
                padding: "40px 16px 0",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 140, height: 140, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -30, right: -20 }} />
                <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 14 }}>
                    <button onClick={onBack} style={{ background: "rgba(255,255,255,0.12)", border: "none", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                    </button>
                    <div style={{ fontWeight: 800, fontSize: 17, color: "white" }}>New Mentorship</div>
                </div>

                {/* Step progress */}
                <div style={{ display: "flex", gap: 4, paddingBottom: 14 }}>
                    {stepLabels.map((label, i) => (
                        <div key={i} style={{ flex: 1, textAlign: "center" }}>
                            <div style={{
                                height: 3, borderRadius: 2, marginBottom: 4,
                                background: step > i + 1 ? "rgba(255,255,255,0.9)"
                                    : step === i + 1 ? "rgba(255,255,255,0.6)"
                                    : "rgba(255,255,255,0.15)",
                            }} />
                            <span style={{ fontSize: 10, fontWeight: step === i + 1 ? 700 : 400, color: step === i + 1 ? "rgba(255,255,255,0.9)" : "rgba(255,255,255,0.4)" }}>
                                {label}
                            </span>
=======
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
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                        </div>
                    ))}
                </div>
            </div>

<<<<<<< HEAD
            {/* ── Content ── */}
            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 14, color: "#DC2626", fontSize: 13 }}>
=======
            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radius, padding: 12, marginBottom: 12, color: "#DC2626", fontSize: 13 }}>
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                        {error}
                    </div>
                )}

<<<<<<< HEAD
                {/* ── Step 1: Setup ── */}
                {step === 1 && (
                    <div>
                        {/* Location card */}
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "14px 14px 2px", boxShadow: T.shadowCard, marginBottom: 12 }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 12, display: "flex", alignItems: "center", gap: 6 }}>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                Location
                            </div>

                            <Field label="County" required hint="Select county to load facilities">
                                <select
                                    value={countyId}
                                    onChange={e => setCountyId(e.target.value)}
                                    style={selectStyle}
                                >
                                    <option value="">Select county…</option>
                                    {counties.map(c => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Facility" required hint={!countyId ? "Select a county first" : facilitiesLoading ? "Loading facilities…" : `${facilities.length} facilities in this county`}>
                                <select
                                    value={facilityId}
                                    onChange={e => {
                                        const f = facilities.find(f => String(f.id) === e.target.value);
                                        setFacilityId(e.target.value);
                                        setFacilityLabel(f?.label ?? "");
                                    }}
                                    disabled={!countyId || facilitiesLoading}
                                    style={{ ...selectStyle, opacity: (!countyId || facilitiesLoading) ? 0.5 : 1 }}
                                >
                                    <option value="">Select facility…</option>
                                    {facilities.map(f => (
                                        <option key={f.id} value={f.id}>{f.label}</option>
                                    ))}
                                </select>
                            </Field>
                        </div>

                        {/* Program & Schedule card */}
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "14px 14px 2px", boxShadow: T.shadowCard }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 12, display: "flex", alignItems: "center", gap: 6 }}>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Program & Schedule
                            </div>

                            <Field label="Mentorship Program" required hint="e.g. Newborn Care or Infant & Child Care">
                                <select
                                    value={programId}
                                    onChange={e => { setProgramId(e.target.value); setSelectedModuleIds([]); }}
                                    style={selectStyle}
                                >
                                    <option value="">Select program…</option>
                                    {programs.map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                            </Field>

                            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                                <Field label="Start Date" required>
                                    <input
                                        type="date"
                                        value={startDate}
                                        min={new Date().toISOString().split("T")[0]}
                                        onChange={e => { setStartDate(e.target.value); if (endDate && e.target.value > endDate) setEndDate(""); }}
                                        style={inputStyle}
                                    />
                                </Field>
                                <Field label="End Date" required>
                                    <input
                                        type="date"
                                        value={endDate}
                                        min={startDate || new Date().toISOString().split("T")[0]}
                                        onChange={e => setEndDate(e.target.value)}
                                        style={inputStyle}
                                    />
                                </Field>
                            </div>

                            <Field label="Number of Mentees" hint="Recommended minimum: 2. No maximum limit enforced.">
                                <input
                                    type="number"
                                    value={maxParticipants}
                                    min={1}
                                    onChange={e => setMaxParticipants(parseInt(e.target.value) || 20)}
                                    style={inputStyle}
                                />
                            </Field>
                        </div>
                    </div>
                )}

                {/* ── Step 2: Modules ── */}
                {step === 2 && (
                    <div>
                        <div style={{ fontSize: 13, color: T.textSub, marginBottom: 12, lineHeight: 1.6 }}>
                            Select modules to include. Sessions are auto-created from program templates.
                        </div>
                        {modulesLoading && (
                            <div style={{ textAlign: "center", color: T.textSub, padding: 32 }}>Loading modules…</div>
                        )}
                        {!modulesLoading && availableModules.length === 0 && (
                            <div style={{ textAlign: "center", color: T.textMuted, padding: 32 }}>
                                {programId ? "No modules available for this program." : "Select a program in Step 1 first."}
=======
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
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                            </div>
                        )}
                        {availableModules.map(m => {
                            const selected = selectedModuleIds.includes(m.id);
                            return (
<<<<<<< HEAD
                                <div
                                    key={m.id}
                                    onClick={() => toggleModule(m.id)}
                                    style={{
                                        display: "flex", alignItems: "flex-start", gap: 12,
                                        padding: "14px 16px", marginBottom: 8, cursor: "pointer",
                                        borderRadius: T.radiusSm, boxShadow: T.shadowCard,
                                        border: `1px solid ${selected ? "#6366F1" : T.border}`,
                                        background: selected ? "rgba(99,102,241,0.05)" : T.card,
                                        transition: "border-color 0.15s",
                                    }}
                                >
                                    <div style={{
                                        width: 22, height: 22, borderRadius: 6, flexShrink: 0, marginTop: 1,
                                        border: `2px solid ${selected ? "#6366F1" : T.border}`,
                                        background: selected ? "#6366F1" : "#fff",
                                        display: "flex", alignItems: "center", justifyContent: "center",
                                        transition: "background 0.15s",
                                    }}>
                                        {selected && (
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        )}
                                    </div>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ fontSize: 14, fontWeight: 600, color: T.text }}>
                                            {m.order_sequence}. {m.name}
                                        </div>
                                        {m.description && (
                                            <div style={{ fontSize: 12, color: T.textSub, marginTop: 2, lineHeight: 1.5 }}>{m.description}</div>
                                        )}
                                        {m.session_count > 0 && (
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 3 }}>
                                                {m.session_count} session{m.session_count !== 1 ? "s" : ""}
                                            </div>
                                        )}
=======
                                <div key={m.id} onClick={() => toggleModule(m.id)}
                                    style={{ display: "flex", alignItems: "center", gap: 12, padding: "12px 14px", marginBottom: 8, borderRadius: T.radius, border: `1px solid ${selected ? T.primary : T.border}`, background: selected ? T.primaryLight + "20" : T.surface, cursor: "pointer" }}>
                                    <div style={{ width: 20, height: 20, borderRadius: 4, border: `2px solid ${selected ? T.primary : T.border}`, background: selected ? T.primary : "transparent", display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>
                                        {selected && <span style={{ color: "#fff", fontSize: 12 }}>✓</span>}
                                    </div>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{m.name}</div>
                                        {m.session_count > 0 && <div style={{ fontSize: 12, color: T.textMuted }}>{m.session_count} session{m.session_count !== 1 ? "s" : ""}</div>}
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                                    </div>
                                </div>
                            );
                        })}
<<<<<<< HEAD
                        {availableModules.length > 0 && (
                            <div style={{ textAlign: "center", fontSize: 12, color: T.textSub, marginTop: 8 }}>
                                {selectedModuleIds.length} of {availableModules.length} selected
                            </div>
                        )}
                    </div>
                )}

                {/* ── Step 3: Mentees ── */}
                {step === 3 && (
                    <div>
                        {!navigator.onLine && (
                            <div style={{ background: "#FFFBEB", border: "1px solid #FCD34D", borderRadius: T.radiusSm, padding: "12px 14px", marginBottom: 14, display: "flex", gap: 10 }}>
                                <span style={{ fontSize: 18 }}>🔒</span>
                                <div>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: "#92400E" }}>Mobile data required</div>
                                    <div style={{ fontSize: 13, color: "#78350F", marginTop: 2 }}>Turn on mobile data to search mentees. You can skip and enroll them after saving.</div>
                                </div>
                            </div>
                        )}

                        {navigator.onLine && (
                            <div style={{ position: "relative", marginBottom: 12 }}>
                                <input
                                    placeholder="Search by name…"
                                    value={menteeSearch}
                                    onChange={e => setMenteeSearch(e.target.value)}
                                    autoFocus
                                    style={{
                                        width: "100%", padding: "11px 40px 11px 14px",
                                        borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
                                        fontSize: 14, boxSizing: "border-box",
                                        background: "#fff", color: T.text, outline: "none",
                                    }}
                                />
                                <svg style={{ position: "absolute", right: 12, top: "50%", transform: "translateY(-50%)" }}
                                    width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2">
                                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                                </svg>
                            </div>
                        )}

                        {menteeSearching && <div style={{ color: T.textSub, textAlign: "center", padding: 16, fontSize: 13 }}>Searching…</div>}
                        {!menteeSearching && menteeSearch.length >= 2 && menteeResults.length === 0 && (
                            <div style={{ color: T.textSub, textAlign: "center", padding: 16, fontSize: 13 }}>No users found.</div>
                        )}
                        {navigator.onLine && menteeSearch.length < 2 && (
                            <div style={{ color: T.textMuted, textAlign: "center", padding: 16, fontSize: 13 }}>
                                Type at least 2 characters to search
                            </div>
                        )}

                        {menteeResults.map(u => (
                            <div
                                key={u.id}
                                onClick={() => addMentee(u)}
                                style={{
                                    padding: "12px 14px", borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
                                    marginBottom: 8, cursor: "pointer", background: T.card,
                                    display: "flex", alignItems: "center", gap: 12, boxShadow: T.shadowCard,
                                }}
                            >
                                <div style={{
                                    width: 36, height: 36, borderRadius: "50%", background: T.primaryGhost,
                                    display: "flex", alignItems: "center", justifyContent: "center",
                                    fontWeight: 700, fontSize: 13, color: T.primary, flexShrink: 0,
                                }}>
                                    {(u.name ?? "?").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()}
                                </div>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: T.text }}>{u.name}</div>
                                    <div style={{ fontSize: 12, color: T.textSub }}>{u.facility_name}</div>
                                </div>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2.5">
                                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                            </div>
                        ))}

                        {selectedMentees.length > 0 && (
                            <div style={{ marginTop: menteeResults.length > 0 ? 16 : 0 }}>
                                <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 8 }}>
                                    Added ({selectedMentees.length})
                                </div>
                                {selectedMentees.map(u => (
                                    <div key={u.id} style={{
                                        display: "flex", alignItems: "center", gap: 12,
                                        padding: "10px 14px", borderRadius: T.radiusSm,
                                        background: T.primaryGhost, border: `1px solid ${T.border}`,
                                        marginBottom: 6,
                                    }}>
                                        <div style={{
                                            width: 32, height: 32, borderRadius: "50%", background: T.primary,
                                            display: "flex", alignItems: "center", justifyContent: "center",
                                            fontWeight: 700, fontSize: 12, color: "#fff", flexShrink: 0,
                                        }}>
                                            {(u.name ?? "?").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()}
                                        </div>
                                        <span style={{ flex: 1, fontSize: 14, color: T.text, fontWeight: 500 }}>{u.name}</span>
                                        <button
                                            onClick={() => removeMentee(u.id)}
                                            style={{ background: "none", border: "none", color: T.textMuted, fontSize: 18, cursor: "pointer", lineHeight: 1, padding: 4 }}
                                        >×</button>
=======
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
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

<<<<<<< HEAD
                {/* ── Step 4: Review ── */}
                {step === 4 && (
                    <div>
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard, marginBottom: 12 }}>
                            {[
                                ["Program",    selectedProgram?.name ?? "—"],
                                ["County",     counties.find(c => String(c.id) === String(countyId))?.name ?? "—"],
                                ["Facility",   facilityLabel || "—"],
                                ["Start Date", startDate],
                                ["End Date",   endDate],
                                ["Max Mentees", `${maxParticipants}`],
                                ["Modules",    `${selectedModuleIds.length} selected`],
                                ["Mentees",    `${selectedMentees.length} added`],
                            ].map(([label, value]) => (
                                <div key={label} style={{ display: "flex", justifyContent: "space-between", paddingBlock: 8, borderBottom: `1px solid ${T.borderLight}` }}>
                                    <span style={{ fontSize: 12, color: T.textSub }}>{label}</span>
                                    <span style={{ fontSize: 13, fontWeight: 600, color: T.text, textAlign: "right", maxWidth: "60%" }}>{value}</span>
                                </div>
                            ))}
                        </div>

                        <button
                            disabled={saving}
                            onClick={() => handleSave(false)}
                            style={{
                                width: "100%", padding: 14, marginBottom: 10, borderRadius: T.radiusSm,
                                background: T.card, border: `1px solid ${T.border}`,
                                color: T.text, fontSize: 14, fontWeight: 600, cursor: saving ? "not-allowed" : "pointer",
                                opacity: saving ? 0.6 : 1,
                            }}
                        >
                            {saving ? "Saving…" : "Save as Draft"}
                        </button>
                        <button
                            disabled={saving || selectedMentees.length === 0}
                            onClick={() => handleSave(true)}
                            style={{
                                width: "100%", padding: 14, borderRadius: T.radiusSm, border: "none",
                                background: "linear-gradient(135deg, #3730A3, #6366F1)",
                                color: "#fff", fontSize: 14, fontWeight: 700,
                                cursor: (saving || selectedMentees.length === 0) ? "not-allowed" : "pointer",
                                opacity: selectedMentees.length === 0 ? 0.5 : 1,
                                boxShadow: "0 4px 12px rgba(55,48,163,0.3)",
                            }}
                        >
                            {saving ? "Starting…" : "Save & Start Class"}
                        </button>
                        {selectedMentees.length === 0 && (
                            <div style={{ textAlign: "center", fontSize: 12, color: T.textMuted, marginTop: 8 }}>
                                Add at least one mentee in Step 3 to start immediately
                            </div>
=======
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
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                        )}
                    </div>
                )}
            </div>

<<<<<<< HEAD
            {/* ── Footer Nav ── */}
            {step < 4 && (
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.borderLight}`, display: "flex", gap: 10 }}>
                    {step > 1 && (
                        <button
                            onClick={() => setStep(s => s - 1)}
                            style={{
                                flex: 1, padding: 12, borderRadius: T.radiusSm,
                                background: T.bg, border: `1px solid ${T.border}`,
                                color: T.text, fontSize: 14, fontWeight: 600, cursor: "pointer",
                            }}
                        >
                            Back
                        </button>
                    )}
                    <button
                        onClick={() => step === 3 ? setStep(4) : setStep(s => s + 1)}
                        disabled={step === 1 && !step1Valid}
                        style={{
                            flex: 2, padding: 12, borderRadius: T.radiusSm, border: "none",
                            background: "linear-gradient(135deg, #3730A3, #6366F1)",
                            color: "#fff", fontSize: 14, fontWeight: 700,
                            cursor: (step === 1 && !step1Valid) ? "not-allowed" : "pointer",
                            opacity: (step === 1 && !step1Valid) ? 0.5 : 1,
                        }}
                    >
                        {step === 3 ? (selectedMentees.length > 0 ? "Continue" : "Skip & Continue") : "Continue"}
                    </button>
=======
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
>>>>>>> 6110d4f9a08611bc561e3ac5a9f1b325f93a88e5
                </div>
            )}
        </div>
    );
}
