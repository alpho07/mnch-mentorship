import { useState } from "react";
import { T, calcGrade, isQuestionVisible, getSectionCompletion } from "../constants.js";
import { BackButton, ProgressBar } from "../components/shared-components.jsx";
import { QuestionCard } from "../components/question-inputs.jsx";

// ── Section Form (one section at a time) ──────────────────────────────────────
function SectionForm({ section, responses, explanations, onAnswer, onExplain, onBack, onSave, isLast }) {
  const questions    = (section.questions || []).filter(q => isQuestionVisible(q, responses));
  const completion   = getSectionCompletion(section.questions || [], responses);
  const pct          = completion.total > 0 ? Math.round((completion.answered / completion.total) * 100) : 0;
  const [g1, g2]     = section.gradient || [section.color, section.color];

  return (
    <div style={{ display:"flex", flexDirection:"column", height:"100%" }}>
      {/* Section header */}
      <div style={{
        background:`linear-gradient(135deg, ${g1} 0%, ${g2} 100%)`,
        padding:"20px 20px 24px", position:"relative", overflow:"hidden",
      }}>
        <div style={{ position:"absolute", top:-20, right:-20, fontSize:80, opacity:0.12, lineHeight:1 }}>
          {section.icon}
        </div>
        <BackButton onBack={onBack} light />
        <div style={{ marginTop:12 }}>
          <div style={{ fontSize:28 }}>{section.icon}</div>
          <div style={{ color:"white", fontSize:20, fontWeight:800, marginTop:4 }}>{section.name}</div>
          <div style={{ color:"rgba(255,255,255,0.7)", fontSize:13 }}>{section.description}</div>
          <div style={{ marginTop:12 }}>
            <div style={{ display:"flex", justifyContent:"space-between", marginBottom:5 }}>
              <span style={{ color:"rgba(255,255,255,0.7)", fontSize:12 }}>
                {completion.answered}/{completion.total} required
              </span>
              <span style={{ color:"white", fontSize:12, fontWeight:700 }}>{pct}%</span>
            </div>
            <div style={{ height:4, background:"rgba(255,255,255,0.25)", borderRadius:999 }}>
              <div style={{ height:"100%", width:`${pct}%`, background:"white", borderRadius:999, transition:"width 0.3s" }} />
            </div>
          </div>
        </div>
      </div>

      {/* Questions */}
      <div style={{ flex:1, overflowY:"auto", padding:"14px 16px 100px", background:T.bg }}>
        {questions.map((q, i) => (
          <QuestionCard
            key={q.id}
            question={q}
            value={responses[q.question_code]}
            explanation={explanations[q.question_code]}
            onAnswer={(v) => onAnswer(q.question_code, v)}
            onExplain={(v) => onExplain(q.question_code, v)}
            index={i}
          />
        ))}
      </div>

      {/* Save button */}
      <div style={{ padding:"12px 16px", background:T.card, borderTop:`1px solid ${T.border}` }}>
        <button onClick={onSave} disabled={pct < 100} style={{
          width:"100%", padding:14, borderRadius:T.radius, border:"none",
          background: pct === 100 ? `linear-gradient(135deg, ${g1}, ${g2})` : "#E5E7EB",
          color: pct === 100 ? "white" : T.textMuted,
          fontSize:15, fontWeight:700, cursor: pct === 100 ? "pointer" : "not-allowed",
          transition:"all 0.2s",
        }}>
          {isLast ? "Save Final Section →" : "Save & Continue →"}
        </button>
      </div>
    </div>
  );
}

// ── Assessment Dashboard (section list + facility form) ───────────────────────
export function AssessmentFormScreen({ user, sections, editAssessment, onBack, onComplete }) {
  const [view,             setView]             = useState("dashboard"); // dashboard | section
  const [activeSectionIdx, setActiveSectionIdx] = useState(0);
  const [completedSections,setCompletedSections]= useState(
    new Set(editAssessment ? Object.keys(editAssessment.section_scores || {}) : [])
  );
  const [facilityData, setFacilityData] = useState(
    editAssessment ? {
      name:            editAssessment.facility_name || "",
      mfl_code:        editAssessment.mfl_code || "",
      county:          editAssessment.county || "",
      subcounty:       editAssessment.subcounty || "",
      assessment_type: editAssessment.assessment_type || "Baseline",
      assessment_date: editAssessment.assessment_date || new Date().toISOString().split("T")[0],
    } : {
      name:"", mfl_code:"", county:"", subcounty:"",
      assessment_type:"Baseline",
      assessment_date: new Date().toISOString().split("T")[0],
    }
  );
  const [responses,     setResponses]     = useState(editAssessment?.responses || {});
  const [explanations,  setExplanations]  = useState({});
  const [saving,        setSaving]        = useState(false);

  const facilityComplete = ["name","mfl_code","county","subcounty","assessment_type","assessment_date"]
    .every(k => facilityData[k]);

  const activeSection = sections[activeSectionIdx];
  const allDone       = completedSections.size === sections.length;

  const handleAnswer  = (code, val) => setResponses(p => ({ ...p, [code]: val }));
  const handleExplain = (code, val) => setExplanations(p => ({ ...p, [code]: val }));

  const handleSectionSave = async () => {
    setSaving(true);
    try {
      // ── PRODUCTION ─────────────────────────────────────────────────────
      // const sectionResponses = {};
      // const sectionExplanations = {};
      // (activeSection.questions || []).forEach(q => {
      //   if (responses[q.question_code] !== undefined) {
      //     sectionResponses[q.question_code] = responses[q.question_code];
      //   }
      //   if (explanations[q.question_code]) {
      //     sectionExplanations[q.question_code] = explanations[q.question_code];
      //   }
      // });
      // await api.responses.bulkSave(assessmentId, activeSection.code, sectionResponses, sectionExplanations);
      // ──────────────────────────────────────────────────────────────────

      setCompletedSections(p => new Set([...p, activeSection.code]));
      setView("dashboard");
      // Auto-advance to next incomplete section
      const nextIdx = sections.findIndex((s, i) => i > activeSectionIdx && !completedSections.has(s.code));
      if (nextIdx >= 0) setActiveSectionIdx(nextIdx);
    } finally {
      setSaving(false);
    }
  };

  const handleSubmit = async () => {
    setSaving(true);
    try {
      // ── PRODUCTION ─────────────────────────────────────────────────────
      // await api.assessments.submit(assessmentId);
      // const result = await api.assessments.find(assessmentId);
      // onComplete(result.data);
      // ──────────────────────────────────────────────────────────────────

      // Local score calculation for demo
      const sectionScores = {};
      let totalPct = 0;
      sections.forEach(s => {
        const qs      = (s.questions || []).filter(q => q.scoring_map && isQuestionVisible(q, responses));
        let score     = 0;
        let maxScore  = 0;
        qs.forEach(q => {
          const v = responses[q.question_code];
          if (v !== undefined && q.scoring_map[v] !== undefined) score += q.scoring_map[v];
          maxScore++;
        });
        const pct = maxScore > 0 ? Math.round((score / maxScore) * 100) : 0;
        sectionScores[s.code] = { percentage: pct, grade: calcGrade(pct), answered_questions: qs.filter(q => responses[q.question_code]).length, total_questions: qs.length };
        totalPct += pct;
      });
      const overallPct = sections.length > 0 ? Math.round(totalPct / sections.length) : 0;
      onComplete({
        id: Date.now(),
        ...facilityData,
        assessor_name:      user.name,
        assessor_contact:   user.email,
        status:             "completed",
        overall_percentage: overallPct,
        overall_grade:      calcGrade(overallPct),
        completed_at:       new Date().toISOString().split("T")[0],
        section_scores:     sectionScores,
        responses,
      });
    } finally {
      setSaving(false);
    }
  };

  // ── Section view ─────────────────────────────────────────────────────────────
  if (view === "section" && activeSection) {
    return (
      <SectionForm
        section={activeSection}
        responses={responses}
        explanations={explanations}
        onAnswer={handleAnswer}
        onExplain={handleExplain}
        onBack={() => setView("dashboard")}
        onSave={handleSectionSave}
        isLast={activeSectionIdx === sections.length - 1}
      />
    );
  }

  // ── Dashboard view ───────────────────────────────────────────────────────────
  const overallPct = Math.round((completedSections.size / Math.max(sections.length, 1)) * 100);

  return (
    <div style={{ display:"flex", flexDirection:"column", height:"100%" }}>
      <div style={{
        background:"linear-gradient(135deg, #1E3A5F 0%, #1D4ED8 100%)",
        padding:"20px 20px 24px",
      }}>
        <BackButton onBack={onBack} light />
        <div style={{ marginTop:12, color:"white", fontSize:20, fontWeight:800 }}>
          {editAssessment ? "Continue Assessment" : "New Assessment"}
        </div>
        <div style={{ color:"rgba(255,255,255,0.6)", fontSize:13, marginTop:2 }}>
          {completedSections.size}/{sections.length} sections completed
        </div>
        <div style={{ marginTop:10 }}>
          <div style={{ height:4, background:"rgba(255,255,255,0.2)", borderRadius:999 }}>
            <div style={{ height:"100%", width:`${overallPct}%`, background:"white", borderRadius:999, transition:"width 0.3s" }} />
          </div>
        </div>
      </div>

      <div style={{ flex:1, overflowY:"auto", padding:"14px 16px 100px", background:T.bg }}>
        {/* Facility fields */}
        <div style={{ background:T.card, borderRadius:T.radius, padding:16, marginBottom:14, boxShadow:T.shadow }}>
          <div style={{ display:"flex", justifyContent:"space-between", alignItems:"center", marginBottom:12 }}>
            <div style={{ fontSize:13, fontWeight:700, color:T.textMid }}>🏥 Facility & Assessor</div>
            <span style={{
              fontSize:11, fontWeight:700,
              background: facilityComplete ? "#D1FAE5" : "#FEF3C7",
              color: facilityComplete ? "#065F46" : "#92400E",
              borderRadius:6, padding:"2px 8px",
            }}>{facilityComplete ? "✓ Done" : "Required"}</span>
          </div>
          <div style={{ display:"grid", gridTemplateColumns:"1fr 1fr", gap:10 }}>
            {[
              { key:"name",            label:"Facility Name", col:"1/-1" },
              { key:"mfl_code",        label:"MFL Code" },
              { key:"county",          label:"County" },
              { key:"subcounty",       label:"Sub-County" },
              { key:"assessment_date", label:"Date", type:"date" },
            ].map(({ key, label, col, type }) => (
              <div key={key} style={{ gridColumn:col }}>
                <div style={{ fontSize:11, color:T.textMuted, fontWeight:600, marginBottom:4 }}>{label}</div>
                <input
                  type={type || "text"} value={facilityData[key] || ""}
                  onChange={e => setFacilityData(p => ({ ...p, [key]: e.target.value }))}
                  style={{
                    width:"100%", padding:"9px 11px", borderRadius:8,
                    border:`1.5px solid ${T.border}`, fontSize:13, color:T.text,
                    outline:"none", boxSizing:"border-box", fontFamily:"inherit",
                    background:T.borderLight,
                  }}
                />
              </div>
            ))}
            <div>
              <div style={{ fontSize:11, color:T.textMuted, fontWeight:600, marginBottom:4 }}>Type</div>
              <select value={facilityData.assessment_type}
                onChange={e => setFacilityData(p => ({ ...p, assessment_type: e.target.value }))}
                style={{
                  width:"100%", padding:"9px 11px", borderRadius:8,
                  border:`1.5px solid ${T.border}`, fontSize:13, color:T.text,
                  outline:"none", background:T.borderLight, fontFamily:"inherit",
                }}>
                {["Baseline","Midline","Endline"].map(o => <option key={o}>{o}</option>)}
              </select>
            </div>
          </div>
        </div>

        {/* Sections */}
        <div style={{ fontSize:12, fontWeight:700, color:T.textMuted, textTransform:"uppercase", letterSpacing:1, marginBottom:10 }}>
          Assessment Sections
        </div>
        {sections.map((s, i) => {
          const done   = completedSections.has(s.code);
          const comp   = getSectionCompletion(s.questions || [], responses);
          const pct    = comp.total > 0 ? Math.round((comp.answered / comp.total) * 100) : 0;
          const inProg = !done && pct > 0;
          const [g1]   = s.gradient || [s.color];

          return (
            <button key={s.id}
              disabled={!facilityComplete}
              onClick={() => { setActiveSectionIdx(i); setView("section"); }}
              style={{
                width:"100%", background:T.card, borderRadius:T.radius,
                padding:"13px 15px", marginBottom:9,
                border:`2px solid ${done ? "#D1FAE5" : inProg ? "#FEF3C7" : T.borderLight}`,
                cursor: facilityComplete ? "pointer" : "not-allowed",
                textAlign:"left", display:"flex", alignItems:"center", gap:13,
                opacity: facilityComplete ? 1 : 0.5, boxShadow:T.shadow,
              }}>
              <div style={{
                width:40, height:40, borderRadius:10,
                background:`${s.color}18`,
                display:"flex", alignItems:"center", justifyContent:"center", fontSize:20,
              }}>{s.icon}</div>
              <div style={{ flex:1, minWidth:0 }}>
                <div style={{ display:"flex", justifyContent:"space-between", alignItems:"center" }}>
                  <span style={{ fontWeight:700, fontSize:13, color:T.text }}>{s.name}</span>
                  <span style={{
                    fontSize:11, fontWeight:700,
                    color: done ? "#059669" : inProg ? "#D97706" : T.textMuted,
                    background: done ? "#D1FAE5" : inProg ? "#FEF3C7" : T.borderLight,
                    borderRadius:5, padding:"2px 7px",
                  }}>
                    {done ? "✓ Done" : inProg ? `${pct}%` : "Start →"}
                  </span>
                </div>
                <div style={{ fontSize:12, color:T.textSub, marginTop:1, overflow:"hidden", textOverflow:"ellipsis", whiteSpace:"nowrap" }}>
                  {s.description}
                </div>
                {inProg && <div style={{ marginTop:5 }}><ProgressBar pct={pct} color={s.color} height={3} /></div>}
              </div>
            </button>
          );
        })}
      </div>

      {allDone && facilityComplete && (
        <div style={{ padding:"12px 16px", background:T.card, borderTop:`1px solid ${T.border}` }}>
          <button onClick={handleSubmit} disabled={saving} style={{
            width:"100%", padding:14, borderRadius:T.radius,
            background: saving ? "#D1D5DB" : "linear-gradient(135deg, #064E3B, #059669)",
            color:"white", border:"none", fontSize:15, fontWeight:700,
            cursor: saving ? "not-allowed" : "pointer",
          }}>
            {saving ? "Submitting…" : "Submit Assessment 🚀"}
          </button>
        </div>
      )}
    </div>
  );
}
