// ─── Design Tokens ────────────────────────────────────────────────────────────
export const T = {
  bg:         "#F0F4F8",
  card:       "#FFFFFF",
  primary:    "#064E3B",
  primaryLight:"#059669",
  text:       "#111827",
  textMid:    "#374151",
  textSub:    "#6B7280",
  textMuted:  "#9CA3AF",
  border:     "#E5E7EB",
  borderLight:"#F3F4F6",
  radius:     16,
  radiusSm:   10,
  shadow:     "0 2px 12px rgba(0,0,0,0.07)",
  shadowMd:   "0 4px 20px rgba(0,0,0,0.1)",
};

// ─── Grade System ─────────────────────────────────────────────────────────────
export const GRADE_COLOR = { green: "#10B981", yellow: "#F59E0B", red: "#EF4444" };
export const GRADE_BG    = { green: "#D1FAE5", yellow: "#FEF3C7", red: "#FEE2E2" };
export const GRADE_TEXT  = { green: "#065F46", yellow: "#92400E", red: "#991B1B" };
export const GRADE_LABEL = { green: "Good",    yellow: "Fair",    red: "Poor"    };

export function calcGrade(pct) {
  if (pct >= 80) return "green";
  if (pct >= 50) return "yellow";
  return "red";
}

// ─── Assessment Sections (static config — icons/colors only) ──────────────────
// Questions and section metadata come from the API: GET /api/v1/sections/schema/full
export const SECTION_META = {
  infrastructure:     { icon: "🏗️", gradient: ["#8B5CF6", "#7C3AED"] },
  skills_lab:         { icon: "🔬", gradient: ["#10B981", "#059669"] },
  human_resources:    { icon: "👥", gradient: ["#F59E0B", "#D97706"] },
  health_products:    { icon: "💊", gradient: ["#EF4444", "#DC2626"] },
  information_systems:{ icon: "💻", gradient: ["#06B6D4", "#0891B2"] },
  quality_of_care:    { icon: "⭐", gradient: ["#EC4899", "#DB2777"] },
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
export function generateLocalId() {
  return "MT-" + Math.random().toString(36).substring(2, 8).toUpperCase();
}

export function isQuestionVisible(question, responses) {
  if (!question.display_conditions) return true;
  const { question_code, value } = question.display_conditions;
  return responses[question_code] === value;
}

export function getSectionCompletion(questions, responses) {
  const required = questions.filter(
    (q) => q.is_required && isQuestionVisible(q, responses)
  );
  const answered = required.filter((q) => {
    const v = responses[q.question_code];
    return v !== undefined && v !== "" && v !== null;
  });
  return { total: required.length, answered: answered.length };
}
