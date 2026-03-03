import { useState, useEffect } from "react";

// ── Shared ────────────────────────────────────────────────────────────────────
import { SECTION_META, calcGrade } from "./constants.js";
import { PhoneShell, BottomNav } from "./components/shared-components.jsx";

// ── Screens ───────────────────────────────────────────────────────────────────
import { LoginScreen }            from "./screens/screen-login.jsx";
import { DashboardScreen }        from "./screens/screen-dashboard.jsx";
import { AssessmentsListScreen }  from "./screens/screen-assessments-list.jsx";
import { AssessmentDetailScreen } from "./screens/screen-assessment-detail.jsx";
import { AssessmentFormScreen }   from "./screens/screen-assessment-form.jsx";
import { ReportsScreen }          from "./screens/screen-reports.jsx";
import { ProfileScreen }          from "./screens/screen-profile.jsx";

// ── API service (swap mock below for real calls) ───────────────────────────────
 import api from "./services/api.service.js";

// ─────────────────────────────────────────────────────────────────────────────
// MOCK DATA — remove once API is live
// ─────────────────────────────────────────────────────────────────────────────
const MOCK_SECTIONS = [
  { id:1, code:"infrastructure",      name:"Infrastructure",      description:"Physical infrastructure and bed capacity",   icon:"🏗️", color:"#8B5CF6", gradient:["#8B5CF6","#7C3AED"], order:1, questions:[] },
  { id:2, code:"skills_lab",          name:"Skills Lab",          description:"Simulation and skills training resources",    icon:"🔬", color:"#10B981", gradient:["#10B981","#059669"], order:2, questions:[] },
  { id:3, code:"human_resources",     name:"Human Resources",     description:"Staffing levels and competency",              icon:"👥", color:"#F59E0B", gradient:["#F59E0B","#D97706"], order:3, questions:[] },
  { id:4, code:"health_products",     name:"Health Products",     description:"Medicines, commodities and supply chain",     icon:"💊", color:"#EF4444", gradient:["#EF4444","#DC2626"], order:4, questions:[] },
  { id:5, code:"information_systems", name:"Information Systems", description:"Data quality and HMIS",                       icon:"💻", color:"#06B6D4", gradient:["#06B6D4","#0891B2"], order:5, questions:[] },
  { id:6, code:"quality_of_care",     name:"Quality of Care",     description:"Clinical practice and patient outcomes",      icon:"⭐", color:"#EC4899", gradient:["#EC4899","#DB2777"], order:6, questions:[] },
];

const MOCK_ASSESSMENTS = [
  {
    id:1, facility_name:"Kenyatta National Hospital", mfl_code:"10001",
    county:"Nairobi", subcounty:"Starehe",
    assessment_type:"Baseline", assessment_date:"2025-11-12",
    assessor_name:"Dr. Alphonce Ochieng", assessor_contact:"alphonce@mnch.go.ke",
    status:"completed", overall_percentage:82, overall_grade:"green",
    completed_at:"2025-11-12",
    section_scores:{
      infrastructure:      { percentage:90, grade:"green",  answered_questions:8, total_questions:8 },
      skills_lab:          { percentage:75, grade:"yellow", answered_questions:5, total_questions:6 },
      human_resources:     { percentage:83, grade:"green",  answered_questions:6, total_questions:6 },
      health_products:     { percentage:80, grade:"green",  answered_questions:6, total_questions:6 },
      information_systems: { percentage:80, grade:"green",  answered_questions:5, total_questions:5 },
      quality_of_care:     { percentage:83, grade:"green",  answered_questions:6, total_questions:6 },
    },
    responses: { INFRA_NBU:"Yes", INFRA_TRAINING:"Yes", INFRA_WATER:"Yes", INFRA_POWER:"Partial", SKILLS_MASTER:"Yes", HR_MENTOR:"Yes", HP_MEDS:"Yes", IS_RECORDS:"Yes", QC_PROTOCOLS:"Yes" },
  },
  {
    id:2, facility_name:"Pumwani Maternity Hospital", mfl_code:"10056",
    county:"Nairobi", subcounty:"Pumwani",
    assessment_type:"Midline", assessment_date:"2025-12-03",
    assessor_name:"Dr. Alphonce Ochieng", assessor_contact:"alphonce@mnch.go.ke",
    status:"completed", overall_percentage:61, overall_grade:"yellow",
    completed_at:"2025-12-03",
    section_scores:{
      infrastructure:      { percentage:60, grade:"yellow", answered_questions:6, total_questions:8 },
      skills_lab:          { percentage:50, grade:"yellow", answered_questions:4, total_questions:6 },
      human_resources:     { percentage:67, grade:"yellow", answered_questions:5, total_questions:6 },
      health_products:     { percentage:67, grade:"yellow", answered_questions:5, total_questions:6 },
      information_systems: { percentage:60, grade:"yellow", answered_questions:4, total_questions:5 },
      quality_of_care:     { percentage:58, grade:"yellow", answered_questions:4, total_questions:6 },
    },
    responses: { INFRA_NBU:"Yes", INFRA_TRAINING:"Partial", INFRA_WATER:"Yes", SKILLS_MASTER:"Yes", HR_MENTOR:"Yes", HP_MEDS:"Partial", IS_RECORDS:"Yes", QC_PROTOCOLS:"Partial" },
  },
  {
    id:3, facility_name:"Mbagathi District Hospital", mfl_code:"10102",
    county:"Nairobi", subcounty:"Langata",
    assessment_type:"Baseline", assessment_date:"2026-01-15",
    status:"in_progress", overall_percentage:0, overall_grade:null,
    section_scores:{}, responses:{},
  },
];

// ─────────────────────────────────────────────────────────────────────────────
// ROOT APP
// ─────────────────────────────────────────────────────────────────────────────
export default function App() {
  const [user,        setUser]        = useState(null);
  const [tab,         setTab]         = useState("dashboard");
  const [modal,       setModal]       = useState(null); // { type: "detail"|"form", data }
  const [assessments, setAssessments] = useState(null);
  const [sections,    setSections]    = useState(null);
  const [loading,     setLoading]     = useState(false);

  // ── Load schema + assessments after login ────────────────────────────────
  useEffect(() => {
    if (!user) return;
    loadData();
  }, [user]);

  const loadData = async () => {
    setLoading(true);
    try {
      // ── PRODUCTION: replace mock data with real API calls ──────────────
       const [schemaRes, assessRes] = await Promise.all([
         api.sections.fullSchema(),
         api.assessments.list(),
       ]);
       const sectionsWithMeta = schemaRes.data.map(s => ({
         ...s,
         gradient: SECTION_META[s.code]?.gradient || [s.color, s.color],
       }));
       setSections(sectionsWithMeta);
       setAssessments(assessRes.data);
      // ─────────────────────────────────────────────────────────────────
    } catch (e) {
      console.error("Load error", e);
    } finally {
      setLoading(false);
    }
  };

  const handleLogin   = (u) => { setUser(u); setTab("dashboard"); };
  const handleLogout  = () => { setUser(null); setModal(null); setTab("dashboard"); /* api.clearToken() */ };

  const openDetail    = (a)  => setModal({ type:"detail", data:a });
  const openNew       = ()   => setModal({ type:"form",   data:null });
  const openContinue  = (a)  => setModal({ type:"form",   data:a });
  const closeModal    = ()   => setModal(null);

  const handleTabChange = (t) => {
    if (t === "new") { openNew(); return; }
    setTab(t);
    setModal(null);
  };

  const handleAssessmentComplete = (completed) => {
    setAssessments(prev => {
      const idx = prev.findIndex(a => a.id === completed.id);
      if (idx >= 0) {
        const next = [...prev];
        next[idx] = completed;
        return next;
      }
      return [completed, ...prev];
    });
    setModal({ type:"detail", data:completed });
  };

  // ── Layout ────────────────────────────────────────────────────────────────
  const userAssessments = assessments; // filter by user.id in production

  const sectionAverages = sections.map(s => {
    const scores = assessments.filter(a => a.status === "completed").map(a => a.section_scores[s.code]?.percentage || 0);
    return { ...s, average_pct: scores.length ? Math.round(scores.reduce((a,b) => a+b, 0) / scores.length) : 0 };
  });

  return (
    <PhoneShell>
      {/* ── Not logged in ── */}
      {!user && (
        <div style={{ position:"absolute", inset:0 }}>
          <LoginScreen onLogin={handleLogin} />
        </div>
      )}

      {/* ── Logged in, no modal ── */}
      {user && !modal && (
        <>
          <div style={{ position:"absolute", inset:0, bottom:56, overflow:"hidden" }}>
            {tab === "dashboard" && (
              <DashboardScreen
                user={user}
                assessments={userAssessments}
                onViewAssessment={openDetail}
                onNewAssessment={openNew}
                loading={loading}
              />
            )}
            {tab === "assessments" && (
              <AssessmentsListScreen
                assessments={userAssessments}
                sections={sections}
                onView={openDetail}
                loading={loading}
              />
            )}
            {tab === "reports" && (
              <ReportsScreen
                user={user}
                assessments={userAssessments}
                sectionAverages={sectionAverages}
                loading={loading}
              />
            )}
            {tab === "profile" && (
              <ProfileScreen
                user={user}
                assessments={userAssessments}
                onUpdateUser={setUser}
                onLogout={handleLogout}
              />
            )}
          </div>
          <div style={{ position:"absolute", bottom:0, left:0, right:0, height:56, zIndex:100 }}>
            <BottomNav active={tab} onChange={handleTabChange} />
          </div>
        </>
      )}

      {/* ── Assessment detail modal ── */}
      {user && modal?.type === "detail" && (
        <div style={{ position:"absolute", inset:0 }}>
          <AssessmentDetailScreen
            assessment={modal.data}
            sections={sections}
            onBack={closeModal}
            onContinue={openContinue}
          />
        </div>
      )}

      {/* ── New / continue form modal ── */}
      {user && modal?.type === "form" && (
        <div style={{ position:"absolute", inset:0 }}>
          <AssessmentFormScreen
            user={user}
            sections={sections}
            editAssessment={modal.data}
            onBack={closeModal}
            onComplete={handleAssessmentComplete}
          />
        </div>
      )}
    </PhoneShell>
  );
}
