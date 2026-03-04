/**
 * MNCH Assessment API Service
 * src/services/api.service.js
 *
 * Single source of truth for all HTTP calls.
 * Token is stored in localStorage under 'mnch_token'.
 */

const BASE_URL = 'https://mnchkenyamentorship.org/api/v1';

const TokenStore = {
    get: () => { try { return localStorage.getItem('mnch_token'); } catch { return null; } },
    set: (t) => { try { localStorage.setItem('mnch_token', t); } catch { } },
    clear: () => { try { localStorage.removeItem('mnch_token'); } catch { } },
};

async function request(method, path, body = null) {
    const token = TokenStore.get();
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(token ? { Authorization: 'Bearer ' + token } : {}),
    };
    const res = await fetch(BASE_URL + path, {
        method,
        headers,
        ...(body !== null ? { body: JSON.stringify(body) } : {}),
    });

    // 204 No Content
    if (res.status === 204) return null;

    let data;
    try { data = await res.json(); } catch { data = {}; }

    if (!res.ok) {
        const error = new Error(data.message || `HTTP ${res.status}`);
        error.status = res.status;
        error.errors = data.errors ?? {};
        throw error;
    }
    return data;
}

const get = (path, params) => {
    const qs = params && Object.keys(params).length > 0
        ? '?' + new URLSearchParams(params).toString()
        : '';
    return request('GET', path + qs);
};
const post = (path, body) => request('POST', path, body);
const put = (path, body) => request('PUT', path, body);
const del = (path) => request('DELETE', path);

const api = {
    setToken: (t) => TokenStore.set(t),
    clearToken: () => TokenStore.clear(),
    getToken: () => TokenStore.get(),

    // ── Auth ──────────────────────────────────────────────────────────────────
    auth: {
        login: (email, password, deviceName) =>
            post('/auth/login', { email, password, device_name: deviceName || 'mobile-app' }),
        logout: () => post('/auth/logout').finally(() => TokenStore.clear()),
        logoutAll: () => post('/auth/logout-all').finally(() => TokenStore.clear()),
        me: () => get('/auth/me'),
        refresh: () => post('/auth/refresh').then(d => { if (d?.token) TokenStore.set(d.token); return d; }),
        forgotPassword: (email) => post('/auth/forgot-password', { email }),
        resetPassword: (token, email, password, password_confirmation) =>
            post('/auth/reset-password', { token, email, password, password_confirmation }),
    },

    // ── Profile ───────────────────────────────────────────────────────────────
    profile: {
        get: () => get('/profile'),
        update: (data) => put('/profile', data),
        changePassword: (data) => put('/profile/password', data),
        stats: () => get('/profile/stats'),
    },

    // ── Facilities ────────────────────────────────────────────────────────────
    facilities: {
        list: (params) => get('/facilities', params),
        byCounty: (countyId) => get('/facilities/county/' + countyId),
        find: (id) => get('/facilities/' + id),
    },

    // ── Sections / Schema ─────────────────────────────────────────────────────
    sections: {
        list: () => get('/sections'),
        find: (id) => get('/sections/' + id),

        fullSchema: () => get('/sections/schema/full'), // no caching — always fetches fresh

        clearSchemaCache: () => { }, // no-op — kept so call sites don't break
    },

    // ── Assessments ───────────────────────────────────────────────────────────
    assessments: {
        list: (params) => get('/assessments', params),
        find: (id) => get('/assessments/' + id),

        // create() intentionally omitted — assessments are pre-loaded by admin on mobile.

        update: (id, data) => put('/assessments/' + id, data),
        delete: (id) => del('/assessments/' + id),

        submit: (id) => post('/assessments/' + id + '/submit'),

        updateSectionProgress: (assessmentId, sectionCode, done) =>
            put('/assessments/' + assessmentId + '/sections/' + sectionCode + '/progress', { done }),
    },

    // ── Human Resources ──────────────────────────────────────────────────────────
    humanResources: {
        /**
         * GET /api/v1/assessments/{id}/human-resources
         * Returns cadre schema + saved responses merged: [{ cadre_id, cadre_name, etat_plus, ... }]
         */
        get: (assessmentId) => get('/assessments/' + assessmentId + '/human-resources'),

        /**
         * POST /api/v1/assessments/{id}/human-resources
         * Bulk-saves all cadre rows.
         * Body: { responses: [{ cadre_id, etat_plus, comprehensive_newborn_care, imnci, type_1_diabetes, essential_newborn_care }] }
         */
        save: (assessmentId, responses) => post('/assessments/' + assessmentId + '/human-resources', { responses }),
    },

    // ── Health Products ───────────────────────────────────────────────────────────
    healthProducts: {
        /**
         * GET /api/v1/assessments/{id}/health-products
         * Returns department → category → commodity schema with saved availability merged.
         */
        get: (assessmentId) => get('/assessments/' + assessmentId + '/health-products'),

        /**
         * POST /api/v1/assessments/{id}/health-products
         * Body: { responses: [{ department_id, commodity_id, available: true|false }] }
         */
        save: (assessmentId, responses) => post('/assessments/' + assessmentId + '/health-products', { responses }),
    },

    // ── Responses ─────────────────────────────────────────────────────────────
    responses: {
        /**
         * GET /api/v1/assessments/{id}/responses
         * Returns { responses: { question_code: value, ... } }
         */
        list: (assessmentId) => get('/assessments/' + assessmentId + '/responses'),

        /**
         * POST /api/v1/assessments/{id}/responses
         * Bulk-saves all responses for a single section.
         */
        bulkSave: (assessmentId, sectionCode, responses, explanations) =>
            post('/assessments/' + assessmentId + '/responses', {
                section_code: sectionCode,
                responses: responses ?? {},
                explanations: explanations ?? {},
            }),
    },

    // ── Reports ───────────────────────────────────────────────────────────────
    reports: {
        full: (assessmentId) => get('/assessments/' + assessmentId + '/report'),
        summary: (assessmentId) => get('/assessments/' + assessmentId + '/report/summary'),
        downloadPdf: (assessmentId) => get('/assessments/' + assessmentId + '/report/pdf'),
        dashboard: () => get('/reports/dashboard'),
        sectionAverages: () => get('/reports/section-averages'),
    },
};

export default api;
