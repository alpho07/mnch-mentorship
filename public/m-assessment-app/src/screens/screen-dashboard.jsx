import { useState, useEffect } from "react";
import { T, GRADE_COLOR, GRADE_BG, GRADE_LABEL } from "../constants.js";
import { Avatar, GradeBadge, StatusChip, ProgressBar } from "../components/shared-components.jsx";

export function DashboardScreen({ user, assessments, onViewAssessment, onNewAssessment, loading }) {
  const completed  = assessments.filter(a => a.status === "completed");
  const inProgress = assessments.filter(a => a.status === "in_progress");
  const avgScore   = completed.length
    ? Math.round(completed.reduce((s,a) => s + (a.overall_percentage || 0), 0) / completed.length)
    : 0;

  return (
    <div style={{ height:"100%", overflowY:"auto", background:T.bg }}>
      {/* Header */}
      <div style={{
        background:"linear-gradient(135deg, #064E3B 0%, #047857 100%)",
        padding:"52px 20px 28px", position:"relative", overflow:"hidden",
      }}>
        <div style={{ position:"absolute", top:-30, right:-30, width:120, height:120, borderRadius:"50%", background:"rgba(255,255,255,0.05)" }} />
        <div style={{ display:"flex", alignItems:"center", justifyContent:"space-between" }}>
          <div>
            <div style={{ color:"rgba(255,255,255,0.6)", fontSize:12 }}>Good morning,</div>
            <div style={{ color:"white", fontSize:20, fontWeight:800, marginTop:2 }}>
              {user.name.split(" ")[0]} 👋
            </div>
            <div style={{ color:"rgba(255,255,255,0.55)", fontSize:12, marginTop:2 }}>
              {user.role} · {user.county}
            </div>
          </div>
          <Avatar initials={user.initials} size={46} color="rgba(255,255,255,0.2)" />
        </div>
      </div>

      <div style={{ padding:"0 16px 100px" }}>
        {/* Stats */}
        <div style={{ display:"grid", gridTemplateColumns:"1fr 1fr 1fr", gap:10, margin:"16px 0" }}>
          {[
            { label:"Total",     value:assessments.length, icon:"📋", color:"#6366F1" },
            { label:"Completed", value:completed.length,   icon:"✅", color:"#10B981" },
            { label:"Avg Score", value:`${avgScore}%`,     icon:"📊", color:"#F59E0B" },
          ].map(s => (
            <div key={s.label} style={{
              background:T.card, borderRadius:T.radius, padding:"14px 12px",
              boxShadow:T.shadow, textAlign:"center",
            }}>
              <div style={{ fontSize:22 }}>{s.icon}</div>
              <div style={{ fontSize:20, fontWeight:800, color:s.color, marginTop:4 }}>{s.value}</div>
              <div style={{ fontSize:11, color:T.textMuted, marginTop:2 }}>{s.label}</div>
            </div>
          ))}
        </div>

        {/* Grade distribution */}
        {completed.length > 0 && (
          <div style={{ background:T.card, borderRadius:T.radius, padding:16, marginBottom:14, boxShadow:T.shadow }}>
            <div style={{ fontSize:12, fontWeight:700, color:T.textMuted, textTransform:"uppercase", letterSpacing:1, marginBottom:12 }}>
              Grade Distribution
            </div>
            <div style={{ display:"flex", gap:8 }}>
              {["green","yellow","red"].map(g => {
                const cnt = completed.filter(a => a.overall_grade === g).length;
                const pct = Math.round((cnt / completed.length) * 100);
                return (
                  <div key={g} style={{ flex:1, textAlign:"center" }}>
                    <ProgressBar pct={pct} color={GRADE_COLOR[g]} height={6} />
                    <div style={{ fontSize:13, fontWeight:700, color:GRADE_COLOR[g], marginTop:5 }}>{cnt}</div>
                    <div style={{ fontSize:11, color:T.textMuted }}>{GRADE_LABEL[g]}</div>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* Resume in-progress banner */}
        {inProgress.length > 0 && (
          <div style={{
            background:"linear-gradient(135deg, #1D4ED8, #3B82F6)",
            borderRadius:T.radius, padding:"14px 16px", marginBottom:14, boxShadow:T.shadow,
          }}>
            <div style={{ color:"rgba(255,255,255,0.7)", fontSize:11, marginBottom:4 }}>Continue where you left off</div>
            <div style={{ color:"white", fontSize:14, fontWeight:700 }}>{inProgress[0].facility_name}</div>
            <button onClick={() => onViewAssessment(inProgress[0])} style={{
              marginTop:10, padding:"8px 16px", borderRadius:8,
              background:"rgba(255,255,255,0.2)", border:"none",
              color:"white", fontSize:13, fontWeight:700, cursor:"pointer",
            }}>Resume →</button>
          </div>
        )}

        {/* Assessments list */}
        <div style={{ display:"flex", alignItems:"center", justifyContent:"space-between", marginBottom:10 }}>
          <div style={{ fontSize:13, fontWeight:700, color:T.textMid, textTransform:"uppercase", letterSpacing:0.8 }}>
            My Assessments
          </div>
          <button onClick={onNewAssessment} style={{
            background:"linear-gradient(135deg, #064E3B, #059669)",
            color:"white", border:"none", borderRadius:8,
            padding:"7px 14px", fontSize:12, fontWeight:700, cursor:"pointer",
          }}>+ New</button>
        </div>

        {loading && (
          <div style={{ textAlign:"center", padding:"30px 0", color:T.textMuted }}>
            <div style={{ fontSize:30, marginBottom:8 }}>⏳</div>Loading assessments…
          </div>
        )}

        {!loading && assessments.length === 0 && (
          <div style={{ textAlign:"center", padding:"40px 20px", color:T.textMuted }}>
            <div style={{ fontSize:40, marginBottom:10 }}>📋</div>
            <div style={{ fontSize:15, fontWeight:600 }}>No assessments yet</div>
            <div style={{ fontSize:13, marginTop:6 }}>Tap "+ New" to start your first assessment</div>
          </div>
        )}

        {assessments.map(a => (
          <button key={a.id} onClick={() => onViewAssessment(a)} style={{
            width:"100%", background:T.card, borderRadius:T.radius,
            padding:"14px 16px", marginBottom:10,
            border:`2px solid ${a.overall_grade ? GRADE_BG[a.overall_grade] : T.borderLight}`,
            cursor:"pointer", textAlign:"left", boxShadow:T.shadow,
            display:"flex", alignItems:"center", gap:14,
          }}>
            <div style={{
              width:44, height:44, borderRadius:12, flexShrink:0,
              background: a.overall_grade ? GRADE_BG[a.overall_grade] : T.borderLight,
              display:"flex", alignItems:"center", justifyContent:"center", fontSize:22,
            }}>
              {a.status==="completed"
                ? (a.overall_grade==="green" ? "✅" : a.overall_grade==="yellow" ? "⚠️" : "❌")
                : "📝"}
            </div>
            <div style={{ flex:1, minWidth:0 }}>
              <div style={{ display:"flex", alignItems:"center", justifyContent:"space-between", gap:8 }}>
                <div style={{ fontWeight:700, fontSize:14, color:T.text, overflow:"hidden", textOverflow:"ellipsis", whiteSpace:"nowrap" }}>
                  {a.facility_name}
                </div>
                {a.overall_grade
                  ? <GradeBadge grade={a.overall_grade} pct={a.overall_percentage} />
                  : <StatusChip status={a.status} />}
              </div>
              <div style={{ fontSize:12, color:T.textSub, marginTop:3 }}>
                {a.assessment_type} · {a.assessment_date}
              </div>
              {a.status==="completed" && (
                <div style={{ marginTop:6 }}>
                  <ProgressBar pct={a.overall_percentage || 0} color={GRADE_COLOR[a.overall_grade]} height={3} />
                </div>
              )}
            </div>
            <div style={{ color:T.border, fontSize:18, flexShrink:0 }}>›</div>
          </button>
        ))}
      </div>
    </div>
  );
}
