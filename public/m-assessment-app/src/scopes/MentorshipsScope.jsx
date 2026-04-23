import { useState } from 'react';
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
import { MyClassesScreen }        from '../screens/screen-my-classes.jsx';
import { ClassProgressScreen }    from '../screens/screen-class-progress.jsx';
import { ProfileScreen }          from '../screens/screen-profile.jsx';

const MENTOR_TABS = [
    { id: 'home',        icon: '🏠', label: 'Home' },
    { id: 'mentorships', icon: '🎓', label: 'Mentorships' },
    { id: 'classes',     icon: '📚', label: 'Classes' },
    { id: 'profile',     icon: '👤', label: 'Profile' },
];

const MENTEE_TABS = [
    { id: 'home',       icon: '🏠', label: 'Home' },
    { id: 'my-classes', icon: '📚', label: 'My Classes' },
    { id: 'profile',    icon: '👤', label: 'Profile' },
];

function BottomNav({ tabs, active, onChange }) {
    return (
        <nav style={{ position: 'fixed', bottom: 0, left: 0, right: 0, background: '#1e293b', borderTop: '1px solid rgba(255,255,255,0.08)', display: 'flex', zIndex: 50, paddingBottom: 'env(safe-area-inset-bottom)' }}>
            {tabs.map(tab => (
                <button key={tab.id} onClick={() => onChange(tab.id)} style={{ flex: 1, padding: '10px 0 8px', background: 'none', border: 'none', cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3, color: active === tab.id ? '#0EA5E9' : 'rgba(255,255,255,0.35)', fontSize: 10, fontWeight: active === tab.id ? 700 : 400, transition: 'color 0.15s' }}>
                    <span style={{ fontSize: 20 }}>{tab.icon}</span>
                    <span>{tab.label}</span>
                </button>
            ))}
        </nav>
    );
}

function HomeScreen({ user }) {
    return <div style={{ padding: '24px 16px' }}><p style={{ color: 'white', fontWeight: 700, fontSize: 20 }}>Welcome, {user?.name?.split(' ')[0]}</p><p style={{ color: 'rgba(255,255,255,0.5)', fontSize: 14 }}>Manage your mentorship classes and track progress.</p></div>;
}

function MenteeHomeScreen({ user }) {
    return <div style={{ padding: '24px 16px' }}><p style={{ color: 'white', fontWeight: 700, fontSize: 20 }}>Welcome, {user?.name?.split(' ')[0]}</p><p style={{ color: 'rgba(255,255,255,0.5)', fontSize: 14 }}>View your class modules and track your learning progress.</p></div>;
}

export function MentorshipsScope({ user, header, onLogout, onUserUpdate }) {
    const isMentee = (user?.roles ?? []).includes('mentee');
    const TABS = isMentee ? MENTEE_TABS : MENTOR_TABS;
    const [tab, setTab]     = useState('home');
    const [modal, setModal] = useState(null);

    if (modal?.type === 'mentorshipDetail') return (
        <MentorshipDetailScreen
            training={modal.data}
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
    if (modal?.type === 'classDetail') return (
        <ClassDetailScreen
            cls={modal.data}
            onBack={() => setModal({ type: 'mentorshipDetail', data: modal.prev })}
            onOpenModule={(mod) => setModal({ type: 'moduleDetail', data: mod, prev: modal.data, mentorship: modal.prev })}
            onManageMentees={() => setModal({ type: 'menteeManager', data: modal.data, prev: modal.prev })}
            onEditClass={() => setModal({ type: 'classForm', data: modal.data, prev: modal.prev, trainingId: modal.prev?.id })}
            onAddModule={() => setModal({ type: 'modulePicker', data: modal.data, prev: modal.prev })}
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
            onBack={() => setModal({ type: 'classDetail', data: modal.prev, prev: modal.mentorship })}
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
            programId={modal.data?.program_id}
            existingModuleIds={[]}
            onBack={() => setModal({ type: 'classDetail', data: modal.data, prev: modal.prev })}
            onPicked={() => setModal({ type: 'classDetail', data: modal.data, prev: modal.prev })}
        />
    );
    if (modal?.type === 'classProgress') return (
        <ClassProgressScreen cls={modal.data} user={user} onBack={() => setModal(null)} />
    );

    return (
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#0f172a' }}>
            {header}
            {tab === 'home' && !isMentee && <HomeScreen user={user} />}
            {tab === 'home' && isMentee  && <MenteeHomeScreen user={user} />}
            {tab === 'mentorships' && <MentorshipsListScreen user={user} onOpen={(t) => setModal({ type: 'mentorshipDetail', data: t })} onNew={() => setModal({ type: 'mentorshipForm', data: null })} />}
            {tab === 'classes'     && <MentorshipsListScreen user={user} onOpen={(t) => setModal({ type: 'mentorshipDetail', data: t })} onNew={() => setModal({ type: 'mentorshipForm', data: null })} />}
            {tab === 'my-classes'  && <MyClassesScreen user={user} onOpen={(cls) => setModal({ type: 'classProgress', data: cls })} />}
            {tab === 'profile'     && <ProfileScreen user={user} assessments={[]} onUpdateUser={onUserUpdate} onLogout={onLogout} />}
            <BottomNav tabs={TABS} active={tab} onChange={setTab} />
        </div>
    );
}
