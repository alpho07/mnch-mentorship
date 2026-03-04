import { useState, useEffect } from "react";
import { T, calcGrade, isQuestionVisible, getSectionCompletion } from "../constants.js";
import { BackButton, ProgressBar } from "../components/shared-components.jsx";
import { QuestionCard } from "../components/question-inputs.jsx";
import { HumanResourcesScreen } from "./screen-human-resources.jsx";
import { HealthProductsScreen } from "./screen-health-products.jsx";
import api from "../services/api.service.js";

// Sections with dedicated custom screens (not generic question form)
const SPECIAL_SECTIONS = {
  human_resources: HumanResourcesScreen,
  health_products: HealthProductsScreen,
};

// ── Section Form (one section at a time) ──────────────────────────────────────
function SectionForm({ section, responses, explanations, onAnswer, onExplain, onBack, onSave, isLast, saving }) {
  const questions  = (section.questions || []).filter(q => isQuestionVisible(q, responses));
  const completion = getSectionCompletion(section.questions || [], responses);
  const pct        = completion.total > 0
    ? Math.round((completion.answered / completion.total) * 100)
    : 0;

  const [g1, g2] = section.gradient ?? [section.color ?? "#6B7280", section.color ?? "#374151"];

  return (
    <div style={{ display:"flex", flexDirection:"column", height:"100%" }}>
      {/* Header */}
      <div style={{
        background:`linear-gradient(135deg, ${g1}, ${g2})`,
        padding:"20px 20px 24px",
      }}>
        <BackButton onBack={onBack} light />
        <div style={{ display:"flex", alignItems:"center", gap:12, marginTop:12 }}>
          <div style={{
            width:48, height:48, borderRadius:14,
            background:"rgba(255,255,255,0.2)",
            display:"flex", alignItems:"center", justifyContent:"center", fontSize:26,
          }}>
            {section.icon ?? "📋"}
          </div>
          <div>
            <div style={{ color:"white", fontSize:18, fontWeight:800 }}>{section.name}</div>
            <div style={{ color:"rgba(255,255,255,0.7)", fontSize:13 }}>{section.description}</div>
          </div>
        </div>
        <div style={{ marginTop:14 }}>
          <div style={{ display:"flex", justifyContent:"space-between", marginBottom:5 }}>
            <span style={{ color:"rgba(255,255,255,0.7)", fontSize:12 }}>Progress</span>
            <span style={{ color:"white", fontSize:12, fontWeight:700 }}>
              {completion.answered}/{completion.total} required
            </span>
          </div>
          <ProgressBar pct={pct} color="rgba(255,255,255,0.9)" height={5} />
        </div>
      </div>

      {/* Questions */}
      <div style={{ flex:1, overflowY:"auto", padding:"14px 16px 20px", background:T.bg }}>
        {questions.length === 0 ? (
          <div style={{ textAlign:"center", padding:"40px 20px", color:T.textMuted }}>
            <div style={{ fontSize:40, marginBottom:10 }}>✅</div>
            <div style={{ fontSize:15, fontWeight:600 }}>No questions in this section</div>
          </div>
        ) : (
          questions.map(q => (
            <QuestionCard
              key={q.id ?? q.question_code}
              question={q}
              value={responses[q.question_code]}
              explanation={explanations[q.question_code] ?? ""}
              onChange={val => onAnswer(q.question_code, val)}
              onExplainChange={val => onExplain(q.question_code, val)}
            />
          ))
        )}
      </div>

      {/* Save button */}
      <div style={{ padding:"12px 16px", background:T.card, borderTop:`1px solid ${T.border}` }}>
        <button
          onClick={onSave}
          disabled={saving || pct < 100}
          style={{
            width:"100%", padding:"15px", borderRadius:12, border:"none",
            background: pct === 100 ? `linear-gradient(135deg, ${g1}, ${g2})` : "#E5E7EB",
            color: pct === 100 ? "white" : T.textMuted,
            fontSize:15, fontWeight:700,
            cursor: (saving || pct < 100) ? "not-allowed" : "pointer",
            transition:"all 0.2s",
            opacity: saving ? 0.7 : 1,
          }}
        >
          {saving ? "Saving…" : isLast ? "Save Final Section →" : "Save & Continue →"}
        </button>
      </div>
    </div>
  );
}

// ── Assessment Form Screen ─────────────────────────────────────────────────────
export function AssessmentFormScreen({ user, sections, editAssessment, onBack, onComplete }) {
  const [view,              setView]              = useState("dashboard"); // dashboard | section
  const [activeSectionIdx,  setActiveSectionIdx]  = useState(0);
  const [completedSections, setCompletedSections] = useState(
    () => new Set(
      editAssessment?.section_progress
        ? Object.entries(editAssessment.section_progress)
            .filter(([, done]) => done)
            .map(([code]) => code)
        : Object.keys(editAssessment?.section_scores ?? {})
    )
  );
  const [responses,    setResponses]    = useState({});
  const [explanations, setExplanations] = useState({});
  const [saving,       setSaving]       = useState(false);
  const [saveError,    setSaveError]    = useState(null);
  const [loadingResp,  setLoadingResp]  = useState(false);

  // Facility data is read-only when editing a pre-loaded assessment
  const facilityData = {
    name:            editAssessment?.facility_name  ?? "",
    mfl_code:        editAssessment?.mfl_code       ?? "",
    county:          editAssessment?.county         ?? "",
    subcounty:       editAssessment?.subcounty      ?? "",
    assessment_type: editAssessment?.assessment_type ?? "Baseline",
    assessment_date: editAssessment?.assessment_date ?? new Date().toISOString().split("T")[0],
  };

  const assessmentId = editAssessment?.id;

  // ── Re-hydrate existing responses from API ──────────────────────────────
  useEffect(() => {
    if (!assessmentId) return;
    setLoadingResp(true);
    api.responses.list(assessmentId)
      .then(data => {
        setResponses(data?.responses ?? {});
      })
      .catch(e => console.error("Failed to load responses", e))
      .finally(() => setLoadingResp(false));
  }, [assessmentId]);

  const activeSection = sections[activeSectionIdx] ?? null;
  const allDone       = completedSections.size >= sections.length && sections.length > 0;

  const handleAnswer  = (code, val) => setResponses(p => ({ ...p, [code]: val }));
  const handleExplain = (code, val) => setExplanations(p => ({ ...p, [code]: val }));

  // ── Save one section ──────────────────────────────────────────────────────
  const handleSectionSave = async () => {
    if (!activeSection || !assessmentId) return;
    setSaving(true);
    setSaveError(null);
    try {
      // Build per-section response maps
      const sectionResponses     = {};
      const sectionExplanations  = {};
      (activeSection.questions ?? []).forEach(q => {
        const code = q.question_code;
        if (responses[code] !== undefined && responses[code] !== null && responses[code] !== "") {
          sectionResponses[code] = responses[code];
        }
        if (explanations[code]) {
          sectionExplanations[code] = explanations[code];
        }
      });

      await api.responses.bulkSave(assessmentId, activeSection.code, sectionResponses, sectionExplanations);
      await api.assessments.updateSectionProgress(assessmentId, activeSection.code, true);

      setCompletedSections(p => new Set([...p, activeSection.code]));
      setView("dashboard");

      // Auto-advance to the next incomplete section
      const nextIdx = sections.findIndex(
        (s, i) => i > activeSectionIdx && !completedSections.has(s.code) && s.code !== activeSection.code
      );
      if (nextIdx >= 0) setActiveSectionIdx(nextIdx);
    } catch (e) {
      console.error("Section save failed", e);
      setSaveError(e.message || "Failed to save. Please retry.");
    } finally {
      setSaving(false);
    }
  };

  // ── Submit whole assessment ───────────────────────────────────────────────
  const handleSubmit = async () => {
    if (!assessmentId) return;
    setSaving(true);
    setSaveError(null);
    try {
      await api.assessments.submit(assessmentId);
      onComplete(assessmentId);
    } catch (e) {
      console.error("Submit failed", e);
      setSaveError(e.message || "Failed to submit. Please retry.");
      setSaving(false);
    }
  };

  // ── Section view ──────────────────────────────────────────────────────────
  if (view === "section" && activeSection) {
    const SpecialScreen = SPECIAL_SECTIONS[activeSection.code];

    // Human Resources / Health Products → custom dedicated screens
    if (SpecialScreen) {
      return (
        <SpecialScreen
          assessment={editAssessment}
          onBack={() => { setSaveError(null); setView("dashboard"); }}
          onComplete={async (id) => {
            // Mark section done locally and return to dashboard
            await api.assessments.updateSectionProgress(assessmentId, activeSection.code, true)
              .catch(() => {}); // best-effort
            setCompletedSections(p => new Set([...p, activeSection.code]));
            setView("dashboard");
          }}
        />
      );
    }

    // Standard question-based sections
    return (
      <SectionForm
        section={activeSection}
        responses={responses}
        explanations={explanations}
        onAnswer={handleAnswer}
        onExplain={handleExplain}
        onBack={() => { setSaveError(null); setView("dashboard"); }}
        onSave={handleSectionSave}
        isLast={activeSectionIdx === sections.length - 1}
        saving={saving}
      />
    );
  }

  // ── Dashboard view ────────────────────────────────────────────────────────
  const overallPct = sections.length > 0
    ? Math.round((completedSections.size / sections.length) * 100)
    : 0;

  return (
    <div style={{ display:"flex", flexDirection:"column", height:"100%" }}>
      {/* Header */}
      <div style={{
        background:"linear-gradient(135deg, #1E3A5F 0%, #1D4ED8 100%)",
        padding:"20px 20px 24px",
      }}>
        <BackButton onBack={onBack} light />
        <div style={{ marginTop:12, color:"white", fontSize:20, fontWeight:800 }}>
          Continue Assessment
        </div>
        <div style={{ color:"rgba(255,255,255,0.6)", fontSize:13, marginTop:2 }}>
          {completedSections.size}/{sections.length} sections completed
        </div>
        <div style={{ marginTop:10 }}>
          <div style={{ height:4, background:"rgba(255,255,255,0.2)", borderRadius:999 }}>
            <div style={{
              height:"100%", width:`${overallPct}%`,
              background:"white", borderRadius:999, transition:"width 0.3s",
            }} />
          </div>
        </div>
      </div>

      <div style={{ flex:1, overflowY:"auto", padding:"14px 16px 100px", background:T.bg }}>

        {/* Loading responses indicator */}
        {loadingResp && (
          <div style={{ textAlign:"center", padding:"16px 0", color:T.textMuted, fontSize:13 }}>
            ⏳ Loading saved responses…
          </div>
        )}

        {/* Error banner */}
        {saveError && (
          <div style={{
            background:"#FEE2E2", color:"#991B1B", borderRadius:10,
            padding:"10px 14px", fontSize:13, marginBottom:14,
          }}>
            ⚠️ {saveError}
          </div>
        )}

        {/* Facility info (read-only) */}
        <div style={{ background:T.card, borderRadius:T.radius, padding:16, marginBottom:14, boxShadow:T.shadow }}>
          <div style={{ fontSize:13, fontWeight:700, color:T.textMid, marginBottom:10 }}>
            🏥 Facility Info
          </div>
          <div style={{ display:"grid", gridTemplateColumns:"1fr 1fr", gap:8 }}>
            {[
              { label:"Facility", value: facilityData.name || "—" },
              { label:"MFL Code", value: facilityData.mfl_code || "—" },
              { label:"County",   value: facilityData.county || "—" },
              { label:"Sub-County", value: facilityData.subcounty || "—" },
              { label:"Type",     value: facilityData.assessment_type || "—" },
              { label:"Date",     value: facilityData.assessment_date || "—" },
            ].map(({ label, value }) => (
              <div key={label}>
                <div style={{ fontSize:10, color:T.textMuted, fontWeight:600, textTransform:"uppercase", letterSpacing:0.6 }}>
                  {label}
                </div>
                <div style={{ fontSize:13, color:T.text, fontWeight:600, marginTop:2 }}>{value}</div>
              </div>
            ))}
          </div>
        </div>

        {/* Sections list */}
        <div style={{ fontSize:12, fontWeight:700, color:T.textMuted, textTransform:"uppercase", letterSpacing:1, marginBottom:10 }}>
          Assessment Sections
        </div>

        {sections.map((s, i) => {
          const done    = completedSections.has(s.code);
          const comp    = getSectionCompletion(s.questions ?? [], responses);
          const pct     = comp.total > 0 ? Math.round((comp.answered / comp.total) * 100) : 0;
          const [g1, g2] = s.gradient ?? [s.color ?? "#6B7280", s.color ?? "#374151"];
          return (
            <button
              key={s.id ?? s.code}
              onClick={() => { setActiveSectionIdx(i); setView("section"); setSaveError(null); }}
              style={{
                width:"100%", background:T.card, borderRadius:T.radius,
                padding:"14px 16px", marginBottom:10,
                border:`2px solid ${done ? "#10B981" : T.border}`,
                cursor:"pointer", textAlign:"left", boxShadow:T.shadow,
                display:"flex", alignItems:"center", gap:14,
              }}
            >
              {/* Section icon */}
              <div style={{
                width:44, height:44, borderRadius:12, flexShrink:0,
                background:`linear-gradient(135deg, ${g1}, ${g2})`,
                display:"flex", alignItems:"center", justifyContent:"center", fontSize:22,
              }}>
                {s.icon ?? "📋"}
              </div>
              <div style={{ flex:1, minWidth:0 }}>
                <div style={{ fontWeight:700, fontSize:14, color:T.text }}>{s.name}</div>
                <div style={{ fontSize:12, color:T.textSub, marginTop:2 }}>{s.description}</div>
                {comp.total > 0 && (
                  <div style={{ marginTop:6 }}>
                    <ProgressBar pct={pct} color={done ? "#10B981" : "#3B82F6"} height={4} />
                    <div style={{ fontSize:11, color:T.textMuted, marginTop:3 }}>
                      {comp.answered}/{comp.total} answered
                    </div>
                  </div>
                )}
              </div>
              <div style={{
                width:32, height:32, borderRadius:8,
                background: done ? "#D1FAE5" : T.borderLight,
                display:"flex", alignItems:"center", justifyContent:"center",
                fontSize:16, flexShrink:0,
              }}>
                {done ? "✅" : "→"}
              </div>
            </button>
          );
        })}

        {/* Submit button — only when all sections are done */}
        {allDone && (
          <button
            onClick={handleSubmit}
            disabled={saving}
            style={{
              width:"100%", padding:16, borderRadius:T.radius, border:"none",
              background: saving ? "#D1D5DB" : "linear-gradient(135deg, #064E3B, #059669)",
              color:"white", fontSize:16, fontWeight:700,
              cursor: saving ? "not-allowed" : "pointer",
              marginTop:10, transition:"all 0.2s",
              opacity: saving ? 0.7 : 1,
            }}
          >
            {saving ? "Submitting…" : "✅ Submit Assessment"}
          </button>
        )}
      </div>
    </div>
  );
}
