import { useState } from "react";
import { T } from "../constants.js";
import { Avatar } from "../components/shared-components.jsx";

export function ProfileScreen({ user, assessments, onUpdateUser, onLogout }) {
  const [editing,    setEditing]    = useState(false);
  const [changingPw, setChangingPw] = useState(false);
  const [form,       setForm]       = useState({ name: user.name, phone: user.phone || "", county: user.county || "", facility: user.facility || "" });
  const [pw,         setPw]         = useState({ current: "", newPw: "", confirm: "" });
  const [msg,        setMsg]        = useState(null);

  const flash = (type, text) => { setMsg({ type, text }); setTimeout(() => setMsg(null), 3000); };

  const handleSaveProfile = () => {
    // PRODUCTION: await api.profile.update(form);
    onUpdateUser({ ...user, ...form });
    setEditing(false);
    flash("success", "Profile updated successfully.");
  };

  const handleChangePassword = () => {
    if (!pw.current)             return flash("error", "Enter your current password.");
    if (pw.newPw.length < 8)     return flash("error", "Password must be at least 8 characters.");
    if (pw.newPw !== pw.confirm) return flash("error", "Passwords do not match.");
    // PRODUCTION: await api.profile.changePassword({ current_password: pw.current, password: pw.newPw, password_confirmation: pw.confirm });
    if (pw.current !== "password123") return flash("error", "Current password is incorrect.");
    onUpdateUser({ ...user, password: pw.newPw });
    setChangingPw(false);
    setPw({ current: "", newPw: "", confirm: "" });
    flash("success", "Password changed successfully.");
  };

  const completed = (assessments || []).filter(a => a.status === "completed");
  const avgScore  = completed.length ? Math.round(completed.reduce((s, a) => s + (a.overall_percentage || 0), 0) / completed.length) : 0;

  return (
    <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
      <div style={{ background: "linear-gradient(135deg, #1E1B4B 0%, #3730A3 100%)", padding: "52px 20px 32px", textAlign: "center" }}>
        <Avatar initials={user.initials || "??"} size={72} color="rgba(255,255,255,0.2)" />
        <div style={{ color: "white", fontSize: 20, fontWeight: 800, marginTop: 12 }}>{user.name}</div>
        <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 4 }}>{user.role} · {user.email}</div>
        <div style={{ display: "flex", justifyContent: "center", gap: 24, marginTop: 16 }}>
          {[{ label: "Assessments", value: (assessments || []).length }, { label: "Completed", value: completed.length }, { label: "Avg Score", value: completed.length ? avgScore + "%" : "N/A" }].map(s => (
            <div key={s.label} style={{ textAlign: "center" }}>
              <div style={{ color: "white", fontSize: 20, fontWeight: 800 }}>{s.value}</div>
              <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 11 }}>{s.label}</div>
            </div>
          ))}
        </div>
      </div>

      <div style={{ padding: "16px 16px 100px" }}>
        {msg && (
          <div style={{ background: msg.type === "success" ? "#D1FAE5" : "#FEE2E2", color: msg.type === "success" ? "#065F46" : "#991B1B", borderRadius: T.radiusSm, padding: "10px 14px", fontSize: 13, marginBottom: 14 }}>{msg.text}</div>
        )}

        <div style={{ background: T.card, borderRadius: T.radius, padding: 16, marginBottom: 14, boxShadow: T.shadow }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 14 }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid }}>Personal Information</div>
            <button onClick={() => setEditing(!editing)} style={{ background: editing ? "#FEE2E2" : T.borderLight, color: editing ? "#EF4444" : T.textMid, border: "none", borderRadius: 7, padding: "5px 12px", fontSize: 12, fontWeight: 700, cursor: "pointer" }}>{editing ? "Cancel" : "Edit"}</button>
          </div>
          {editing ? (
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              {[{ key: "name", label: "Full Name" }, { key: "phone", label: "Phone" }, { key: "county", label: "County" }, { key: "facility", label: "Facility" }].map(({ key, label }) => (
                <div key={key}>
                  <div style={{ fontSize: 11, color: T.textMuted, fontWeight: 600, marginBottom: 5 }}>{label}</div>
                  <input value={form[key]} onChange={e => setForm(p => ({ ...p, [key]: e.target.value }))} style={{ width: "100%", padding: "10px 13px", borderRadius: T.radiusSm, border: "2px solid " + T.border, fontSize: 14, color: T.text, outline: "none", boxSizing: "border-box", fontFamily: "inherit", background: T.borderLight }} />
                </div>
              ))}
              <button onClick={handleSaveProfile} style={{ padding: "12px", borderRadius: T.radiusSm, background: "linear-gradient(135deg, #064E3B, #059669)", color: "white", border: "none", fontSize: 14, fontWeight: 700, cursor: "pointer" }}>Save Changes</button>
            </div>
          ) : (
            [{ label: "Full Name", value: user.name }, { label: "Email", value: user.email }, { label: "Phone", value: user.phone || "—" }, { label: "Role", value: user.role }, { label: "County", value: user.county || "—" }, { label: "Facility", value: user.facility || "—" }].map(({ label, value }) => (
              <div key={label} style={{ display: "flex", justifyContent: "space-between", padding: "9px 0", borderBottom: "1px solid " + T.borderLight }}>
                <span style={{ fontSize: 13, color: T.textSub }}>{label}</span>
                <span style={{ fontSize: 13, fontWeight: 600, color: T.text, textAlign: "right", maxWidth: "55%" }}>{value}</span>
              </div>
            ))
          )}
        </div>

        <div style={{ background: T.card, borderRadius: T.radius, padding: 16, marginBottom: 14, boxShadow: T.shadow }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: changingPw ? 14 : 0 }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid }}>Security</div>
            <button onClick={() => setChangingPw(!changingPw)} style={{ background: changingPw ? "#FEE2E2" : T.borderLight, color: changingPw ? "#EF4444" : T.textMid, border: "none", borderRadius: 7, padding: "5px 12px", fontSize: 12, fontWeight: 700, cursor: "pointer" }}>{changingPw ? "Cancel" : "Change Password"}</button>
          </div>
          {changingPw && (
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              {[{ key: "current", label: "Current Password" }, { key: "newPw", label: "New Password" }, { key: "confirm", label: "Confirm New Password" }].map(({ key, label }) => (
                <div key={key}>
                  <div style={{ fontSize: 11, color: T.textMuted, fontWeight: 600, marginBottom: 5 }}>{label}</div>
                  <input type="password" value={pw[key]} onChange={e => setPw(p => ({ ...p, [key]: e.target.value }))} style={{ width: "100%", padding: "10px 13px", borderRadius: T.radiusSm, border: "2px solid " + T.border, fontSize: 14, color: T.text, outline: "none", boxSizing: "border-box", fontFamily: "inherit", background: T.borderLight }} />
                </div>
              ))}
              <button onClick={handleChangePassword} style={{ padding: "12px", borderRadius: T.radiusSm, background: "#1D4ED8", color: "white", border: "none", fontSize: 14, fontWeight: 700, cursor: "pointer" }}>Update Password</button>
            </div>
          )}
        </div>

        <button onClick={onLogout} style={{ width: "100%", padding: 14, borderRadius: T.radius, background: "#FEE2E2", color: "#EF4444", border: "none", fontSize: 15, fontWeight: 700, cursor: "pointer" }}>Sign Out</button>
      </div>
    </div>
  );
}
