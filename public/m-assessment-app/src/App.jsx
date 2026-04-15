import { useState, useEffect, useRef } from "react";
import { useConfirm } from "./hooks/useConfirm.jsx";

// ── Shared ────────────────────────────────────────────────────────────────────
import { SECTION_META, calcGrade, computeTabs, MENTOR_ROLES, MENTEE_ROLES, ADMIN_ROLES } from "./constants.js";
import { PhoneShell, BottomNav } from "./components/shared-components.jsx";
import { SyncIndicator } from "./components/sync-indicator.jsx";

// ── Screens ───────────────────────────────────────────────────────────────────
import { LoginScreen } from "./screens/screen-login.jsx";
import { DashboardScreen } from "./screens/screen-dashboard.jsx";
import { AssessmentsListScreen } from "./screens/screen-assessments-list.jsx";
import { AssessmentDetailScreen } from "./screens/screen-assessment-detail.jsx";
import { AssessmentFormScreen } from "./screens/screen-assessment-form.jsx";
import { AssessmentReportScreen } from "./screens/screen-assessment-report.jsx";
import { ReportsScreen } from "./screens/screen-reports.jsx";
import { ProfileScreen } from "./screens/screen-profile.jsx";
import { EmailJobsScreen } from "./screens/screen-email-jobs.jsx";
import { MentorshipsListScreen } from "./screens/screen-mentorships-list.jsx";
import { MentorshipDetailScreen } from "./screens/screen-mentorship-detail.jsx";
import { ClassDetailScreen } from "./screens/screen-class-detail.jsx";
import { ModuleDetailScreen } from "./screens/screen-module-detail.jsx";
import { AttendanceRosterScreen } from "./screens/screen-attendance-roster.jsx";
import { MyClassesScreen } from "./screens/screen-my-classes.jsx";
import { ClassProgressScreen } from "./screens/screen-class-progress.jsx";
import { TrainingsListScreen } from "./screens/screen-trainings-list.jsx";
import { TrainingDetailScreen } from "./screens/screen-training-detail.jsx";
import { MentorshipFormScreen } from "./screens/screen-mentorship-form.jsx";
import { SessionNotesScreen } from "./screens/screen-session-notes.jsx";
import { MenteeManagerScreen } from "./screens/screen-mentee-manager.jsx";
import { ClassFormScreen } from "./screens/screen-class-form.jsx";
import { ModulePickerScreen } from "./screens/screen-module-picker.jsx";

import api from "./services/api.service.js";

// ─────────────────────────────────────────────────────────────────────────────
// Pure helpers — outside component so identity never changes between renders
// ─────────────────────────────────────────────────────────────────────────────

function normaliseUser(u) {
    if (!u)
        return u;
    const fac = typeof u.facility === "object" && u.facility !== null ? u.facility : null;
    return {
        ...u,
        facility: fac?.name ?? (typeof u.facility === "string" ? u.facility : ""),
        county: fac?.county ?? u.county ?? "",
        subcounty: fac?.subcounty ?? u.subcounty ?? "",
        mfl_code: fac?.mfl_code ?? u.mfl_code ?? "",
        initials: u.initials || ((u.name || "??").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()),
    };
}

// Overall score = sum of 4 scored section percentages / 4 (matches Blade formula)
const SCORED_SECTION_CODES = ["infrastructure", "skills_lab", "information_systems", "quality_of_care"];
function calcOverallPct(sectionScores) {
    const vals = SCORED_SECTION_CODES.map(c => {
        const raw = sectionScores[c]?.percentage;
        if (raw == null) return null;
        const n = Number(raw);
        return isNaN(n) ? null : n;
    }).filter(v => v !== null);
    if (vals.length === 0) return null;
    return vals.reduce((a, b) => a + b, 0) / 4;
}

function enrichAssessment(a) {
    if (!a)
        return a;
    const sectionScores = a.section_scores ?? {};
    const calcPct = calcOverallPct(sectionScores);
    return {
        ...a,
        section_scores: sectionScores,
        section_progress: a.section_progress ?? {},
        responses: a.responses ?? {},
        facility_name: a.facility_name ?? "",
        mfl_code: a.mfl_code ?? "",
        county: a.county ?? "",
        subcounty: a.subcounty ?? "",
        // Always prefer client-side formula (4 sections ÷ 4); server value as fallback only
        overall_percentage: calcPct ?? (isNaN(Number(a.overall_percentage)) ? null : Number(a.overall_percentage) || null),
        overall_grade: calcPct != null ? calcGrade(calcPct) : a.overall_grade,
    };
}

// ─────────────────────────────────────────────────────────────────────────────
export default function App() {
    const [user, setUser] = useState(null);
    const [tab, setTab] = useState("dashboard");
    const [modal, setModal] = useState(null);
    const { confirm, ConfirmDialog } = useConfirm();
    const [navConfig, setNavConfig] = useState({ tabs: null, showFab: false });
    const [assessments, setAssessments] = useState(null); // null = not yet loaded
    const [sections, setSections] = useState(null); // null = not yet loaded
    const [facilities, setFacilities] = useState([]);
    const [showCreateSheet, setShowCreateSheet] = useState(false);
    const [showMentorshipWizard, setShowMentorshipWizard] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    // Prevents loadData re-firing when user object identity changes on re-renders
    const dataLoadedRef = useRef(false);

    // ── Session restore on mount (runs once) ─────────────────────────────────
    useEffect(() => {
        const token = api.getToken();
        if (!token)
            return;
        api.auth.me()
                .then(data => {
                    const u = data?.user ?? data;
                    if (u?.id) {
                        const normalised = normaliseUser(u);
                        setUser(normalised);
                        setNavConfig(computeTabs(normalised.roles ?? []));
                    }
                })
                .catch(() => api.clearToken());
    }, []);

    // ── assessment:id-resolved — swap temp offline ID with real server ID ─────
    useEffect(() => {
        const handler = (e) => {
            const { tempId, realId } = e.detail;
            setAssessments(prev =>
                (prev ?? []).map(a => a.id === tempId ? { ...a, id: realId, _isOffline: false } : a)
            );
            setModal(prev => {
                if (prev?.data?.id === tempId) {
                    return { ...prev, data: { ...prev.data, id: realId, _isOffline: false } };
                }
                return prev;
            });
        };
        window.addEventListener("assessment:id-resolved", handler);
        return () => window.removeEventListener("assessment:id-resolved", handler);
    }, []);

    // ── mentorship:id-resolved — swap temp offline ID with real server ID ──────
    useEffect(() => {
        const handler = (e) => {
            const { tempId, realId } = e.detail;
            setModal(prev => {
                if (prev?.type === "mentorshipDetail" && prev?.data?.id === tempId) {
                    return { ...prev, data: { ...prev.data, id: realId, _isOffline: false } };
                }
                return prev;
            });
        };
        window.addEventListener("mentorship:id-resolved", handler);
        return () => window.removeEventListener("mentorship:id-resolved", handler);
    }, []);

    // ── Load data when user.id becomes available ──────────────────────────────
    useEffect(() => {
        if (!user?.id) {
            dataLoadedRef.current = false;
            return;
        }
        if (dataLoadedRef.current)
            return;
        dataLoadedRef.current = true;
        runLoadData();
    }, [user?.id]);

    const runLoadData = async () => {
        setLoading(true);
        setError(null);
        try {
            const [schemaRes, assessRes] = await Promise.all([
                api.sections.fullSchema(),
                api.assessments.list(),
            ]);

            const rawSections = Array.isArray(schemaRes) ? schemaRes
                    : Array.isArray(schemaRes?.data) ? schemaRes.data : [];
            const rawAssessments = Array.isArray(assessRes) ? assessRes
                    : Array.isArray(assessRes?.data) ? assessRes.data : [];

            console.log(`[MNCH] ${rawSections.length} sections, ${rawAssessments.length} assessments`);

            setSections(rawSections.map(s => ({
                    ...s,
                    icon: SECTION_META[s.code]?.icon ?? s.icon ?? "📋",
                    gradient: SECTION_META[s.code]?.gradient ?? [s.color ?? "#6B7280", s.color ?? "#374151"],
                })));
            setAssessments(rawAssessments);

            // Pre-cache HR, HP, and response data for offline use (non-blocking)
            api.prefetchForOffline(rawAssessments).catch(() => {
            });
        } catch (e) {
            console.error("[MNCH] loadData error:", e);
            setError(e.message || "Failed to load. Please retry.");
            setSections(s => s ?? []);
            setAssessments(a => a ?? []);
        } finally {
            setLoading(false);
        }

        // Facilities are only needed for the creation sheet — fetch separately
        // so a 403/500 here never blocks the main load.
        api.facilities.list()
            .then(data => {
                const arr = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : [];
                setFacilities(arr);
            })
            .catch(() => { /* non-critical — facilities will load from cache or show warning */ });

        // ── Role-based background bootstrap ──────────────────────────────────
        const roleSet = new Set(user?.roles ?? []);
        const isMentorUser  = [...MENTOR_ROLES].some(r => roleSet.has(r));
        const isMenteeUser  = [...MENTEE_ROLES].some(r => roleSet.has(r));
        const isAdminUser   = [...ADMIN_ROLES].some(r => roleSet.has(r));

        if (isMentorUser) {
            api.mentorships.list().catch(() => {});
        }
        if (isMenteeUser) {
            api.me.classes().catch(() => {});
        }
        if (isMenteeUser || isAdminUser) {
            api.trainings.list().catch(() => {});
        }
        // Resources: load once, TTL guard is inside api.resources.list()
        api.resources.list().catch(() => {});
    };

    // ── Retry ─────────────────────────────────────────────────────────────────
    const handleRetry = () => {
        dataLoadedRef.current = false;
        setAssessments(null);
        setSections(null);
        setFacilities([]);
        setError(null);
        dataLoadedRef.current = true;
        runLoadData();
    };

    // ── Auth ──────────────────────────────────────────────────────────────────
    const handleLogin = (u) => {
        dataLoadedRef.current = false;
        setAssessments(null);
        setSections(null);
        setError(null);
        setModal(null);
        const normalised = normaliseUser(u);
        setUser(normalised);
        const config = computeTabs(normalised.roles ?? []);
        setNavConfig(config);
        setTab("dashboard");
    };

    const handleLogout = async () => {
        try {
            await api.auth.logout();
        } catch { /* ignore logout errors */ }
        // api.auth.logout already clears token + offlineStore
        api.sections.clearSchemaCache();
        dataLoadedRef.current = false;
        setUser(null);
        setAssessments(null);
        setSections(null);
        setFacilities([]);
        setModal(null);
        setTab("dashboard");
        setError(null);
    };

    // ── Navigation ────────────────────────────────────────────────────────────
    const handleTabChange = (t) => {
        if (t === "new") {
            setTab("assessments");
            setShowCreateSheet(true);
            setModal(null);
            return;
        }
        setTab(t);
        setModal(null);
    };

    // ── Assessment navigation ─────────────────────────────────────────────────
    const openDetail = (a) => setModal({type: "detail", data: a});
    const openContinue = (a) => setModal({type: "form", data: a});
    const openReport = (a) => setModal({type: "report", data: a});
    const closeModal = () => setModal(null);

    const handleCreate = (assessment) => {
        setAssessments(prev => [assessment, ...(prev ?? [])]);
        setModal({ type: "form", data: assessment });
    };

    const handleDelete = (id) => {
        setAssessments(prev => (prev ?? []).filter(a => a.id !== id));
        setModal(null);
    };

    // Go back from report → detail
    const backFromReport = () => {
        if (modal?.prevData)
            setModal({type: "detail", data: modal.prevData});
        else
            closeModal();
    };

    const handleAssessmentComplete = async (assessmentOrId) => {
        const id = assessmentOrId?.id ?? assessmentOrId;
        try {
            const res = await api.assessments.find(id);
            const updated = res?.data ?? res;
            if (!updated?.id)
                return;
            const enriched = enrichAssessment(updated);
            setAssessments(prev => {
                const list = prev ?? [];
                const idx = list.findIndex(a => a.id === enriched.id);
                if (idx >= 0) {
                    const n = [...list];
                    n[idx] = enriched;
                    return n;
                }
                return [enriched, ...list];
            });
            // After completion, go straight to the report
            setModal({type: "report", data: enriched, prevData: enriched});
        } catch (e) {
            console.error("Refresh failed", e);
            // Fallback: show detail screen
            if (assessmentOrId?.id)
                setModal({type: "detail", data: assessmentOrId});
        }
    };

    // ── Derived ───────────────────────────────────────────────────────────────
    const userAssessments = (assessments ?? []).map(enrichAssessment);
    const sectionsResolved = sections ?? [];
    const isLoading = loading || (!!user?.id && assessments === null && !error);

    const sectionAverages = sectionsResolved.map(s => {
        const scores = userAssessments
                .filter(a => a.status === "completed")
                .map(a => Number(a.section_scores?.[s.code]?.percentage) || 0);
        return {
            ...s,
            average_pct: scores.length
                    ? Math.round(scores.reduce((a, b) => a + b, 0) / scores.length)
                    : 0,
        };
    });

    // ── Render ────────────────────────────────────────────────────────────────
    return (
            <PhoneShell>
                {/* ── Sync status indicator (offline/syncing/error) ── */}
                {user && <SyncIndicator />}
            
                {/* ── Login ── */}
                {!user && (
                                    <div style={{position: "absolute", inset: 0}}>
                                        <LoginScreen onLogin={handleLogin} />
                                    </div>
                                )}
            
                {/* ── Tab screens (no modal) ── */}
                {user && !modal && (
                            <div style={{display: "flex", flexDirection: "column", height: "100%", position: "absolute", inset: 0}}>
                                <div style={{flex: 1, overflow: "hidden", position: "relative"}}>
                                    {tab === "dashboard" && (
                                                <DashboardScreen
                                                    user={user}
                                                    assessments={userAssessments}
                                                    onViewAssessment={openDetail}
                                                    loading={isLoading}
                                                    error={error}
                                                    onRetry={handleRetry}
                                                    />
                                        )}
                                    {tab === "assessments" && (
                                                <AssessmentsListScreen
                                                    assessments={userAssessments}
                                                    sections={sectionsResolved}
                                                    onView={openDetail}
                                                    loading={isLoading}
                                                    onCreate={handleCreate}
                                                    facilities={facilities}
                                                    user={user}
                                                    openSheet={showCreateSheet}
                                                    onSheetClose={() => setShowCreateSheet(false)}
                                                    />
                                        )}
                                    {tab === "reports" && (
                                                    <ReportsScreen
                                                                user={user}
                                                                assessments={userAssessments}
                                                                sectionAverages={sectionAverages}
                                                                loading={isLoading}
                                                                onViewAssessment={openDetail}
                                                                onViewEmailJobs={() => setModal({ type: "emailJobs" })}
                                                                />
                                        )}
                                    {tab === "profile" && (
                                                <ProfileScreen
                                                    user={user}
                                                    assessments={userAssessments}
                                                    onUpdateUser={u => setUser(normaliseUser(u))}
                                                    onLogout={handleLogout}
                                                    />
                                        )}
                                    {tab === "mentorship" && (
                                        <MentorshipsListScreen
                                            user={user}
                                            onOpen={(training) => setModal({ type: "mentorshipDetail", data: training })}
                                            onNew={() => setShowMentorshipWizard(true)}
                                        />
                                    )}
                                    {tab === "myClasses" && (
                                        <MyClassesScreen
                                            user={user}
                                            onOpen={(cls) => setModal({ type: "classProgress", data: cls })}
                                        />
                                    )}
                                    {tab === "trainings" && (
                                        <TrainingsListScreen
                                            user={user}
                                            onOpen={(t) => setModal({ type: "trainingDetail", data: t })}
                                        />
                                    )}
                                </div>
                                <div style={{flexShrink: 0, zIndex: 100}}>
                                    <BottomNav
                                        active={tab}
                                        onChange={handleTabChange}
                                        tabs={navConfig.tabs}
                                        showFab={navConfig.showFab}
                                    />
                                </div>
                            </div>
                        )}
            
                {/* ── Detail modal ── */}
                {user && modal?.type === "detail" && (
                            <div style={{position: "absolute", inset: 0}}>
                                <AssessmentDetailScreen
                                    assessment={modal.data}
                                    sections={sectionsResolved}
                                    onBack={closeModal}
                                    onContinue={openContinue}
                                    onViewReport={() => openReport(modal.data)}
                                    onDelete={handleDelete}
                                    />
                            </div>
                        )}
            
                {/* ── Form (continue) modal ── */}
                {user && modal?.type === "form" && modal.data && (
                            <div style={{position: "absolute", inset: 0}}>
                                    <AssessmentFormScreen
                                        user={user}
                                        sections={sectionsResolved}
                                        editAssessment={modal.data}
                                        onBack={closeModal}
                                        onComplete={handleAssessmentComplete}
                                        />
                                </div>
                        )}
            
                {/* ── Full report modal ── */}
                {user && modal?.type === "report" && modal.data && (
                            <div style={{position: "absolute", inset: 0}}>
                                <AssessmentReportScreen
                                    assessment={modal.data}
                                    onBack={backFromReport}
                                    />
                            </div>
                        )}

                {/* ── Email jobs modal ── */}
                {user && modal?.type === "emailJobs" && (
                            <div style={{position: "absolute", inset: 0}}>
                                <EmailJobsScreen onBack={closeModal} />
                            </div>
                        )}

                {user && modal?.type === "mentorshipDetail" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <MentorshipDetailScreen
                            training={modal.data}
                            onBack={closeModal}
                            onOpenClass={(cls) => setModal({ type: "classDetail", data: cls, prev: modal.data })}
                            onAddClass={() => setModal({ type: "classForm", data: null, prev: modal.data, trainingId: modal.data.id, fromMentorship: true })}
                            onEditClass={(cls) => setModal({ type: "classForm", data: cls, prev: modal.data, trainingId: modal.data.id, fromMentorship: true })}
                            onDeleteClass={async (cls) => {
                                const ok = await confirm({
                                    title: `Delete "${cls.name}"?`,
                                    message: "This class and all its modules, sessions, and attendance records will be permanently removed. This cannot be undone.",
                                    confirmLabel: "Delete Class",
                                    danger: true,
                                });
                                if (!ok) return;
                                try {
                                    await api.mentorships.deleteClass(modal.data.id, cls.id);
                                    setModal(prev => ({
                                        ...prev,
                                        data: {
                                            ...prev.data,
                                            classes: (prev.data.classes ?? []).filter(c => c.id !== cls.id),
                                        },
                                    }));
                                } catch (e) {
                                    alert(e.message ?? "Failed to delete class.");
                                }
                            }}
                        />
                    </div>
                )}
                {user && modal?.type === "classDetail" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <ClassDetailScreen
                            cls={modal.data}
                            confirm={confirm}
                            onBack={() => setModal({ type: "mentorshipDetail", data: modal.prev })}
                            onOpenModule={(mod) => setModal({ type: "moduleDetail", data: mod, prev: modal.data, mentorship: modal.prev })}
                            onManageMentees={() => setModal({ type: "menteeManager", data: modal.data, prev: modal.prev })}
                            onEditClass={() => setModal({ type: "classForm", data: modal.data, prev: modal.prev, trainingId: modal.prev?.id })}
                            onAddModule={() => setModal({ type: "modulePicker", data: modal.data, prev: modal.prev })}
                        />
                    </div>
                )}
                {user && modal?.type === "menteeManager" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <MenteeManagerScreen
                            cls={modal.data}
                            onBack={() => setModal({ type: "classDetail", data: modal.data, prev: modal.prev })}
                            confirm={confirm}
                        />
                    </div>
                )}
                {user && modal?.type === "classForm" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <ClassFormScreen
                            trainingId={modal.trainingId ?? modal.prev?.id}
                            existingClass={modal.data}
                            onBack={() => modal.fromMentorship
                                ? setModal({ type: "mentorshipDetail", data: modal.prev })
                                : setModal({ type: "classDetail", data: modal.data, prev: modal.prev })
                            }
                            onSaved={(updated) => {
                                if (modal.fromMentorship) {
                                    // New class or edit from mentorship detail — go back to mentorshipDetail
                                    // and patch the classes list in prev
                                    const savedClass = modal.data ? { ...modal.data, ...updated } : updated;
                                    const prevClasses = modal.prev?.classes ?? [];
                                    const alreadyExists = prevClasses.some(c => c.id === savedClass.id);
                                    const newClasses = alreadyExists
                                        ? prevClasses.map(c => c.id === savedClass.id ? savedClass : c)
                                        : [...prevClasses, savedClass];
                                    setModal({
                                        type: "mentorshipDetail",
                                        data: { ...modal.prev, classes: newClasses },
                                    });
                                } else {
                                    setModal({
                                        type: "classDetail",
                                        data: modal.data ? { ...modal.data, ...updated } : updated,
                                        prev: modal.prev,
                                    });
                                }
                            }}
                        />
                    </div>
                )}
                {user && modal?.type === "modulePicker" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <ModulePickerScreen
                            programId={modal.prev?.program_id}
                            existingModuleIds={(modal.data?.modules ?? []).map(m => m.program_module_id).filter(Boolean)}
                            onBack={() => setModal({ type: "classDetail", data: modal.data, prev: modal.prev })}
                            onPicked={async (programModuleId) => {
                                // Throws on error (picker catches and shows inline error)
                                const res = await api.modules.add(modal.data.id, programModuleId);
                                const newModule = res?.data;
                                if (newModule) {
                                    // Patch the class data so classDetail shows the new module immediately
                                    setModal(prev => ({
                                        ...prev,
                                        data: {
                                            ...prev.data,
                                            modules: [...(prev.data.modules ?? []), newModule],
                                        },
                                    }));
                                }
                            }}
                        />
                    </div>
                )}
                {user && modal?.type === "moduleDetail" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <ModuleDetailScreen
                            module={modal.data}
                            user={user}
                            onBack={() => setModal({ type: "classDetail", data: modal.prev, prev: modal.mentorship })}
                            onOpenAttendance={(mod) => setModal({ type: "attendanceRoster", data: mod, prev: modal.prev, mentorship: modal.mentorship })}
                            onOpenSession={(session) => setModal({ type: "sessionNotes", data: session, prev: modal.data, prevClass: modal.prev, mentorship: modal.mentorship })}
                        />
                    </div>
                )}
                {user && modal?.type === "sessionNotes" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <SessionNotesScreen
                            session={modal.data}
                            onBack={() => setModal({ type: "moduleDetail", data: modal.prev, prev: modal.prevClass, mentorship: modal.mentorship })}
                            onSaved={() => setModal({ type: "moduleDetail", data: modal.prev, prev: modal.prevClass, mentorship: modal.mentorship })}
                        />
                    </div>
                )}
                {user && modal?.type === "attendanceRoster" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <AttendanceRosterScreen
                            module={modal.data}
                            user={user}
                            onBack={() => setModal({ type: "moduleDetail", data: modal.data, prev: modal.prev, mentorship: modal.mentorship })}
                        />
                    </div>
                )}
                {user && modal?.type === "classProgress" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <ClassProgressScreen
                            cls={modal.data}
                            user={user}
                            onBack={closeModal}
                        />
                    </div>
                )}
                {user && modal?.type === "trainingDetail" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <TrainingDetailScreen
                            training={modal.data}
                            user={user}
                            onBack={closeModal}
                        />
                    </div>
                )}
                {user && showMentorshipWizard && (
                    <div style={{ position: "absolute", inset: 0, zIndex: 200 }}>
                        <MentorshipFormScreen
                            user={user}
                            onBack={() => setShowMentorshipWizard(false)}
                            onCreated={(training) => {
                                setShowMentorshipWizard(false);
                                setModal({ type: "mentorshipDetail", data: training });
                            }}
                        />
                    </div>
                )}
                <ConfirmDialog />
            </PhoneShell>
            );
}