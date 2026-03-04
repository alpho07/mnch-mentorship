import { T } from "../constants.js";
import { ProgressBar } from "./shared-components.jsx";

// ─── Yes / No / Partial ───────────────────────────────────────────────────────
export function YesNoInput({ question, value, onChange }) {
  const opts   = question.question_type === "yes_no_partial"
    ? ["Yes", "Partial", "No"]
    : ["Yes", "No"];
  const colors = { Yes: "#10B981", Partial: "#F59E0B", No: "#EF4444" };
  const icons  = { Yes: "✓",       Partial: "◐",       No: "✗" };

  return (
    <div style={{ display: "flex", gap: 8, marginTop: 10 }}>
      {opts.map((o) => {
        const active = value === o;
        return (
          <button key={o} onClick={() => onChange(o)} style={{
            flex: 1, padding: "12px 4px", borderRadius: 12,
            border: `2px solid ${active ? colors[o] : T.border}`,
            background: active ? `${colors[o]}18` : T.borderLight,
            color: active ? colors[o] : T.textMuted,
            fontWeight: active ? 800 : 500, fontSize: 13, cursor: "pointer",
            display: "flex", flexDirection: "column", alignItems: "center", gap: 3,
            transition: "all 0.15s",
          }}>
            <span style={{ fontSize: 16 }}>{icons[o]}</span>
            <span>{o}</span>
          </button>
        );
      })}
    </div>
  );
}

// ─── Proportion (slider + number) ─────────────────────────────────────────────
export function ProportionInput({ value, onChange }) {
  const num   = Math.min(Math.max(parseFloat(value) || 0, 0), 100);
  const color = num >= 80 ? "#10B981" : num >= 50 ? "#F59E0B" : "#EF4444";

  return (
    <div style={{ marginTop: 10 }}>
      <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 8 }}>
        <input type="range" min={0} max={100} value={num}
          onChange={(e) => onChange(e.target.value)}
          style={{ flex: 1, accentColor: color, cursor: "pointer" }} />
        <div style={{ display: "flex", alignItems: "center", gap: 3 }}>
          <input type="number" min={0} max={100} value={value || ""}
            onChange={(e) => onChange(e.target.value)}
            placeholder="0"
            style={{
              width: 56, padding: "7px 5px", borderRadius: 8,
              border: `2px solid ${T.border}`, fontSize: 15, fontWeight: 700,
              textAlign: "center", color, outline: "none", fontFamily: "inherit",
              background: T.borderLight,
            }} />
          <span style={{ fontSize: 13, color: T.textSub }}>%</span>
        </div>
      </div>
      <ProgressBar pct={num} color={color} height={4} />
    </div>
  );
}

// ─── Select (radio-style list) ────────────────────────────────────────────────
export function SelectInput({ question, value, onChange }) {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 7, marginTop: 10 }}>
      {(question.options || []).map((opt) => {
        const active = value === opt;
        return (
          <button key={opt} onClick={() => onChange(opt)} style={{
            padding: "11px 14px", borderRadius: T.radiusSm,
            border: `2px solid ${active ? "#0EA5E9" : T.border}`,
            background: active ? "#E0F2FE" : T.borderLight,
            color: active ? "#0284C7" : T.textMid,
            fontWeight: active ? 700 : 400, fontSize: 13,
            cursor: "pointer", textAlign: "left",
            display: "flex", alignItems: "center", gap: 10,
            transition: "all 0.15s",
          }}>
            <div style={{
              width: 16, height: 16, borderRadius: "50%",
              border: active ? "5px solid #0EA5E9" : `2px solid ${T.border}`,
              background: "white", flexShrink: 0,
            }} />
            {opt}
          </button>
        );
      })}
    </div>
  );
}

// ─── Number ───────────────────────────────────────────────────────────────────
export function NumberInput({ value, onChange }) {
  return (
    <input type="number" min={0} value={value || ""} onChange={(e) => onChange(e.target.value)}
      placeholder="0"
      style={{
        marginTop: 10, padding: "11px 14px", borderRadius: T.radiusSm,
        border: `2px solid ${T.border}`, fontSize: 18, fontWeight: 700,
        color: T.text, outline: "none", width: 130,
        background: T.borderLight, fontFamily: "inherit",
      }} />
  );
}

// ─── Text / Date ──────────────────────────────────────────────────────────────
export function TextInput({ question, value, onChange }) {
  return (
    <input
      type={question.question_type === "date" ? "date" : "text"}
      value={value || ""}
      onChange={(e) => onChange(e.target.value)}
      placeholder={question.placeholder || ""}
      style={{
        marginTop: 10, padding: "11px 14px", borderRadius: T.radiusSm,
        border: `2px solid ${T.border}`, fontSize: 14, color: T.text,
        outline: "none", width: "100%", boxSizing: "border-box",
        background: T.borderLight, fontFamily: "inherit",
      }}
    />
  );
}

// ─── Explanation textarea (shown when requires_explanation_on matches) ─────────
export function ExplanationInput({ label, value, onChange }) {
  return (
    <div style={{ marginTop: 8 }}>
      <div style={{ fontSize: 11, color: "#9CA3AF", marginBottom: 4 }}>
        {label || "Comments / Recommendations"}
      </div>
      <textarea
        value={value || ""}
        onChange={(e) => onChange(e.target.value)}
        rows={2}
        placeholder="Add comments..."
        style={{
          width: "100%", padding: "9px 12px", borderRadius: T.radiusSm,
          border: `1.5px solid ${T.border}`, fontSize: 13, color: T.text,
          outline: "none", resize: "none", boxSizing: "border-box",
          background: T.borderLight, fontFamily: "inherit",
        }}
      />
    </div>
  );
}

// ─── QuestionCard — renders the correct input based on question_type ──────────
export function QuestionCard({ question, value, explanation, onAnswer, onExplain, index }) {
  const hasValue     = value !== undefined && value !== "" && value !== null;
  const needsExplain = question.requires_explanation_on &&
    question.requires_explanation_on.includes(value);

  const renderInput = () => {
    switch (question.question_type) {
      case "yes_no":
      case "yes_no_partial":
        return <YesNoInput question={question} value={value} onChange={onAnswer} />;
      case "proportion":
        return <ProportionInput value={value} onChange={onAnswer} />;
      case "select":
      case "radio":
        return <SelectInput question={question} value={value} onChange={onAnswer} />;
      case "number":
        return <NumberInput value={value} onChange={onAnswer} />;
      default:
        return <TextInput question={question} value={value} onChange={onAnswer} />;
    }
  };

  return (
    <div style={{
      background: T.card, borderRadius: T.radius, padding: "15px 16px", marginBottom: 10,
      border: `2px solid ${hasValue ? "#D1FAE5" : T.borderLight}`,
      boxShadow: T.shadow, transition: "border-color 0.2s",
    }}>
      {/* Question header */}
      <div style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
        <div style={{
          minWidth: 24, height: 24, borderRadius: 6,
          background: hasValue ? "#D1FAE5" : T.borderLight,
          display: "flex", alignItems: "center", justifyContent: "center",
          fontSize: 11, fontWeight: 700,
          color: hasValue ? "#059669" : T.textMuted, flexShrink: 0,
        }}>{hasValue ? "✓" : index + 1}</div>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 14, fontWeight: 600, color: T.text, lineHeight: 1.45 }}>
            {question.question_text}
            {question.is_required && <span style={{ color: "#EF4444", marginLeft: 3 }}>*</span>}
          </div>
          {question.help_text && (
            <div style={{ fontSize: 12, color: T.textMuted, marginTop: 3 }}>{question.help_text}</div>
          )}
        </div>
      </div>

      {/* Input */}
      {renderInput()}

      {/* Conditional explanation field */}
      {needsExplain && (
        <ExplanationInput
          label={question.explanation_label}
          value={explanation}
          onChange={onExplain}
        />
      )}
    </div>
  );
}
