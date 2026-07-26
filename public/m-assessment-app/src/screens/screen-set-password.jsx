import { useState } from "react";
import { T } from "../constants.js";
import { Field, inputStyle } from "./screen-mentorship-form.jsx";
import api from "../services/api.service.js";

function PasswordToggleButton({ show, onClick }) {
    return (
        <button type="button" onClick={onClick} aria-label={show ? "Hide password" : "Show password"} style={{
            position: "absolute", right: 4, top: "50%", transform: "translateY(-50%)",
            width: 36, height: 36, display: "flex", alignItems: "center", justifyContent: "center",
            background: "none", border: "none", padding: 0, cursor: "pointer", color: T.textMuted,
        }}>
            {show ? (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 012.132-3.542m3.42-2.88A9.958 9.958 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.965 9.965 0 01-4.132 5.411M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 3l18 18"/>
                </svg>
            ) : (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            )}
        </button>
    );
}

export function SetPasswordScreen({ mode, target, onDone, onBack }) {
    const [password, setPassword]         = useState("");
    const [confirmPassword, setConfirmPw] = useState("");
    const [error, setError]               = useState("");
    const [saving, setSaving]             = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm]   = useState(false);

    const isVerify = mode === "verify-account";

    const handleSubmit = async () => {
        setError("");
        if (password.length < 8) { setError("Password must be at least 8 characters."); return; }
        if (isVerify && (!/[A-Z]/.test(password) || !/[0-9]/.test(password))) {
            setError("Password must contain at least one uppercase letter and one number.");
            return;
        }
        if (password !== confirmPassword) { setError("Passwords do not match."); return; }

        setSaving(true);
        try {
            if (isVerify) {
                const data = await api.auth.verifyAccount(
                    target.userId, target.expires, target.signature, password, confirmPassword
                );
                onDone(data?.user ?? null);
            } else {
                await api.auth.resetPassword(target.token, target.email, password, confirmPassword);
                onDone(null);
            }
        } catch (e) {
            setError(e.message || "Something went wrong. The link may have expired — please request a new one.");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <div style={{ background: T.gradientHero, padding: "44px 28px 32px", borderRadius: "28px", margin: "calc(6px + env(safe-area-inset-top, 0px)) 6px 0" }}>
                <div style={{ color: "white", fontSize: 22, fontWeight: 800 }}>
                    {isVerify ? "Set Your Password" : "Choose a New Password"}
                </div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 14, marginTop: 4 }}>
                    {isVerify
                        ? "Welcome to MNCH Kenya — create a password to activate your account."
                        : "Enter a new password for your account."}
                </div>
            </div>

            <div style={{ flex: 1, padding: "28px 20px", overflowY: "auto" }}>
                {error && (
                    <div style={{ background: "#FEF2F2", color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "12px 16px", fontSize: 13, marginBottom: 18, border: "1px solid #FECACA" }}>
                        {error}
                    </div>
                )}

                <Field label="New Password" required hint={isVerify ? "At least 8 characters, one uppercase letter, one number." : "At least 8 characters."}>
                    <div style={{ position: "relative" }}>
                        <input type={showPassword ? "text" : "password"} value={password} onChange={e => setPassword(e.target.value)}
                            style={{ ...inputStyle, paddingRight: 44 }} placeholder="Enter new password" />
                        <PasswordToggleButton show={showPassword} onClick={() => setShowPassword(v => !v)} />
                    </div>
                </Field>
                <Field label="Confirm Password" required>
                    <div style={{ position: "relative" }}>
                        <input type={showConfirm ? "text" : "password"} value={confirmPassword} onChange={e => setConfirmPw(e.target.value)}
                            style={{ ...inputStyle, paddingRight: 44 }} placeholder="Re-enter new password" />
                        <PasswordToggleButton show={showConfirm} onClick={() => setShowConfirm(v => !v)} />
                    </div>
                </Field>

                <button onClick={handleSubmit} disabled={saving} style={{
                    width: "100%", padding: 15, borderRadius: T.radiusSm, border: "none",
                    background: saving ? T.borderLight : T.gradientPrimary,
                    color: saving ? T.textMuted : "white", fontSize: 15, fontWeight: 700,
                    cursor: saving ? "not-allowed" : "pointer", marginTop: 8,
                }}>
                    {saving ? "Saving…" : "Set Password"}
                </button>

                {onBack && (
                    <button type="button" onClick={onBack} style={{
                        background: "none", border: "none", padding: 0, textAlign: "center",
                        marginTop: 16, fontSize: 13, color: T.primary, fontWeight: 600,
                        cursor: "pointer", width: "100%",
                    }}>
                        Back to Login
                    </button>
                )}
            </div>
        </div>
    );
}
