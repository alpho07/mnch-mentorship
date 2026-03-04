import { useState, useEffect } from "react";
import { T } from "../constants.js";
import { BackButton } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

// ── Available / Not Available toggle ──────────────────────────────────────────
function AvailabilityToggle({ value, onChange }) {
    return (
        <div style={{ display: "flex", gap: 6, flexShrink: 0 }}>
            {[true, false].map(opt => {
                const active = value === opt;
                const isAvail = opt === true;
                return (
                    <button key={String(opt)} onClick={() => onChange(opt)} style={{
                        padding: "6px 12px", borderRadius: 8, fontSize: 12, fontWeight: 700,
                        border: `2px solid ${active ? (isAvail ? "#10B981" : "#EF4444") : T.border}`,
                        background: active ? (isAvail ? "#D1FAE5" : "#FEE2E2") : T.borderLight,
                        color: active ? (isAvail ? "#065F46" : "#991B1B") : T.textMuted,
                        cursor: "pointer",
                        display: "flex", alignItems: "center", gap: 4,
                    }}>
                        <span>{isAvail ? "✓" : "✗"}</span>
                        <span>{isAvail ? "Yes" : "No"}</span>
                    </button>
                );
            })}
        </div>
    );
}

// ── Category section ──────────────────────────────────────────────────────────
function CategorySection({ category, deptId, responses, onChange }) {
    const [open, setOpen] = useState(true);
    const answered = category.commodities.filter(c => responses[`${deptId}_${c.commodity_id}`] !== undefined).length;
    const available = category.commodities.filter(c => responses[`${deptId}_${c.commodity_id}`] === true).length;

    return (
        <div style={{ background: T.card, borderRadius: T.radius, marginBottom: 10, overflow: "hidden", boxShadow: T.shadow }}>
            <button onClick={() => setOpen(o => !o)} style={{
                width: "100%", padding: "12px 16px", border: "none", background: "none",
                cursor: "pointer", display: "flex", alignItems: "center", gap: 10, textAlign: "left",
            }}>
                <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 700, fontSize: 13, color: T.text }}>{category.category_name}</div>
                    <div style={{ fontSize: 11, color: T.textSub, marginTop: 2 }}>
                        {category.commodities.length} items
                        {answered > 0 && ` · ${available}/${answered} available`}
                    </div>
                </div>
                <span style={{ color: T.textMuted, fontSize: 16, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
            </button>

            {open && (
                <div style={{ borderTop: `1px solid ${T.border}` }}>
                    {category.commodities.map((c, i) => {
                        const key = `${deptId}_${c.commodity_id}`;
                        const val = responses[key]; // true | false | undefined
                        return (
                            <div key={c.commodity_id} style={{
                                padding: "12px 16px",
                                borderBottom: i < category.commodities.length - 1 ? `1px solid ${T.borderLight}` : "none",
                                display: "flex", alignItems: "center", gap: 10,
                            }}>
                                <div style={{ flex: 1, fontSize: 13, color: T.text, lineHeight: 1.4 }}>
                                    {c.name}
                                    {c.description && (
                                        <div style={{ fontSize: 11, color: T.textSub, marginTop: 2 }}>{c.description}</div>
                                    )}
                                </div>
                                <AvailabilityToggle
                                    value={val === undefined ? null : val}
                                    onChange={v => onChange(key, v)}
                                />
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

// ── Main screen ────────────────────────────────────────────────────────────────
export function HealthProductsScreen({ assessment, onBack, onComplete }) {
    const [departments, setDepartments] = useState([]);
    const [activeDept, setActiveDept] = useState(null);
    const [responses, setResponses] = useState({});  // key: `${deptId}_${commodityId}` → bool
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        api.healthProducts.get(assessment.id)
            .then(res => {
                const depts = Array.isArray(res?.data) ? res.data : [];
                setDepartments(depts);
                if (depts.length > 0) setActiveDept(depts[0].department_id);

                // Hydrate saved responses
                const init = {};
                depts.forEach(dept => {
                    dept.categories.forEach(cat => {
                        cat.commodities.forEach(c => {
                            if (c.available !== null && c.available !== undefined) {
                                init[`${dept.department_id}_${c.commodity_id}`] = c.available;
                            }
                        });
                    });
                });
                setResponses(init);
            })
            .catch(e => setError(e.message || "Failed to load"))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    const handleChange = (key, val) => {
        setResponses(prev => ({ ...prev, [key]: val }));
    };

    const handleSave = async () => {
        setSaving(true);
        setError(null);
        try {
            // Build flat responses array from all keys
            const responseArray = Object.entries(responses).map(([key, available]) => {
                const [department_id, commodity_id] = key.split("_").map(Number);
                return { department_id, commodity_id, available };
            });
            await api.healthProducts.save(assessment.id, responseArray);
            onComplete?.(assessment.id);
        } catch (e) {
            setError(e.message || "Save failed");
        } finally {
            setSaving(false);
        }
    };

    const activeDeptData = departments.find(d => d.department_id === activeDept);

    // Progress for active dept
    const deptProgress = activeDeptData
        ? (() => {
            const all = activeDeptData.categories.flatMap(c => c.commodities);
            const answered = all.filter(c => responses[`${activeDept}_${c.commodity_id}`] !== undefined).length;
            return { total: all.length, answered };
        })()
        : { total: 0, answered: 0 };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
            {/* Header */}
            <div style={{
                background: "linear-gradient(135deg, #7F1D1D 0%, #EF4444 100%)",
                padding: "20px 20px 0",
            }}>
                <BackButton onBack={onBack} light />
                <div style={{ marginTop: 12, color: "white", fontSize: 18, fontWeight: 800 }}>
                    Health Products
                </div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 2, marginBottom: 14 }}>
                    {assessment.facility_name} · Commodity availability
                </div>

                {/* Department tabs */}
                {!loading && departments.length > 0 && (
                    <div style={{ display: "flex", gap: 0, overflowX: "auto", paddingBottom: 0 }}>
                        {departments.map(dept => (
                            <button key={dept.department_id} onClick={() => setActiveDept(dept.department_id)} style={{
                                padding: "10px 14px", border: "none", background: "none",
                                color: activeDept === dept.department_id ? "white" : "rgba(255,255,255,0.55)",
                                fontWeight: activeDept === dept.department_id ? 700 : 500,
                                fontSize: 12, cursor: "pointer", whiteSpace: "nowrap",
                                borderBottom: activeDept === dept.department_id ? "2px solid white" : "2px solid transparent",
                            }}>
                                {dept.department_name}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Progress bar for active dept */}
            {!loading && deptProgress.total > 0 && (
                <div style={{ background: "#7F1D1D", padding: "6px 16px 10px" }}>
                    <div style={{ height: 4, background: "rgba(255,255,255,0.2)", borderRadius: 999, overflow: "hidden" }}>
                        <div style={{
                            height: "100%", borderRadius: 999, background: "white",
                            width: `${Math.round((deptProgress.answered / deptProgress.total) * 100)}%`,
                            transition: "width 0.3s",
                        }} />
                    </div>
                    <div style={{ fontSize: 10, color: "rgba(255,255,255,0.6)", marginTop: 4, textAlign: "right" }}>
                        {deptProgress.answered}/{deptProgress.total} answered
                    </div>
                </div>
            )}

            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: "14px 16px 100px", background: T.bg }}>
                {loading && (
                    <div style={{ textAlign: "center", padding: "40px 0", color: T.textMuted }}>
                        <div style={{ fontSize: 30, marginBottom: 8 }}>⏳</div>Loading commodities…
                    </div>
                )}
                {error && (
                    <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusSm, padding: "10px 14px", fontSize: 13, marginBottom: 12 }}>
                        {error}
                    </div>
                )}
                {!loading && activeDeptData && activeDeptData.categories.map(cat => (
                    <CategorySection
                        key={cat.category_id}
                        category={cat}
                        deptId={activeDept}
                        responses={responses}
                        onChange={handleChange}
                    />
                ))}
                {!loading && activeDeptData && activeDeptData.categories.length === 0 && (
                    <div style={{ textAlign: "center", padding: "40px 24px", color: T.textMuted }}>
                        <div style={{ fontSize: 40, marginBottom: 12 }}>📦</div>
                        No commodities for this department.
                    </div>
                )}
            </div>

            {/* Save */}
            {!loading && departments.length > 0 && (
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.border}` }}>
                    <button onClick={handleSave} disabled={saving} style={{
                        width: "100%", padding: 15, borderRadius: T.radius, border: "none",
                        background: saving ? "#D1D5DB" : "linear-gradient(135deg, #7F1D1D, #EF4444)",
                        color: "white", fontSize: 15, fontWeight: 700,
                        cursor: saving ? "not-allowed" : "pointer",
                    }}>
                        {saving ? "Saving…" : "Save Health Products →"}
                    </button>
                </div>
            )}
        </div>
    );
}
