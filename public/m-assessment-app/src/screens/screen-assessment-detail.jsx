import { useState } from "react";
import { T, GRADE_COLOR, GRADE_BG, GRADE_TEXT, GRADE_LABEL } from "../constants.js";
import { GradeBadge, StatusChip, BackButton, ProgressBar } from "../components/shared-components.jsx";

export function AssessmentDetailScreen({ assessment, sections, onBack, onContinue }) {
  const [tab, setTab] = useState("overview");
  const grade  = assessment.overall_grade;
  const pct    = assessment.overall_percentage || 0;

  return (
    <div style={{ display:"flex", flexDirection:"column", height:"100%" }}>
      {/* Header */}
      <div style={{
        background: grade
          ? `linear-gradient(135deg, ${GRADE_COLOR[grade]} 0%, ${GRADE_COLOR[grade]}CC 100%)`
          : "linear-gradient(135deg, #6B7280, #4B5563)",
        padding:"20px 20px 24px", position:"relative", overflow:"hidden",
      }}>
        <div style={{ position:"absolute", top:-20, right:-20, width:100, height:100, borderRadius:"50%", background:"rgba(255,255,255,0.1)" }} />
        <BackButton onBack={onBack} light />
        <div style={{ marginTop:12 }}>
          <div style={{ color:"rgba(255,255,255,0.7)", fontSize:12 }}>
            {assessment.assessment_type} · {assessment.assessment_date}
          </div>
          <div style={{ color:"white", fontSize:20, fontWeight:800, marginTop:4, lineHeight:1.2 }}>
            {assessment.facility_name}
          </div>
          <div style={{ color:"rgba(255,255,255,0.7)", fontSize:12, marginTop:4 }}>
            {assessment.county}{assessment.subcounty ? ` · ${assessment.subcounty}` : ""}
          </div>
          {assessment.status === "completed" && (
            <div style={{ display:"flex", alignItems:"center", gap:10, marginTop:14 }}>
              {/* Circular progress */}
              <div style={{ position:"relative", width:64, height:64 }}>
                <svg width={64} height={64} style={{ transform:"rotate(-90deg)" }}>
                  <circle cx={32} cy={32} r={26} fill="none" stroke="rgba(255,255,255,0.25)" strokeWidth={6} />
                  <circle cx={32} cy={32} r={26} fill="none" stroke="white" strokeWidth={6}
                    strokeDasharray={`${2*Math.PI*26}`}
                    strokeDashoffset={`${2*Math.PI*26*(1-pct/100)}`}
                    strokeLinecap="round" />
                </svg>
                <div style={{
                  position:"absolute", inset:0, display:"flex",
                  alignItems:"center", justifyContent:"center",
                  color:"white", fontSize:14, fontWeight:800,
                }}>{pct}%</div>
              </div>
              <div>
                <div style={{ color:"white", fontSize:22, fontWeight:800 }}>
                  {grade ? GRADE_LABEL[grade] : "—"}
                </div>
                <div style={{ color:"rgba(255,255,255,0.7)", fontSize:12 }}>Overall Grade</div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Tabs */}
      <div style={{ display:"flex", background:T.card, borderBottom:`1px solid ${T.border}` }}>
        {["overview","sections","responses"].map(t => (
          <button key={t} onClick={() => setTab(t)} style={{
            flex:1, padding:"12px 4px", border:"none", cursor:"pointer", background:"none",
            borderBottom:`3px solid ${tab===t ? "#059669" : "transparent"}`,
            color: tab===t ? "#059669" : T.textSub,
            fontWeight: tab===t ? 700 : 500, fontSize:13,
            textTransform:"capitalize", transition:"all 0.15s",
          }}>{t}</button>
        ))}
      </div>

      {/* Content */}
      <div style={{ flex:1, overflowY:"auto", background:T.bg, padding:"16px 16px 80px" }}>
        {tab === "overview"   && <OverviewTab   assessment={assessment} sections={sections} />}
        {tab === "sections"   && <SectionsTab   assessment={assessment} sections={sections} />}
        {tab === "responses"  && <ResponsesTab  assessment={assessment} sections={sections} />}
      </div>

      {assessment.status === "in_progress" && (
        <div style={{ padding:"12px 16px", background:T.card, borderTop:`1px solid ${T.border}` }}>
          <button onClick={() => onContinue(assessment)} style={{
            width:"100%", padding:14, borderRadius:T.radius,
            background:"linear-gradient(135deg, #064E3B, #059669)",
            color:"white", border:"none", fontSize:15, fontWeight:700, cursor:"pointer",
          }}>Continue Assessment →</button>
        </div>
      )}
    </div>
  );
}

// ── Tab: Overview ─────────────────────────────────────────────────────────────
function OverviewTab({ assessment, sections }) {
  const rows = [
    { label:"Facility",    value: assessment.facility_name },
    { label:"MFL Code",    value: assessment.mfl_code },
    { label:"County",      value: assessment.county },
    { label:"Sub-County",  value: assessment.subcounty },
    { label:"Type",        value: assessment.assessment_type },
    { label:"Date",        value: assessment.assessment_date },
    { label:"Assessor",    value: assessment.assessor_name },
    { label:"Contact",     value: assessment.assessor_contact },
    { label:"Status",      value: <StatusChip status={assessment.status} /> },
  ];
  const sectionScores = assessment.section_scores || {};

  return (
    <>
      <div style={{ background:T.card, borderRadius:T.radius, padding:16, marginBottom:14, boxShadow:T.shadow }}>
        <div style={{ fontSize:12, fontWeight:700, color:T.textMuted, textTransform:"uppercase", letterSpacing:1, marginBottom:12 }}>
          Facility Information
        </div>
        {rows.map(({ label, value }) => (
          <div key={label} style={{ display:"flex", justifyContent:"space-between", alignItems:"center", padding:"9px 0", borderBottom:`1px solid ${T.borderLight}` }}>
            <span style={{ fontSize:13, color:T.textSub }}>{label}</span>
            <span style={{ fontSize:13, fontWeight:600, color:T.text }}>{value}</span>
          </div>
        ))}
      </div>

      {assessment.status === "completed" && sections.length > 0 && (
        <div style={{ background:T.card, borderRadius:T.radius, padding:16, boxShadow:T.shadow }}>
          <div style={{ fontSize:12, fontWeight:700, color:T.textMuted, textTransform:"uppercase", letterSpacing:1, marginBottom:12 }}>
            Section Scores
          </div>
          {sections.map(s => {
            const sc = sectionScores[s.code];
            if (!sc) return null;
            return (
              <div key={s.id} style={{ marginBottom:12 }}>
                <div style={{ display:"flex", justifyContent:"space-between", marginBottom:5 }}>
                  <span style={{ fontSize:13, fontWeight:600, color:T.textMid }}>{s.icon} {s.name}</span>
                  <span style={{ fontSize:13, fontWeight:700, color:GRADE_COLOR[sc.grade] }}>{sc.percentage}%</span>
                </div>
                <ProgressBar pct={sc.percentage} color={GRADE_COLOR[sc.grade]} height={6} />
              </div>
            );
          })}
        </div>
      )}
    </>
  );
}

// ── Tab: Sections ─────────────────────────────────────────────────────────────
function SectionsTab({ assessment, sections }) {
  const sectionScores = assessment.section_scores || {};
  return (
    <>
      {sections.map(s => {
        const sc = sectionScores[s.code];
        return (
          <div key={s.id} style={{ background:T.card, borderRadius:T.radius, padding:16, marginBottom:10, boxShadow:T.shadow }}>
            <div style={{ display:"flex", alignItems:"center", gap:12, marginBottom: sc ? 12 : 0 }}>
              <div style={{
                width:40, height:40, borderRadius:10, flexShrink:0,
                background:`${s.color}18`,
                display:"flex", alignItems:"center", justifyContent:"center", fontSize:20,
              }}>{s.icon}</div>
              <div style={{ flex:1 }}>
                <div style={{ fontWeight:700, fontSize:14, color:T.text }}>{s.name}</div>
                <div style={{ fontSize:12, color:T.textSub, marginTop:1 }}>{s.description}</div>
              </div>
              {sc
                ? <GradeBadge grade={sc.grade} pct={sc.percentage} />
                : <span style={{ fontSize:11, color:T.textMuted, background:T.borderLight, borderRadius:6, padding:"3px 9px" }}>
                    {assessment.status === "in_progress" ? "Pending" : "N/A"}
                  </span>
              }
            </div>
            {sc && (
              <>
                <ProgressBar pct={sc.percentage} color={GRADE_COLOR[sc.grade]} height={5} />
                <div style={{ display:"flex", gap:12, marginTop:8 }}>
                  <span style={{ fontSize:12, color:T.textSub }}>
                    <b style={{ color:"#10B981" }}>{sc.answered_questions}</b> answered
                  </span>
                  <span style={{ fontSize:12, color:T.textSub }}>
                    <b>{sc.total_questions}</b> required
                  </span>
                </div>
              </>
            )}
          </div>
        );
      })}
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
          <div key={s.id} style={{ background:T.card, borderRadius:T.radius, marginBottom:10, overflow:"hidden", boxShadow:T.shadow }}>
            <button onClick={() => setExpanded(open ? null : s.code)} style={{
              width:"100%", padding:"14px 16px", border:"none", background:"none",
              cursor:"pointer", display:"flex", alignItems:"center", gap:10, textAlign:"left",
            }}>
              <span style={{ fontSize:20 }}>{s.icon}</span>
              <span style={{ flex:1, fontWeight:700, fontSize:14, color:T.text }}>{s.name}</span>
              <span style={{ color:T.textMuted, fontSize:16, transition:"transform 0.2s", transform: open ? "rotate(90deg)" : "none" }}>›</span>
            </button>

            {open && (
              <div style={{ padding:"0 16px 16px", borderTop:`1px solid ${T.borderLight}` }}>
                {questions.map(q => {
                  const resp = responses[q.question_code];
                  if (!resp) return null;
                  const num    = parseFloat(resp);
                  const isGood = resp === "Yes" || (!isNaN(num) && num >= 75);
                  const isMid  = resp === "Partial" || (!isNaN(num) && num >= 50 && num < 75);
                  const color  = isGood ? "#10B981" : isMid ? "#F59E0B" : "#EF4444";
                  return (
                    <div key={q.id} style={{ padding:"10px 0", borderBottom:`1px solid ${T.borderLight}` }}>
                      <div style={{ fontSize:12, color:T.textSub, marginBottom:5, lineHeight:1.4 }}>
                        {q.question_text}
                      </div>
                      <span style={{
                        fontSize:13, fontWeight:700, color,
                        background:`${color}14`, borderRadius:6, padding:"3px 9px",
                      }}>
                        {resp}{q.question_type === "proportion" ? "%" : ""}
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        );
      })}
    </>
  );
}
