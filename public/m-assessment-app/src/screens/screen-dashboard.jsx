import { T, GRADE_COLOR, GRADE_BG } from "../constants.js";
import { GradeBadge, StatusChip, ProgressBar } from "../components/shared-components.jsx";

export function DashboardScreen({ user, assessments, onViewAssessment, loading, error, onRetry }) {
    const list = assessments ?? [];
    const completed = list.filter(a => a.status === "completed");
    const inProgress = list.filter(a => a.status === "in_progress");
    const avgScore = completed.length
        ? Math.round(completed.reduce((s, a) => s + (a.overall_percentage ?? 0), 0) / completed.length)
        : 0;

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* Hero header */}
            <div style={{
                background: "linear-gradient(160deg, #064E3B 0%, #065F46 60%, #047857 100%)",
                padding: "52px 20px 28px",
                position: "relative", overflow: "hidden",
            }}>
                {[{ s: 160, t: -40, r: -40, o: 0.06 }, { s: 90, b: -20, l: 20, o: 0.05 }].map((c, i) => (
                    <div key={i} style={{
                        position: "absolute", width: c.s, height: c.s, borderRadius: "50%",
                        background: "white", opacity: c.o,
                        top: c.t, right: c.r, bottom: c.b, left: c.l,
                    }} />
                ))}
                <div style={{ color: "rgba(255,255,255,0.7)", fontSize: 13 }}>Welcome back 👋</div>
                <div style={{ color: "white", fontSize: 22, fontWeight: 900, marginTop: 4 }}>
                    {user?.name ?? "Assessor"}
                </div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 2 }}>
                    {user?.facility ?? user?.county ?? ""}
                </div>

                {/* Stats row */}
                <div style={{ display: "flex", gap: 10, marginTop: 18 }}>
                    {[
                        { label: "Total", value: list.length, color: "rgba(255,255,255,0.15)" },
                        { label: "Completed", value: completed.length, color: "rgba(16,185,129,0.3)" },
                        { label: "In Progress", value: inProgress.length, color: "rgba(245,158,11,0.3)" },
                        { label: "Avg Score", value: completed.length ? `${avgScore}%` : "—", color: "rgba(255,255,255,0.15)" },
                    ].map(({ label, value, color }) => (
                        <div key={label} style={{
                            flex: 1, background: color, borderRadius: 12, padding: "10px 8px", textAlign: "center",
                        }}>
                            <div style={{ color: "white", fontSize: 18, fontWeight: 800 }}>{value}</div>
                            <div style={{ color: "rgba(255,255,255,0.65)", fontSize: 10, marginTop: 2 }}>{label}</div>
                        </div>
                    ))}
                </div>
            </div>

            <div style={{ padding: "14px 16px 100px" }}>

                {/* In-progress resume banner */}
                {inProgress.length > 0 && (
                    <div style={{
                        background: "linear-gradient(135deg, #F59E0B, #D97706)",
                        borderRadius: T.radius, padding: "14px 16px", marginBottom: 14,
                        boxShadow: T.shadowMd,
                    }}>
                        <div style={{ color: "white", fontWeight: 800, fontSize: 14 }}>
                            📝 Assessment In Progress
                        </div>
                        <div style={{ color: "rgba(255,255,255,0.8)", fontSize: 12, marginTop: 3 }}>
                            {inProgress[0]?.facility_name ?? "Facility"} — tap to resume
                        </div>
                        <button
                            onClick={() => onViewAssessment(inProgress[0])}
                            style={{
                                marginTop: 10, padding: "8px 16px", borderRadius: 8,
                                background: "rgba(255,255,255,0.2)", border: "none",
                                color: "white", fontSize: 13, fontWeight: 700, cursor: "pointer",
                            }}
                        >
                            Resume →
                        </button>
                    </div>
                )}

                {/* Section title — no "+ New" button */}
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 10 }}>
                    <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid, textTransform: "uppercase", letterSpacing: 0.8 }}>
                        My Assessments
                    </div>
                </div>

                {/* Error state */}
                {error && (
                    <div style={{
                        background: "#FEE2E2", borderRadius: T.radius, padding: "16px",
                        marginBottom: 14, textAlign: "center",
                    }}>
                        <div style={{ fontSize: 30, marginBottom: 8 }}>⚠️</div>
                        <div style={{ fontSize: 14, fontWeight: 700, color: "#991B1B" }}>Failed to load</div>
                        <div style={{ fontSize: 12, color: "#B91C1C", marginTop: 4 }}>{error}</div>
                        {onRetry && (
                            <button onClick={onRetry} style={{
                                marginTop: 10, padding: "8px 18px", borderRadius: 8,
                                background: "#EF4444", color: "white", border: "none",
                                fontSize: 13, fontWeight: 700, cursor: "pointer",
                            }}>
                                Retry
                            </button>
                        )}
                    </div>
                )}

                {/* Loading state */}
                {loading && !error && (
                    <div style={{ textAlign: "center", padding: "30px 0", color: T.textMuted }}>
                        <div style={{ fontSize: 30, marginBottom: 8 }}>⏳</div>
                        Loading assessments…
                    </div>
                )}

                {/* Empty state - loaded successfully, zero results */}
                {!loading && !error && list.length === 0 && (
                    <div style={{ textAlign: "center", padding: "50px 24px", color: T.textMuted }}>
                        <div style={{ fontSize: 56, marginBottom: 16 }}>📋</div>
                        <div style={{ fontSize: 17, fontWeight: 700, color: T.textMid, marginBottom: 8 }}>
                            No assessments yet
                        </div>
                        <div style={{ fontSize: 13, lineHeight: 1.6, color: T.textSub }}>
                            Your assessments will appear here once they have been created and assigned to you by an administrator.
                        </div>
                        <div style={{
                            marginTop: 20, padding: "12px 16px",
                            background: "#F0FDF4", borderRadius: T.radiusSm,
                            border: "1px solid #BBF7D0",
                            fontSize: 12, color: "#065F46", textAlign: "left", lineHeight: 1.6,
                        }}>
                            <strong>What to do:</strong><br />
                            Contact your MNCH administrator to have facility assessments pre-loaded for your account.
                        </div>
                    </div>
                )}

                {/* Assessment cards */}
                {!loading && list.map(a => (
                    <button
                        key={a.id}
                        onClick={() => onViewAssessment(a)}
                        style={{
                            width: "100%", background: T.card, borderRadius: T.radius,
                            padding: "14px 16px", marginBottom: 10,
                            border: `2px solid ${a.overall_grade ? GRADE_BG[a.overall_grade] : T.borderLight}`,
                            cursor: "pointer", textAlign: "left", boxShadow: T.shadow,
                            display: "flex", alignItems: "center", gap: 14,
                        }}
                    >
                        <div style={{
                            width: 44, height: 44, borderRadius: 12, flexShrink: 0,
                            background: a.overall_grade ? GRADE_BG[a.overall_grade] : T.borderLight,
                            display: "flex", alignItems: "center", justifyContent: "center", fontSize: 22,
                        }}>
                            {a.status === "completed"
                                ? (a.overall_grade === "green" ? "✅" : a.overall_grade === "yellow" ? "⚠️" : "❌")
                                : "📝"}
                        </div>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8 }}>
                                <div style={{
                                    fontWeight: 700, fontSize: 14, color: T.text,
                                    overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap",
                                }}>
                                    {a.facility_name ?? "—"}
                                </div>
                                {a.overall_grade
                                    ? <GradeBadge grade={a.overall_grade} pct={a.overall_percentage} />
                                    : <StatusChip status={a.status} />
                                }
                            </div>
                            <div style={{ fontSize: 12, color: T.textSub, marginTop: 3 }}>
                                {[a.assessment_type, a.county, a.assessment_date].filter(Boolean).join(" · ")}
                            </div>
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
