// screen-mentee-manager.jsx — Search, add, invite and remove mentees from a class
import { useState, useEffect, useRef } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

function Avatar({ name, size = 36, bg = T.primaryGhost, color = T.primary }) {
    const initials = (name ?? "?").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase();
    return (
        <div style={{
            width: size, height: size, borderRadius: "50%", background: bg,
            display: "flex", alignItems: "center", justifyContent: "center",
            fontWeight: 700, fontSize: size * 0.35, color, flexShrink: 0,
        }}>
            {initials}
        </div>
    );
}

export function MenteeManagerScreen({ cls, onBack }) {
    const [mentees, setMentees]           = useState(cls.mentees ?? []);
    const [search, setSearch]             = useState("");
    const [results, setResults]           = useState([]);
    const [searching, setSearching]       = useState(false);
    const [adding, setAdding]             = useState(null);
    const [removing, setRemoving]         = useState(null);
    const [enrollLink, setEnrollLink]     = useState(null);
    const [linkLoading, setLinkLoading]   = useState(false);
    const [copied, setCopied]             = useState(false);
    const [error, setError]               = useState(null);
    const [tab, setTab]                   = useState("roster"); // "roster" | "search" | "invite"
    const searchTimer = useRef(null);

    // Load enrollment link on mount
    useEffect(() => {
        setLinkLoading(true);
        api.classLifecycle.enrollmentLink(cls.id)
            .then(d => setEnrollLink(d?.data ?? null))
            .catch(() => {})
            .finally(() => setLinkLoading(false));
    }, [cls.id]);

    // Debounced user search
    useEffect(() => {
        clearTimeout(searchTimer.current);
        if (search.trim().length < 2) { setResults([]); return; }
        searchTimer.current = setTimeout(() => {
            setSearching(true);
            api.lookups.userSearch(search.trim())
                .then(d => setResults(Array.isArray(d?.data) ? d.data : []))
                .catch(() => setResults([]))
                .finally(() => setSearching(false));
        }, 400);
        return () => clearTimeout(searchTimer.current);
    }, [search]);

    const enrolledIds = new Set(mentees.map(m => m.user_id ?? m.id));

    const handleAdd = async (user) => {
        setAdding(user.id); setError(null);
        try {
            const res = await api.classLifecycle.enrollMentee(cls.id, user.id);
            setMentees(prev => [...prev, { id: res?.data?.participant_id, user_id: user.id, name: user.name, email: user.email }]);
            setSearch("");
            setResults([]);
            setTab("roster");
        } catch (e) {
            setError(e.message ?? "Could not add mentee.");
        } finally {
            setAdding(null);
        }
    };

    const handleRemove = async (mentee) => {
        if (!window.confirm(`Remove ${mentee.name} from this class?`)) return;
        setRemoving(mentee.id); setError(null);
        try {
            await api.classLifecycle.removeMentee(cls.id, mentee.id);
            setMentees(prev => prev.filter(m => m.id !== mentee.id));
        } catch (e) {
            setError(e.message ?? "Could not remove mentee.");
        } finally {
            setRemoving(null);
        }
    };

    const handleRegenerate = async () => {
        if (!window.confirm("Regenerate enrollment link? The old link will stop working.")) return;
        setLinkLoading(true);
        try {
            const res = await api.classLifecycle.regenerateToken(cls.id);
            setEnrollLink(res?.data ?? null);
        } catch (e) {
            setError(e.message ?? "Failed to regenerate.");
        } finally {
            setLinkLoading(false);
        }
    };

    const handleCopy = () => {
        if (!enrollLink?.url) return;
        navigator.clipboard?.writeText(enrollLink.url).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* ── Gradient Header ── */}
            <div style={{
                background: "linear-gradient(135deg, #1E1B4B 0%, #3730A3 60%, #6366F1 100%)",
                padding: "40px 16px 14px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 120, height: 120, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -30, right: -20 }} />
                <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 0 }}>
                    <button onClick={onBack} style={{ background: "rgba(255,255,255,0.12)", border: "none", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                    </button>
                    <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 800, fontSize: 16, color: "white" }}>Manage Mentees</div>
                        <div style={{ fontSize: 12, color: "rgba(255,255,255,0.55)" }}>{cls.name} · {mentees.length} enrolled</div>
                    </div>
                </div>
            </div>
            {/* Tabs on white background */}
            <div style={{ background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ display: "flex" }}>
                    {[
                        { key: "roster", label: `Roster (${mentees.length})` },
                        { key: "search", label: "Add Mentee" },
                        { key: "invite", label: "Invite Link" },
                    ].map(t => (
                        <button key={t.key} onClick={() => setTab(t.key)} style={{
                            flex: 1, padding: "10px 4px", border: "none", background: "none",
                            fontSize: 12, fontWeight: tab === t.key ? 700 : 500,
                            color: tab === t.key ? T.primary : T.textSub, cursor: "pointer",
                            borderBottom: tab === t.key ? `2px solid ${T.primary}` : "2px solid transparent",
                        }}>
                            {t.label}
                        </button>
                    ))}
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 12, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                {/* ── Roster tab ── */}
                {tab === "roster" && (
                    <>
                        {mentees.length === 0 && (
                            <div style={{ textAlign: "center", paddingTop: 40 }}>
                                <div style={{ fontSize: 40, marginBottom: 12 }}>👥</div>
                                <div style={{ color: T.textSub, fontSize: 14 }}>No mentees enrolled yet.</div>
                                <button onClick={() => setTab("search")} style={{
                                    marginTop: 16, padding: "9px 20px", borderRadius: T.radiusSm,
                                    background: T.primary, border: "none", color: "#fff",
                                    fontSize: 13, fontWeight: 700, cursor: "pointer",
                                }}>
                                    Add Mentees
                                </button>
                            </div>
                        )}
                        {mentees.map(m => (
                            <div key={m.id} style={{
                                background: T.card, borderRadius: T.radiusSm, padding: "12px 14px",
                                marginBottom: 8, boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                                display: "flex", alignItems: "center", gap: 12,
                            }}>
                                <Avatar name={m.name} />
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontWeight: 600, color: T.text, fontSize: 14 }}>{m.name}</div>
                                    {m.email && <div style={{ fontSize: 11, color: T.textSub, marginTop: 1 }}>{m.email}</div>}
                                </div>
                                <button
                                    onClick={() => handleRemove(m)}
                                    disabled={removing === m.id}
                                    style={{
                                        padding: "5px 12px", borderRadius: T.radiusSm,
                                        border: "1px solid #FECACA", background: "#FEF2F2",
                                        color: "#DC2626", fontSize: 12, fontWeight: 600,
                                        cursor: removing === m.id ? "not-allowed" : "pointer",
                                        opacity: removing === m.id ? 0.6 : 1, flexShrink: 0,
                                    }}
                                >
                                    {removing === m.id ? "…" : "Remove"}
                                </button>
                            </div>
                        ))}
                    </>
                )}

                {/* ── Search tab ── */}
                {tab === "search" && (
                    <>
                        <div style={{ position: "relative", marginBottom: 12 }}>
                            <input
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                placeholder="Search by name…"
                                autoFocus
                                style={{
                                    width: "100%", padding: "11px 40px 11px 14px", borderRadius: T.radiusSm,
                                    border: `1px solid ${T.border}`, fontSize: 14, boxSizing: "border-box",
                                    background: "#fff", color: T.text, outline: "none",
                                }}
                            />
                            <svg style={{ position: "absolute", right: 12, top: "50%", transform: "translateY(-50%)" }}
                                width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2">
                                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                            </svg>
                        </div>

                        {searching && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 20 }}>Searching…</div>}
                        {!searching && search.length >= 2 && results.length === 0 && (
                            <div style={{ color: T.textSub, textAlign: "center", paddingTop: 20 }}>No users found.</div>
                        )}
                        {search.length < 2 && (
                            <div style={{ color: T.textMuted, textAlign: "center", paddingTop: 20, fontSize: 13 }}>
                                Type at least 2 characters to search
                            </div>
                        )}

                        {results.map(u => {
                            const alreadyEnrolled = enrolledIds.has(u.id);
                            const isAdding = adding === u.id;
                            return (
                                <div key={u.id} style={{
                                    background: T.card, borderRadius: T.radiusSm, padding: "12px 14px",
                                    marginBottom: 8, boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                                    display: "flex", alignItems: "center", gap: 12,
                                    opacity: alreadyEnrolled ? 0.5 : 1,
                                }}>
                                    <Avatar name={u.name} />
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontWeight: 600, color: T.text, fontSize: 14 }}>{u.name}</div>
                                        <div style={{ fontSize: 11, color: T.textSub }}>
                                            {u.email}{u.facility_name ? ` · ${u.facility_name}` : ""}
                                        </div>
                                    </div>
                                    {alreadyEnrolled ? (
                                        <span style={{ fontSize: 11, color: "#065F46", background: "#D1FAE5", padding: "3px 8px", borderRadius: 20, flexShrink: 0 }}>Enrolled</span>
                                    ) : (
                                        <button
                                            onClick={() => handleAdd(u)}
                                            disabled={isAdding}
                                            style={{
                                                padding: "6px 16px", borderRadius: T.radiusSm, border: "none",
                                                background: isAdding ? T.border : T.primary,
                                                color: "#fff", fontSize: 13, fontWeight: 700,
                                                cursor: isAdding ? "not-allowed" : "pointer", flexShrink: 0,
                                            }}
                                        >
                                            {isAdding ? "Adding…" : "Add"}
                                        </button>
                                    )}
                                </div>
                            );
                        })}
                    </>
                )}

                {/* ── Invite link tab ── */}
                {tab === "invite" && (
                    <div>
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard, marginBottom: 14 }}>
                            <div style={{ fontSize: 13, fontWeight: 700, color: T.text, marginBottom: 6 }}>Enrollment Link</div>
                            <div style={{ fontSize: 12, color: T.textSub, marginBottom: 12, lineHeight: 1.6 }}>
                                Share this link with mentees to let them self-enroll in this class.
                            </div>

                            {linkLoading && <div style={{ color: T.textSub, fontSize: 13 }}>Loading…</div>}

                            {enrollLink && (
                                <>
                                    <div style={{
                                        background: T.bg, borderRadius: T.radiusXs, padding: "10px 12px",
                                        border: `1px solid ${T.border}`, fontSize: 12, color: T.text,
                                        wordBreak: "break-all", marginBottom: 12, lineHeight: 1.5,
                                    }}>
                                        {enrollLink.url}
                                    </div>
                                    <button onClick={handleCopy} style={{
                                        width: "100%", padding: "10px 0", borderRadius: T.radiusSm,
                                        border: "none", background: copied ? "#10B981" : T.gradientPrimary,
                                        color: "#fff", fontSize: 14, fontWeight: 700, cursor: "pointer",
                                        marginBottom: 10, boxShadow: `0 4px 12px ${T.primaryGlow}`,
                                        transition: "background 0.3s",
                                    }}>
                                        {copied ? "✓ Copied!" : "Copy Link"}
                                    </button>
                                </>
                            )}
                        </div>

                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard }}>
                            <div style={{ fontSize: 13, fontWeight: 700, color: T.text, marginBottom: 4 }}>Regenerate Link</div>
                            <div style={{ fontSize: 12, color: T.textSub, marginBottom: 12, lineHeight: 1.6 }}>
                                Creates a new link and invalidates the old one. Use this if the link has been shared with the wrong person.
                            </div>
                            <button onClick={handleRegenerate} disabled={linkLoading} style={{
                                width: "100%", padding: "10px 0", borderRadius: T.radiusSm,
                                border: "1.5px solid #FECACA", background: "#FEF2F2",
                                color: "#DC2626", fontSize: 14, fontWeight: 700,
                                cursor: linkLoading ? "not-allowed" : "pointer",
                                opacity: linkLoading ? 0.6 : 1,
                            }}>
                                {linkLoading ? "Regenerating…" : "Regenerate Link"}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
