import { useState, useEffect, useMemo } from 'react';
import api from '../services/api.service.js';
import { AnalyticsHomeScreen }    from '../screens/screen-analytics-home.jsx';
import { MentorshipsListScreen }  from '../screens/screen-mentorships-list.jsx';
import { MentorshipDetailScreen } from '../screens/screen-mentorship-detail.jsx';
import { MentorshipFormScreen }   from '../screens/screen-mentorship-form.jsx';
import { ClassDetailScreen }      from '../screens/screen-class-detail.jsx';
import { ClassFormScreen }        from '../screens/screen-class-form.jsx';
import { ModuleDetailScreen }     from '../screens/screen-module-detail.jsx';
import { ModulePickerScreen }     from '../screens/screen-module-picker.jsx';
import { AttendanceRosterScreen } from '../screens/screen-attendance-roster.jsx';
import { SessionNotesScreen }     from '../screens/screen-session-notes.jsx';
import { MenteeManagerScreen }    from '../screens/screen-mentee-manager.jsx';
import { MyClassesScreen }             from '../screens/screen-my-classes.jsx';
import { MentorshipReportsScreen }    from '../screens/screen-mentorship-reports.jsx';
import { ClassProgressScreen }    from '../screens/screen-class-progress.jsx';
import { MentorshipOverviewScreen } from '../screens/screen-mentorship-overview.jsx';

const STATUS_MAP = {
    active:    { bg: "#D1FAE5", color: "#065F46", stripe: "#10B981" },
    draft:     { bg: "#FEF3C7", color: "#92400E", stripe: "#F59E0B" },
    completed: { bg: "#F3F4F6", color: "#6B7280", stripe: "#9CA3AF" },
    cancelled: { bg: "#FEE2E2", color: "#991B1B", stripe: "#EF4444" },
};

// SVG nav icons keyed by tab id
const TAB_ICONS = {
    home: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? "#0097A7" : "#8BC8C8"} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" fill={active ? "rgba(0,151,167,0.08)" : "none"} />
            <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
    ),
    mentorships: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? "#0097A7" : "#8BC8C8"} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="8" r="4" fill={active ? "rgba(0,151,167,0.08)" : "none"} />
            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
            <path d="M17 11l2 2 4-4" stroke={active ? "#10B981" : "#8BC8C8"} />
        </svg>
    ),
    reports: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? "#0097A7" : "#8BC8C8"} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M18 20V10" />
            <path d="M12 20V4" />
            <path d="M6 20v-6" />
            <rect x="2" y="2" width="20" height="20" rx="2" fill={active ? "rgba(0,151,167,0.08)" : "none"} stroke="none"/>
        </svg>
    ),
    "my-classes": (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? "#0097A7" : "#8BC8C8"} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z" fill={active ? "rgba(0,151,167,0.08)" : "none"} />
            <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z" />
        </svg>
    ),
};

const MENTOR_TABS = [
    { id: 'home',        label: 'Home' },
    { id: 'mentorships', label: 'Mentorships' },
    { id: 'reports',     label: 'Reports' },
];

const MENTEE_TABS = [
    { id: 'home',       label: 'Home' },
    { id: 'my-classes', label: 'My Classes' },
];

function BottomNav({ tabs, active, onChange }) {
    return (
        <nav style={{
            position: 'fixed', bottom: 0, left: 0, right: 0,
            background: '#ffffff',
            backdropFilter: 'blur(12px)',
            WebkitBackdropFilter: 'blur(12px)',
            borderTop: '1px solid #EAF6F7',
            boxShadow: '0 -2px 16px rgba(0,0,0,0.06)',
            display: 'flex', zIndex: 50,
            paddingBottom: 'env(safe-area-inset-bottom)',
        }}>
            {tabs.map(tab => {
                const isActive = active === tab.id;
                const iconFn   = TAB_ICONS[tab.id];
                return (
                    <button key={tab.id} onClick={() => onChange(tab.id)} style={{
                        flex: 1, padding: '6px 4px 8px', background: 'none', border: 'none',
                        cursor: 'pointer', display: 'flex', flexDirection: 'column',
                        alignItems: 'center', justifyContent: 'center', gap: 3,
                        position: 'relative', transition: 'all 0.2s',
                    }}>
                        {isActive && (
                            <div style={{
                                position: 'absolute', top: 0, left: '25%', right: '25%',
                                height: 3, borderRadius: '0 0 99px 99px',
                                background: 'linear-gradient(135deg, #0097A7 0%, #26C6DA 100%)',
                            }} />
                        )}
                        <div style={{
                            width: 36, height: 36, display: 'flex', alignItems: 'center', justifyContent: 'center',
                            borderRadius: 12, background: isActive ? 'rgba(0,151,167,0.08)' : 'transparent',
                            transition: 'all 0.2s',
                        }}>
                            {iconFn ? iconFn(isActive) : null}
                        </div>
                        <span style={{
                            fontSize: 10, fontWeight: isActive ? 700 : 500,
                            color: isActive ? '#0097A7' : '#8BC8C8',
                            transition: 'all 0.2s',
                        }}>
                            {tab.label}
                        </span>
                    </button>
                );
            })}
        </nav>
    );
}

function StatCard({ label, value, color, bg, loading }) {
    return (
        <div style={{ background: bg, border: `1px solid ${color}22`, borderRadius: 14, padding: '14px 10px', textAlign: 'center' }}>
            <div style={{ fontSize: 26, fontWeight: 800, color, lineHeight: 1 }}>{loading ? '—' : value}</div>
            <div style={{ fontSize: 11, color: '#4A8080', marginTop: 3, fontWeight: 600 }}>{label}</div>
        </div>
    );
}

function HomeScreen({ user, onOpen, onNew }) {
    const [mentorships, setMentorships] = useState([]);
    const [loading, setLoading]         = useState(true);

    useEffect(() => {
        api.mentorships.list()
            .then(data => {
                const arr = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
                setMentorships(arr.filter(m => !m.is_trashed));
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const stats = useMemo(() => {
        const active    = mentorships.filter(m => m.status === 'active').length;
        const completed = mentorships.filter(m => m.status === 'completed').length;
        const draft     = mentorships.filter(m => m.status === 'draft').length;
        const classes   = mentorships.reduce((s, m) => s + (m.class_count ?? 0), 0);
        return { total: mentorships.length, active, completed, draft, classes };
    }, [mentorships]);

    const recents = useMemo(() => {
        const active = mentorships.filter(m => m.status === 'active');
        const draft  = mentorships.filter(m => m.status === 'draft');
        return [...active, ...draft].slice(0, 4);
    }, [mentorships]);

    return (
        <div style={{ paddingBottom: 16 }}>
            {/* Greeting */}
            <div style={{ padding: '20px 16px 14px' }}>
                <p style={{ color: '#1A3A3A', fontWeight: 800, fontSize: 22, margin: '0 0 2px' }}>
                    Welcome, {user?.name?.split(' ')[0]}
                </p>
                <p style={{ color: '#4A8080', fontSize: 13, margin: 0 }}>
                    {loading ? 'Loading your mentorships…' : stats.total === 0 ? 'No mentorships yet — start one below' : `${stats.active} active · ${stats.total} total mentorship${stats.total !== 1 ? 's' : ''}`}
                </p>
            </div>

            {/* Stats row */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, padding: '0 16px 14px' }}>
                <StatCard label="Active"    value={stats.active}    color="#10B981" bg="#ECFDF5" loading={loading} />
                <StatCard label="Total"     value={stats.total}     color="#0097A7" bg="#E0F7FA" loading={loading} />
                <StatCard label="Classes"   value={stats.classes}   color="#6366F1" bg="#EEF2FF" loading={loading} />
            </div>

            {/* Draft count pill */}
            {!loading && stats.draft > 0 && (
                <div style={{ padding: '0 16px 14px' }}>
                    <div style={{ background: '#FEF3C7', border: '1px solid #F59E0B44', borderRadius: 10, padding: '8px 14px', display: 'flex', alignItems: 'center', gap: 8 }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#92400E" strokeWidth="2" strokeLinecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span style={{ fontSize: 13, color: '#92400E', fontWeight: 600 }}>
                            {stats.draft} draft mentorship{stats.draft !== 1 ? 's' : ''} awaiting activation
                        </span>
                    </div>
                </div>
            )}

            {/* New mentorship CTA */}
            {onNew && (
                <div style={{ padding: '0 16px 16px' }}>
                    <button onClick={onNew} style={{
                        width: '100%', padding: '14px 0',
                        background: 'linear-gradient(135deg, #0097A7 0%, #26C6DA 100%)',
                        color: 'white', border: 'none', borderRadius: 14,
                        fontWeight: 700, fontSize: 15, cursor: 'pointer',
                        boxShadow: '0 4px 16px rgba(0,151,167,0.22)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
                    }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        New Mentorship
                    </button>
                </div>
            )}

            {/* Recent active/draft mentorships */}
            {!loading && recents.length > 0 && (
                <div style={{ padding: '0 16px' }}>
                    <p style={{ color: '#1A3A3A', fontWeight: 700, fontSize: 15, margin: '0 0 10px' }}>
                        Recent Mentorships
                    </p>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                        {recents.map(m => {
                            const s = STATUS_MAP[m.status] ?? STATUS_MAP.draft;
                            return (
                                <button key={m.id} onClick={() => onOpen?.(m)} style={{
                                    width: '100%', background: 'white',
                                    border: '1px solid #E0F2F1', borderRadius: 14,
                                    padding: 0, textAlign: 'left', cursor: 'pointer',
                                    boxShadow: '0 2px 8px rgba(0,0,0,0.06)', overflow: 'hidden', display: 'block',
                                }}>
                                    <div style={{ height: 3, background: `linear-gradient(90deg, ${s.stripe}, ${s.stripe}88)` }} />
                                    <div style={{ padding: '12px 14px' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8, marginBottom: 6 }}>
                                            <span style={{ fontSize: 14, fontWeight: 700, color: '#1A3A3A', flex: 1, lineHeight: 1.3 }}>{m.title}</span>
                                            <span style={{ fontSize: 10, fontWeight: 700, padding: '3px 8px', borderRadius: 20, background: s.bg, color: s.color, flexShrink: 0, whiteSpace: 'nowrap' }}>
                                                {m.status}
                                            </span>
                                        </div>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, fontSize: 12, color: '#4A8080' }}>
                                            {(m.facility || m.county) && <span>{m.facility ?? m.county}</span>}
                                            {m.program && <span style={{ background: '#F0F9FA', padding: '1px 7px', borderRadius: 8, border: '1px solid #E0F2F1' }}>{m.program}</span>}
                                            <span>{m.class_count ?? 0} class{(m.class_count ?? 0) !== 1 ? 'es' : ''}</span>
                                        </div>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Completed mentorships footer note */}
            {!loading && stats.completed > 0 && (
                <p style={{ textAlign: 'center', color: '#4A8080', fontSize: 12, margin: '16px 16px 0', padding: '12px 0', borderTop: '1px solid #E0F2F1' }}>
                    {stats.completed} completed mentorship{stats.completed !== 1 ? 's' : ''} — view all in the Mentorships tab
                </p>
            )}

            {/* Empty state */}
            {!loading && mentorships.length === 0 && (
                <div style={{ padding: '24px 16px', textAlign: 'center' }}>
                    <div style={{ width: 64, height: 64, borderRadius: 20, background: '#E0F7FA', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 14px' }}>
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0097A7" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="8" cy="7" r="3"/><circle cx="17" cy="7" r="3"/>
                            <path d="M2 21v-2a4 4 0 014-4h5"/><path d="M17 14l2 2 3-3"/>
                        </svg>
                    </div>
                    <p style={{ color: '#1A3A3A', fontWeight: 700, fontSize: 16, margin: '0 0 6px' }}>No mentorships yet</p>
                    <p style={{ color: '#4A8080', fontSize: 13, margin: 0 }}>Create your first mentorship to get started</p>
                </div>
            )}
        </div>
    );
}

function MenteeHomeScreen({ user }) {
    const [classes, setClasses] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.me.classes()
            .then(data => {
                const arr = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
                setClasses(arr);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const inProgress = classes.filter(c => c.status === 'active' || c.status === 'in_progress');
    const completed  = classes.filter(c => c.status === 'completed');

    return (
        <div style={{ paddingBottom: 16 }}>
            <div style={{ padding: '20px 16px 14px' }}>
                <p style={{ color: '#1A3A3A', fontWeight: 800, fontSize: 22, margin: '0 0 2px' }}>
                    Welcome, {user?.name?.split(' ')[0]}
                </p>
                <p style={{ color: '#4A8080', fontSize: 13, margin: 0 }}>
                    {loading ? 'Loading your classes…' : `${inProgress.length} active · ${classes.length} enrolled`}
                </p>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, padding: '0 16px 16px' }}>
                <StatCard label="Active"    value={inProgress.length} color="#10B981" bg="#ECFDF5" loading={loading} />
                <StatCard label="Enrolled"  value={classes.length}    color="#0097A7" bg="#E0F7FA" loading={loading} />
                <StatCard label="Done"      value={completed.length}  color="#6366F1" bg="#EEF2FF" loading={loading} />
            </div>

            {!loading && inProgress.length > 0 && (
                <div style={{ padding: '0 16px' }}>
                    <p style={{ color: '#1A3A3A', fontWeight: 700, fontSize: 15, margin: '0 0 10px' }}>Your Active Classes</p>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                        {inProgress.slice(0, 3).map(cls => (
                            <div key={cls.id} style={{ background: 'white', border: '1px solid #E0F2F1', borderRadius: 14, padding: '12px 14px', boxShadow: '0 2px 8px rgba(0,0,0,0.06)' }}>
                                <p style={{ color: '#1A3A3A', fontWeight: 700, fontSize: 14, margin: '0 0 4px' }}>{cls.name ?? cls.title ?? 'Class'}</p>
                                <p style={{ color: '#4A8080', fontSize: 12, margin: 0 }}>{cls.progress_percentage != null ? `${Math.round(cls.progress_percentage)}% complete` : 'In progress'}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {!loading && classes.length === 0 && (
                <div style={{ padding: '24px 16px', textAlign: 'center' }}>
                    <p style={{ color: '#1A3A3A', fontWeight: 700, fontSize: 16, margin: '0 0 6px' }}>No classes yet</p>
                    <p style={{ color: '#4A8080', fontSize: 13, margin: 0 }}>You haven't been enrolled in any classes</p>
                </div>
            )}
        </div>
    );
}

export function MentorshipsScope({ user, onLogout, onUserUpdate }) {
    const isMentee = (user?.roles ?? []).includes('mentee');
    const TABS = isMentee ? MENTEE_TABS : MENTOR_TABS;
    const [tab, setTab]     = useState('home');
    const [modal, setModal] = useState(null);

    if (modal?.type === 'mentorshipOverview') return (
        <MentorshipOverviewScreen
            mentorshipId={modal.data?.id ?? modal.data}
            onBack={() => setModal(null)}
            onViewDetail={(id) => setModal({ type: 'mentorshipDetail', data: modal.data })}
            onEdit={(id) => setModal({ type: 'mentorshipEdit', data: modal.data })}
            onViewMentees={(cls) => setModal({ type: 'menteeManager', data: cls, prev: modal.data })}
        />
    );
    if (modal?.type === 'mentorshipDetail') return (
        <MentorshipDetailScreen
            training={modal.data}
            user={user}
            onBack={() => setModal(null)}
            onOpenClass={(cls) => setModal({ type: 'classDetail', data: cls, prev: modal.data })}
            onAddClass={() => setModal({ type: 'classForm', data: null, prev: modal.data, trainingId: modal.data.id, fromMentorship: true })}
            onEditClass={(cls) => setModal({ type: 'classForm', data: cls, prev: modal.data, trainingId: modal.data.id, fromMentorship: true })}
            onDeleteClass={() => {}}
        />
    );
    if (modal?.type === 'mentorshipForm') return (
        <MentorshipFormScreen
            user={user}
            onBack={() => setModal(null)}
            onCreated={(t) => setModal({ type: 'mentorshipDetail', data: t })}
        />
    );
    if (modal?.type === 'mentorshipEdit') return (
        <MentorshipFormScreen
            user={user}
            existingMentorship={modal.data}
            onBack={() => setModal(null)}
            onCreated={(t) => setModal({ type: 'mentorshipDetail', data: t })}
        />
    );
    if (modal?.type === 'classDetail') return (
        <ClassDetailScreen
            cls={modal.data}
            onBack={() => setModal({ type: 'mentorshipDetail', data: modal.prev })}
            onOpenModule={(mod) => setModal({ type: 'moduleDetail', data: mod, prev: modal.data, mentorship: modal.prev })}
            onManageMentees={() => setModal({ type: 'menteeManager', data: modal.data, prev: modal.prev })}
            onEditClass={() => setModal({ type: 'classForm', data: modal.data, prev: modal.prev, trainingId: modal.prev?.id })}
            onAddModule={(currentModules) => setModal({
                type: 'modulePicker',
                data: modal.data,
                prev: modal.prev,
                existingModuleIds: (currentModules ?? []).map(m => m.program_module_id).filter(Boolean),
            })}
        />
    );
    if (modal?.type === 'classForm') return (
        <ClassFormScreen
            trainingId={modal.trainingId ?? modal.prev?.id}
            existingClass={modal.data}
            onBack={() => modal.fromMentorship
                ? setModal({ type: 'mentorshipDetail', data: modal.prev })
                : setModal({ type: 'classDetail', data: modal.data, prev: modal.prev })}
            onSaved={(updated) => {
                if (modal.fromMentorship) {
                    setModal({ type: 'mentorshipDetail', data: modal.prev });
                } else {
                    setModal({ type: 'classDetail', data: { ...modal.data, ...updated }, prev: modal.prev });
                }
            }}
        />
    );
    if (modal?.type === 'moduleDetail') return (
        <ModuleDetailScreen
            module={modal.data}
            user={user}
            onBack={() => modal.backTab ? setModal(null) : setModal({ type: 'classDetail', data: modal.prev, prev: modal.mentorship })}
            onOpenAttendance={(mod) => setModal({ type: 'attendance', data: mod, prev: modal.prev, mentorship: modal.mentorship })}
            onOpenSession={(sess, mod) => setModal({ type: 'sessionNotes', data: sess, module: mod, prev: modal.prev, mentorship: modal.mentorship })}
        />
    );
    if (modal?.type === 'attendance') return (
        <AttendanceRosterScreen
            module={modal.data}
            user={user}
            onBack={() => setModal({ type: 'moduleDetail', data: modal.data, prev: modal.prev, mentorship: modal.mentorship })}
        />
    );
    if (modal?.type === 'sessionNotes') return (
        <SessionNotesScreen
            session={modal.data}
            onBack={() => setModal({ type: 'moduleDetail', data: modal.module, prev: modal.prev, mentorship: modal.mentorship })}
            onSaved={() => setModal({ type: 'moduleDetail', data: modal.module, prev: modal.prev, mentorship: modal.mentorship })}
        />
    );
    if (modal?.type === 'menteeManager') return (
        <MenteeManagerScreen
            cls={modal.data}
            onBack={() => setModal({ type: 'classDetail', data: modal.data, prev: modal.prev })}
        />
    );
    if (modal?.type === 'modulePicker') return (
        <ModulePickerScreen
            programId={modal.data?.program_id ?? modal.prev?.program_id}
            existingModuleIds={modal.existingModuleIds ?? []}
            onBack={() => setModal({ type: 'classDetail', data: modal.data, prev: modal.prev })}
            onPicked={(programModuleId) => api.modules.add(modal.data.id, programModuleId)}
        />
    );
    if (modal?.type === 'classProgress') return (
        <ClassProgressScreen cls={modal.data} user={user} onBack={() => setModal(null)} />
    );

    return (
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#F0F9FA' }}>
            {tab === 'home' && !isMentee && <AnalyticsHomeScreen mode="mentorship" user={user} />}
            {tab === 'home' && isMentee  && <MenteeHomeScreen user={user} />}
            {tab === 'mentorships' && <MentorshipsListScreen user={user} onOpen={(t) => setModal({ type: 'mentorshipOverview', data: t })} onNew={() => setModal({ type: 'mentorshipForm', data: null })} onEdit={(t) => setModal({ type: 'mentorshipEdit', data: t })} />}
            {tab === 'reports'     && <MentorshipReportsScreen user={user} />}
            {tab === 'my-classes'  && <MyClassesScreen user={user} onModuleDetail={(mod, cls) => setModal({ type: 'moduleDetail', data: mod, prev: cls, mentorship: null, backTab: 'my-classes' })} />}
            <BottomNav tabs={TABS} active={tab} onChange={setTab} />
        </div>
    );
}
