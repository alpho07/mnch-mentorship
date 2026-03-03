import { T, GRADE_COLOR, GRADE_BG, GRADE_TEXT, GRADE_LABEL } from "../constants.js";

// ─── Avatar ───────────────────────────────────────────────────────────────────
export function Avatar({ initials, size = 40, color = "#064E3B" }) {
  return (
    <div style={{
      width: size, height: size, borderRadius: "50%",
      background: `linear-gradient(135deg, ${color}, ${color}CC)`,
      display: "flex", alignItems: "center", justifyContent: "center",
      color: "white", fontWeight: 800, fontSize: size * 0.32, flexShrink: 0,
    }}>{initials}</div>
  );
}

// ─── GradeBadge ───────────────────────────────────────────────────────────────
export function GradeBadge({ grade, pct }) {
  if (!grade) return null;
  return (
    <span style={{
      background: GRADE_BG[grade], color: GRADE_TEXT[grade],
      borderRadius: 6, padding: "3px 9px", fontSize: 12, fontWeight: 700,
      whiteSpace: "nowrap",
    }}>
      {GRADE_LABEL[grade]} · {pct}%
    </span>
  );
}

// ─── StatusChip ───────────────────────────────────────────────────────────────
export function StatusChip({ status }) {
  const cfg = {
    completed:   { bg: "#D1FAE5", color: "#065F46", label: "Completed" },
    in_progress: { bg: "#DBEAFE", color: "#1E40AF", label: "In Progress" },
    draft:       { bg: "#F3F4F6", color: "#374151", label: "Draft" },
  }[status] || { bg: "#F3F4F6", color: "#374151", label: status };

  return (
    <span style={{
      background: cfg.bg, color: cfg.color,
      borderRadius: 6, padding: "3px 9px", fontSize: 11, fontWeight: 700,
    }}>{cfg.label}</span>
  );
}

// ─── BackButton ───────────────────────────────────────────────────────────────
export function BackButton({ onBack, light }) {
  return (
    <button onClick={onBack} style={{
      background: light ? "rgba(255,255,255,0.2)" : T.borderLight,
      border: "none", borderRadius: T.radiusSm,
      padding: "7px 13px", cursor: "pointer",
      color: light ? "white" : T.textMid,
      fontSize: 13, fontWeight: 600,
      display: "flex", alignItems: "center", gap: 4,
    }}>← Back</button>
  );
}

// ─── ProgressBar ─────────────────────────────────────────────────────────────
export function ProgressBar({ pct, color, height = 5 }) {
  return (
    <div style={{ height, background: "#E5E7EB", borderRadius: 999, overflow: "hidden" }}>
      <div style={{
        width: `${Math.min(pct, 100)}%`, height: "100%",
        background: color || "#059669", borderRadius: 999,
        transition: "width 0.4s",
      }} />
    </div>
  );
}

// ─── BottomNav ────────────────────────────────────────────────────────────────
export function BottomNav({ active, onChange }) {
  const tabs = [
    { id: "dashboard",   icon: "🏠",  label: "Home" },
    { id: "assessments", icon: "📋",  label: "Assessments" },
    { id: "new",         icon: "➕",  label: "New", special: true },
    { id: "reports",     icon: "📊",  label: "Reports" },
    { id: "profile",     icon: "👤",  label: "Profile" },
  ];

  return (
    <div style={{
      position: "absolute", bottom: 0, left: 0, right: 0,
      background: "white", borderTop: `1px solid ${T.border}`,
      display: "flex", paddingBottom: 8,
      boxShadow: "0 -4px 20px rgba(0,0,0,0.08)",
    }}>
      {tabs.map((t) => (
        <button key={t.id} onClick={() => onChange(t.id)} style={{
          flex: 1, border: "none", background: "none",
          cursor: "pointer", padding: "10px 0 4px",
          display: "flex", flexDirection: "column", alignItems: "center", gap: 3,
        }}>
          {t.special ? (
            <div style={{
              width: 44, height: 44, borderRadius: "50%", marginTop: -22,
              background: "linear-gradient(135deg, #064E3B, #059669)",
              display: "flex", alignItems: "center", justifyContent: "center",
              fontSize: 22, boxShadow: "0 4px 12px rgba(6,78,59,0.4)",
              border: "3px solid white",
            }}>{t.icon}</div>
          ) : (
            <>
              <span style={{ fontSize: 20 }}>{t.icon}</span>
              <span style={{
                fontSize: 10, fontWeight: active === t.id ? 700 : 400,
                color: active === t.id ? "#059669" : "#9CA3AF",
              }}>{t.label}</span>
            </>
          )}
        </button>
      ))}
    </div>
  );
}

// ─── Phone Shell (wraps all screens) ─────────────────────────────────────────
export function PhoneShell({ children }) {
  return (
    <div style={{
      display: "flex", alignItems: "center", justifyContent: "center",
      minHeight: "100vh",
      background: "radial-gradient(ellipse at 20% 50%, #D1FAE5 0%, #E0F2FE 40%, #EDE9FE 100%)",
      fontFamily: "'Segoe UI', -apple-system, system-ui, sans-serif",
      padding: 16,
    }}>
      <div style={{
        width: 390, height: 820, borderRadius: 50, overflow: "hidden",
        position: "relative", background: "#F0F4F8",
        boxShadow: "0 50px 100px rgba(0,0,0,0.25), 0 0 0 1px rgba(0,0,0,0.06), inset 0 0 0 2px rgba(255,255,255,0.6)",
      }}>
        {/* Dynamic island */}
        <div style={{
          position: "absolute", top: 10, left: "50%", transform: "translateX(-50%)",
          width: 120, height: 34, background: "#0A0A0A", borderRadius: 20, zIndex: 200,
        }} />
        {children}
        {/* Home indicator */}
        <div style={{
          position: "absolute", bottom: 6, left: "50%", transform: "translateX(-50%)",
          width: 130, height: 5, background: "rgba(0,0,0,0.15)", borderRadius: 999, zIndex: 300,
        }} />
      </div>
    </div>
  );
}
