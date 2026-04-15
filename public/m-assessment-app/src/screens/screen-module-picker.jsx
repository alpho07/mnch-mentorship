// screen-module-picker.jsx — Pick a program module to add to a class
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function ModulePickerScreen({ programId, existingModuleIds = [], onBack, onPicked }) {
    const [modules, setModules] = useState(null);
    const [loading, setLoading] = useState(true);
    const [adding, setAdding]   = useState(null); // id being added
    const [error, setError]     = useState(null);

    useEffect(() => {
        if (!programId) { setLoading(false); return; }
        api.lookups.programModules(programId)
            .then(d => setModules(Array.isArray(d) ? d : Array.isArray(d?.data) ? d.data : []))
            .catch(() => setModules([]))
            .finally(() => setLoading(false));
    }, [programId]);

    const handleAdd = async (mod) => {
        setAdding(mod.id); setError(null);
        try {
            const result = await onPicked(mod.id);
            // success — caller handles state update
        } catch (e) {
            setError(e.message ?? "Failed to add module.");
        } finally {
            setAdding(null);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{
                background: "linear-gradient(135deg, #1E1B4B 0%, #3730A3 60%, #6366F1 100%)",
                padding: "40px 16px 14px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 120, height: 120, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -30, right: -20 }} />
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <button onClick={onBack} style={{ background: "rgba(255,255,255,0.12)", border: "none", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                    </button>
                    <div style={{ fontWeight: 800, fontSize: 16, color: "white" }}>Add Module</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 12, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}
                {!programId && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40, fontSize: 14 }}>
                        No program linked to this mentorship.
                    </div>
                )}
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading modules…</div>}
                {!loading && modules?.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>No modules available.</div>
                )}
                {(modules ?? []).map(mod => {
                    const already = existingModuleIds.includes(mod.id);
                    const isAdding = adding === mod.id;
                    return (
                        <div key={mod.id} style={{
                            background: T.card, borderRadius: T.radiusSm, padding: "14px 16px",
                            marginBottom: 10, boxShadow: T.shadowCard, border: `1px solid ${already ? T.border : T.border}`,
                            display: "flex", alignItems: "flex-start", gap: 12,
                            opacity: already ? 0.5 : 1,
                        }}>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontWeight: 700, color: T.text, fontSize: 14 }}>
                                    {mod.order_sequence}. {mod.name}
                                </div>
                                {mod.description && (
                                    <div style={{ fontSize: 12, color: T.textSub, marginTop: 3, lineHeight: 1.5 }}>{mod.description}</div>
                                )}
                                <div style={{ fontSize: 11, color: T.textMuted, marginTop: 4 }}>
                                    {mod.session_count ?? 0} session{mod.session_count !== 1 ? "s" : ""}
                                </div>
                            </div>
                            {already ? (
                                <span style={{ fontSize: 11, color: T.textSub, padding: "4px 8px", background: T.bg, borderRadius: 6, flexShrink: 0 }}>Added</span>
                            ) : (
                                <button
                                    onClick={() => handleAdd(mod)}
                                    disabled={isAdding}
                                    style={{
                                        padding: "6px 16px", borderRadius: T.radiusSm, border: "none",
                                        background: isAdding ? T.border : T.primary,
                                        color: "#fff", fontSize: 13, fontWeight: 700,
                                        cursor: isAdding ? "not-allowed" : "pointer", flexShrink: 0,
                                    }}
                                >
                                    {isAdding ? "Adding…" : "+ Add"}
                                </button>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
