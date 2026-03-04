import { useState } from "react";
import { T, GRADE_COLOR, GRADE_BG } from "../constants.js";
import { BackButton, GradeBadge, StatusChip, ProgressBar } from "../components/shared-components.jsx";

// ── Tab: Overview ─────────────────────────────────────────────────────────────
function OverviewTab({ assessment, sections }) {
    return (
        <>
            {/* Score card */}
            {assessment.status === "completed" && (
                <div style={{
                    background: assessment.overall_grade
                        ? `linear-gradient(135deg, ${GRADE_BG[assessment.overall_grade]}, white)`
                        : T.borderLight,
                    borderRadius: T.radius, padding: 16, marginBottom: 14, boxShadow: T.shadow,
                }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 8 }}>
                        Overall Score
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
                        <div style={{ fontSize: 48, fontWeight: 900, color: assessment.overall_grade ? GRADE_COLOR[assessment.overall_grade] : T.textMid }}>
                            {assessment.overall_percentage ?? "—"}
                            {assessment.overall_percentage != null && <span style={{ fontSize: 24 }}>%</span>}
                        </div>
                        {assessment.overall_grade && <GradeBadge grade={assessment.overall_grade} />}
                    </div>
                    <ProgressBar pct={assessment.overall_percentage ?? 0} color={GRADE_COLOR[assessment.overall_grade] ?? T.primary} height={6} />
                </div>
            )}

            {/* Facility info */}
            <div style={{ background: T.card, borderRadius: T.radius, padding: 16, marginBottom: 14, boxShadow: T.shadow }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid, marginBottom: 10 }}>🏥 Facility Info</div>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
                    {[
                        { label: "Facility", value: assessment.facility_name || "—" },
                        { label: "MFL Code", value: assessment.mfl_code || "—" },
                        { label: "County", value: assessment.county || "—" },
                        { label: "Sub-County", value: assessment.subcounty || "—" },
                        { label: "Type", value: assessment.assessment_type || "—" },
                        { label: "Date", value: assessment.assessment_date || "—" },
                    ].map(({ label, value }) => (
                        <div key={label}>
                            <div style={{ fontSize: 10, color: T.textMuted, fontWeight: 600, textTransform: "uppercase", letterSpacing: 0.6 }}>{label}</div>
                            <div style={{ fontSize: 13, color: T.text, fontWeight: 600, marginTop: 2 }}>{value}</div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Section scores */}
            {sections && sections.length > 0 && (
                <div style={{ background: T.card, borderRadius: T.radius, padding: 16, boxShadow: T.shadow }}>
                    <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid, marginBottom: 12 }}>Section Scores</div>
                    {sections.map((s, i) => {
                        const sc = (assessment.section_scores || {})[s.code];
                        return (
                            <div key={s.id ?? s.code} style={{ marginBottom: i < sections.length - 1 ? 12 : 0 }}>
                                <div style={{
                                    width: 40, height: 40, borderRadius: 10, flexShrink: 0,
                                    background: `${s.color ?? "#6B7280"}18`,
                                    display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20,
                                    float: "left", marginRight: 12,
                                }}>{s.icon}</div>
                                <div style={{ overflow: "hidden" }}>
                                    <div style={{ fontWeight: 700, fontSize: 14, color: T.text }}>{s.name}</div>
                                    <div style={{ fontSize: 12, color: T.textSub, marginTop: 1 }}>{s.description}</div>
                                </div>
                                <div style={{ clear: "both" }} />
                                {sc
                                    ? <>
                                        <ProgressBar pct={sc.percentage} color={GRADE_COLOR[sc.grade]} height={5} />
                                        <div style={{ display: "flex", justifyContent: "space-between", marginTop: 4 }}>
                                            <span style={{ fontSize: 12, color: T.textSub }}><b style={{ color: "#10B981" }}>{sc.answered_questions}</b> answered</span>
                                            <GradeBadge grade={sc.grade} pct={sc.percentage} />
                                        </div>
                                    </>
                                    : <div style={{ fontSize: 12, color: T.textMuted, marginTop: 4 }}>
                                        {assessment.status === "in_progress" ? "Pending" : "N/A"}
                                    </div>
                                }
                            </div>
                        );
                    })}
                </div>
            )}
        </>
    );
}

// ── Tab: Responses ────────────────────────────────────────────────────────────
function ResponsesTab({ assessment, sections }) {
    const [expanded, setExpanded] = useState(sections[0]?.code);
    const responses = assessment.responses || {};

    return (
        <>
            {sections.map(s => {
                const questions = s.questions || [];
                const open = expanded === s.code;
                return (
                    <div key={s.id ?? s.code} style={{ background: T.card, borderRadius: T.radius, marginBottom: 10, overflow: "hidden", boxShadow: T.shadow }}>
                        <button onClick={() => setExpanded(open ? null : s.code)} style={{
                            width: "100%", padding: "14px 16px", border: "none", background: "none",
                            cursor: "pointer", display: "flex", alignItems: "center", gap: 10, textAlign: "left",
                        }}>
                            <span style={{ fontSize: 20 }}>{s.icon}</span>
                            <span style={{ flex: 1, fontWeight: 700, fontSize: 14, color: T.text }}>{s.name}</span>
                            <span style={{ color: T.textMuted, fontSize: 16, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
                        </button>
                        {open && questions.length > 0 && (
                            <div style={{ padding: "0 16px 14px", borderTop: `1px solid ${T.border}` }}>
                                {questions.map(q => {
                                    const val = responses[q.question_code];
                                    return (
                                        <div key={q.id ?? q.question_code} style={{ paddingTop: 12 }}>
                                            <div style={{ fontSize: 13, color: T.textMid, marginBottom: 5 }}>{q.question_text}</div>
                                            <div style={{
                                                display: "inline-block", padding: "4px 12px", borderRadius: 8,
                                                background: val === "Yes" ? "#D1FAE5" : val === "No" ? "#FEE2E2" : val === "Partial" ? "#FEF3C7" : T.borderLight,
                                                color: val === "Yes" ? "#065F46" : val === "No" ? "#991B1B" : val === "Partial" ? "#92400E" : T.textMuted,
                                                fontSize: 12, fontWeight: 700,
                                            }}>
                                                {val ?? "Not answered"}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                        {open && questions.length === 0 && (
                            <div style={{ padding: "12px 16px", borderTop: `1px solid ${T.border}`, fontSize: 13, color: T.textMuted }}>
                                No questions in this section.
                            </div>
                        )}
                    </div>
                );
            })}
        </>
    );
}

// ── Main screen ───────────────────────────────────────────────────────────────
export function AssessmentDetailScreen({ assessment, sections, onBack, onContinue }) {
    const [tab, setTab] = useState("overview");

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
            {/* Header */}
            <div style={{
                background: "linear-gradient(135deg, #1E3A5F 0%, #1D4ED8 100%)",
                padding: "20px 20px 24px",
            }}>
                <BackButton onBack={onBack} light />
                <div style={{ marginTop: 12, color: "white", fontSize: 18, fontWeight: 800 }}>
                    {assessment.facility_name || "Assessment"}
                </div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 2 }}>
                    {[assessment.assessment_type, assessment.assessment_date].filter(Boolean).join(" · ")}
                </div>
                <div style={{ marginTop: 10 }}>
                    {assessment.overall_grade
                        ? <GradeBadge grade={assessment.overall_grade} pct={assessment.overall_percentage} />
                        : <StatusChip status={assessment.status} />
                    }
                </div>
            </div>

            {/* Tabs */}
            <div style={{ display: "flex", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                {["overview", "responses"].map(t => (
                    <button key={t} onClick={() => setTab(t)} style={{
                        flex: 1, padding: "12px 0", border: "none",
                        background: tab === t ? T.card : T.borderLight,
                        borderBottom: tab === t ? `2px solid #1D4ED8` : "2px solid transparent",
                        color: tab === t ? "#1D4ED8" : T.textMuted,
                        fontSize: 13, fontWeight: tab === t ? 700 : 500,
                        cursor: "pointer", textTransform: "capitalize",
                    }}>
                        {t}
                    </button>
                ))}
            </div>

            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: "14px 16px 100px", background: T.bg }}>
                {tab === "overview" && <OverviewTab assessment={assessment} sections={sections ?? []} />}
                {tab === "responses" && <ResponsesTab assessment={assessment} sections={sections ?? []} />}
            </div>

            {/* Continue button for in_progress */}
            {assessment.status === "in_progress" && onContinue && (
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.border}` }}>
                    <button onClick={() => onContinue(assessment)} style={{
                        width: "100%", padding: 15, borderRadius: T.radius, border: "none",
                        background: "linear-gradient(135deg, #1E3A5F, #1D4ED8)",
                        color: "white", fontSize: 15, fontWeight: 700, cursor: "pointer",
                    }}>
                        Continue Assessment →
                    </button>
                </div>
            )}
        </div>
    );
}
