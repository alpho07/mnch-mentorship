import { T, GRADE_COLOR } from "../constants.js";
import { GradeBadge, ProgressBar } from "../components/shared-components.jsx";

export function ReportsScreen({ user, assessments, sectionAverages, loading }) {
  const completed = (assessments || []).filter(a => a.status === "completed");
  const avgScore  = completed.length ? Math.round(completed.reduce((s, a) => s + (a.overall_percentage || 0), 0) / completed.length) : 0;

  return (
    <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
      <div style={{ background: "linear-gradient(135deg, #701A75 0%, #9333EA 100%)", padding: "52px 20px 24px" }}>
        <div style={{ color: "white", fontSize: 20, fontWeight: 800 }}>Reports & Analytics</div>
        <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 2 }}>{completed.length} completed assessments</div>
      </div>

      <div style={{ padding: "14px 16px 100px" }}>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginBottom: 14 }}>
          {[
            { label: "Average Score",      value: avgScore + "%",                                                 icon: "📊", color: "#8B5CF6" },
            { label: "Green Facilities",   value: completed.filter(a => a.overall_grade === "green").length,   icon: "✅", color: "#10B981" },
            { label: "Yellow Facilities",  value: completed.filter(a => a.overall_grade === "yellow").length,  icon: "⚠️", color: "#F59E0B" },
            { label: "Red Facilities",     value: completed.filter(a => a.overall_grade === "red").length,     icon: "❌", color: "#EF4444" },
          ].map(s => (
            <div key={s.label} style={{ background: T.card, borderRadius: T.radius, padding: "14px 16px", boxShadow: T.shadow }}>
              <div style={{ fontSize: 22 }}>{s.icon}</div>
              <div style={{ fontSize: 22, fontWeight: 800, color: s.color, marginTop: 6 }}>{s.value}</div>
              <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>{s.label}</div>
            </div>
          ))}
        </div>

        {sectionAverages && sectionAverages.length > 0 && (
          <div style={{ background: T.card, borderRadius: T.radius, padding: 16, marginBottom: 14, boxShadow: T.shadow }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid, marginBottom: 14 }}>Section Performance Averages</div>
            {sectionAverages.map(s => (
              <div key={s.code} style={{ marginBottom: 14 }}>
                <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 5 }}>
                  <span style={{ fontSize: 13, color: T.textMid }}>{s.icon} {s.name}</span>
                  <span style={{ fontSize: 12, fontWeight: 700, color: s.average_pct >= 80 ? "#10B981" : s.average_pct >= 50 ? "#F59E0B" : "#EF4444" }}>{s.average_pct}%</span>
                </div>
                <ProgressBar pct={s.average_pct} color={s.average_pct >= 80 ? "#10B981" : s.average_pct >= 50 ? "#F59E0B" : "#EF4444"} height={8} />
              </div>
            ))}
          </div>
        )}

        <div style={{ background: T.card, borderRadius: T.radius, padding: 16, boxShadow: T.shadow }}>
          <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid, marginBottom: 14 }}>Assessment Summary</div>
          {completed.length === 0 && (
            <div style={{ color: T.textMuted, fontSize: 13, textAlign: "center", padding: "20px 0" }}>No completed assessments yet.</div>
          )}
          {completed.map(a => (
            <div key={a.id} style={{ padding: "10px 0", borderBottom: "1px solid " + T.borderLight }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                <div>
                  <div style={{ fontSize: 13, fontWeight: 700, color: T.text }}>{a.facility_name}</div>
                  <div style={{ fontSize: 11, color: T.textSub, marginTop: 2 }}>{a.assessment_type} · {a.assessment_date}</div>
                </div>
                <GradeBadge grade={a.overall_grade} pct={a.overall_percentage} />
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
