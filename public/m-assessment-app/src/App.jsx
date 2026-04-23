import { useState, useEffect } from "react";
import { LoginScreen } from "./screens/screen-login.jsx";
import { ScopeShell } from "./components/ScopeShell.jsx";
import api from "./services/api.service.js";

// ── normaliseUser ─────────────────────────────────────────────────────────────
function normaliseUser(u) {
    if (!u) return u;
    const fac = typeof u.facility === "object" && u.facility !== null ? u.facility : null;
    return {
        ...u,
        facility:    fac?.name ?? (typeof u.facility === "string" ? u.facility : ""),
        facility_id: u.facility_id ?? fac?.id ?? null,
        county:      fac?.county ?? u.county ?? "",
        county_id:   u.county_id ?? fac?.county_id ?? null,
        subcounty:   fac?.subcounty ?? u.subcounty ?? "",
        mfl_code:    fac?.mfl_code ?? u.mfl_code ?? "",
        initials:    u.initials || ((u.name || "??").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()),
    };
}

export default function App() {
    const [user, setUser]       = useState(null);
    const [loading, setLoading] = useState(true);

    // ── Session restore ──────────────────────────────────────────────────────
    useEffect(() => {
        const token = api.getToken();
        if (!token) { setLoading(false); return; }

        api.auth.me()
            .then(data => {
                const u = data?.user ?? data;
                if (u?.id) setUser(normaliseUser(u));
            })
            .catch(() => api.clearToken())
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", background: "#0f172a" }}>
                <div style={{ color: "white", fontSize: 14, opacity: 0.6 }}>Loading…</div>
            </div>
        );
    }

    if (!user) {
        return <LoginScreen onLogin={(u) => setUser(normaliseUser(u))} />;
    }

    return (
        <ScopeShell
            user={user}
            onLogout={() => { api.clearToken(); setUser(null); }}
            onUserUpdate={(u) => setUser(normaliseUser(u))}
        />
    );
}
