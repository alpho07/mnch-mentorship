import { useState, useEffect } from "react";
import { T } from "../constants.js";
import { Field, SearchableDropdown, inputStyle } from "./screen-mentorship-form.jsx";
import api from "../services/api.service.js";

const ROLE_OPTIONS = [
    { id: "mentee", name: "Mentee" },
    { id: "facility_mentor", name: "Facility Mentor" },
];

export function RegisterScreen({ onRegistered, onBack }) {
    const [firstName, setFirstName]   = useState("");
    const [middleName, setMiddleName] = useState("");
    const [lastName, setLastName]     = useState("");
    const [email, setEmail]           = useState("");
    const [phone, setPhone]           = useState("");
    const [cadreId, setCadreId]       = useState("");
    const [departmentId, setDeptId]   = useState("");
    const [role, setRole]             = useState("mentee");
    const [countyId, setCountyId]     = useState("");
    const [facilityId, setFacilityId] = useState("");

    const [cadres, setCadres]         = useState([]);
    const [departments, setDeptments] = useState([]);
    const [counties, setCounties]     = useState([]);
    const [facilities, setFacilities] = useState([]);
    const [facilitiesLoading, setFacilitiesLoading] = useState(false);

    const [error, setError]     = useState("");
    const [saving, setSaving]   = useState(false);
    const [done, setDone]       = useState(false);

    useEffect(() => {
        api.lookups.cadres().then(d => setCadres(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
        api.lookups.departments().then(d => setDeptments(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
        api.lookups.counties().then(d => setCounties(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
    }, []);

    useEffect(() => {
        if (!countyId) { setFacilities([]); setFacilityId(""); return; }
        setFacilitiesLoading(true);
        api.lookups.facilitiesByCounty(countyId)
            .then(list => {
                const arr = (Array.isArray(list?.data) ? list.data : Array.isArray(list) ? list : [])
                    .map(f => ({ ...f, label: f?.label || (f?.mfl_code ? `${f.mfl_code} - ${f.name}` : f?.name) }))
                    .filter(f => f.id && f.name);
                setFacilities(arr);
                setFacilityId("");
            })
            .catch(() => setFacilities([]))
            .finally(() => setFacilitiesLoading(false));
    }, [countyId]);

    const valid = firstName.trim() && lastName.trim() && email.trim() && phone.trim()
        && cadreId && departmentId && role && countyId && facilityId;

    const handleSubmit = async () => {
        setError("");
        if (!valid) { setError("Please fill in all required fields."); return; }
        setSaving(true);
        try {
            await api.auth.register({
                first_name: firstName.trim(),
                middle_name: middleName.trim() || null,
                last_name: lastName.trim(),
                email: email.trim(),
                phone: phone.trim(),
                cadre_id: parseInt(cadreId),
                department_id: parseInt(departmentId),
                role,
                county_id: parseInt(countyId),
                facility_id: parseInt(facilityId),
            });
            setDone(true);
        } catch (e) {
            setError(e.message || "Registration failed. Please check your details and try again.");
        } finally {
            setSaving(false);
        }
    };

    if (done) {
        return (
            <div style={{ display: "flex", flexDirection: "column", height: "100%", alignItems: "center",
                justifyContent: "center", padding: 32, textAlign: "center", background: T.bg }}>
                <div style={{ fontSize: 40, marginBottom: 12 }}>📬</div>
                <div style={{ fontSize: 18, fontWeight: 800, color: T.text, marginBottom: 8 }}>Check your email</div>
                <div style={{ fontSize: 14, color: T.textSub, marginBottom: 28, lineHeight: 1.6 }}>
                    We've sent a verification link to <strong>{email.trim()}</strong>. Open it on this device to set your password and activate your account.
                </div>
                <button onClick={onRegistered} style={{
                    padding: "12px 28px", background: T.gradientPrimary, color: "white", border: "none",
                    borderRadius: 12, fontWeight: 600, fontSize: 15, cursor: "pointer",
                }}>
                    Back to Login
                </button>
            </div>
        );
    }

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <div style={{ background: T.gradientHero, padding: "40px 20px 20px", borderRadius: "0 0 28px 28px", margin: "0 6px" }}>
                <button onClick={onBack} style={{ background: "rgba(255,255,255,0.15)", border: "none", cursor: "pointer",
                    padding: "6px 10px", borderRadius: 10, marginBottom: 12, color: "white", fontSize: 12, fontWeight: 600 }}>
                    ← Back
                </button>
                <div style={{ color: "white", fontSize: 22, fontWeight: 800 }}>Create Account</div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 4 }}>Join the MNCH Mentorship Platform</div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: "20px 20px 0" }}>
                {error && (
                    <div style={{ background: "#FEF2F2", color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "12px 16px", fontSize: 13, marginBottom: 16, border: "1px solid #FECACA" }}>
                        {error}
                    </div>
                )}

                <Field label="First Name" required><input value={firstName} onChange={e => setFirstName(e.target.value)} style={inputStyle} /></Field>
                <Field label="Middle Name"><input value={middleName} onChange={e => setMiddleName(e.target.value)} style={inputStyle} /></Field>
                <Field label="Last Name" required><input value={lastName} onChange={e => setLastName(e.target.value)} style={inputStyle} /></Field>
                <Field label="Email Address" required><input type="email" value={email} onChange={e => setEmail(e.target.value)} style={inputStyle} /></Field>
                <Field label="Phone Number" required><input type="tel" value={phone} onChange={e => setPhone(e.target.value)} style={inputStyle} /></Field>

                <Field label="Cadre" required>
                    <SearchableDropdown options={cadres} value={cadreId} onChange={setCadreId} placeholder="Select cadre..." searchPlaceholder="Search cadre..." />
                </Field>
                <Field label="Department" required>
                    <SearchableDropdown options={departments} value={departmentId} onChange={setDeptId} placeholder="Select department..." searchPlaceholder="Search department..." />
                </Field>
                <Field label="Role" required>
                    <SearchableDropdown options={ROLE_OPTIONS} value={role} onChange={setRole} placeholder="Select role..." />
                </Field>
                <Field label="County" required hint="Select county to load facilities">
                    <SearchableDropdown options={counties} value={countyId} onChange={setCountyId} placeholder="Select county..." searchPlaceholder="Search county..." />
                </Field>
                <Field label="Facility" required hint={!countyId ? "Select a county first" : facilitiesLoading ? "Loading facilities…" : undefined}>
                    <SearchableDropdown
                        options={facilities} value={facilityId} onChange={setFacilityId}
                        disabled={!countyId || facilitiesLoading}
                        getLabel={f => f.label ?? f.name}
                        placeholder={facilitiesLoading ? "Loading facilities..." : "Select facility..."}
                        searchPlaceholder="Search facility or MFL..."
                    />
                </Field>
            </div>

            <div style={{ padding: "12px 20px", paddingBottom: "calc(12px + env(safe-area-inset-bottom, 0px))", background: T.card, borderTop: `1px solid ${T.borderLight}` }}>
                <button onClick={handleSubmit} disabled={saving || !valid} style={{
                    width: "100%", padding: 14, borderRadius: T.radiusSm, border: "none",
                    background: (saving || !valid) ? T.borderLight : T.gradientPrimary,
                    color: (saving || !valid) ? T.textMuted : "white", fontSize: 15, fontWeight: 700,
                    cursor: (saving || !valid) ? "not-allowed" : "pointer",
                }}>
                    {saving ? "Registering…" : "Register"}
                </button>
            </div>
        </div>
    );
}
