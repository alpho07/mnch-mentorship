/**
 * MNCH Assessment API Service — Offline-Aware
 * src/services/api.service.js
 *
 * Wraps every API call with offline fallback:
 *   - GET requests: try network first, cache result in IndexedDB; on failure, return cached
 *   - POST/PUT writes: try network first; on failure, queue in sync queue + save locally
 *
 * The raw (unwrapped) API is also exported as `_rawApi` so the sync queue
 * can replay operations without going through the offline layer again.
 */

import offlineStore from "./offline-store.js";
import syncQueue from "./sync-queue.js";

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'https://mnchkenyamentorship.org/api/v1';

const TokenStore = {
    get: () => {
        try {
            return localStorage.getItem('mnch_token');
        } catch {
            return null;
        }
    },
    set: (t) => {
        try {
            localStorage.setItem('mnch_token', t);
        } catch { /* localStorage unavailable */ }
    },
    clear: () => {
        try {
            localStorage.removeItem('mnch_token');
        } catch { /* localStorage unavailable */ }
    },
};

// ── Raw fetch (no offline logic) ─────────────────────────────────────────────
async function request(method, path, body = null) {
    const token = TokenStore.get();
    const res = await fetch(BASE_URL + path, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(token ? {Authorization: 'Bearer ' + token} : {}),
        },
        ...(body !== null ? {body: JSON.stringify(body)} : {}),
    });

    if (res.status === 204)
        return null;

    let data;
    try {
        data = await res.json();
    } catch {
        data = {};
    }

    if (!res.ok) {
        const err = new Error(data.message || `HTTP ${res.status}`);
        err.status = res.status;
        err.data = data;                // ← add this line
        err.errors = data.errors ?? {};
        throw err;
    }
    return data;
}

const get = (path, params) => {
    const qs = params && Object.keys(params).length > 0
            ? '?' + new URLSearchParams(params).toString() : '';
    return request('GET', path + qs);
};
const post = (path, body) => request('POST', path, body);
const put = (path, body) => request('PUT', path, body);
const del = (path) => request('DELETE', path);

// ── Helper: is a fetch error a network error? ───────────────────────────────
function isNetworkError(e) {
    return (
            e instanceof TypeError || // fetch throws TypeError on network fail
            e.message?.includes('Failed to fetch') ||
            e.message?.includes('Network request failed') ||
            e.message?.includes('NetworkError') ||
            !navigator.onLine
            );
}

// ── Raw API (exported for sync-queue to use directly) ────────────────────────
export const _rawApi = {
    auth: {
        login: (email, password, deviceName) =>
            post('/auth/login', {email, password, device_name: deviceName || 'mobile-app'}),
        logout: () => post('/auth/logout').finally(() => TokenStore.clear()),
        logoutAll: () => post('/auth/logout-all').finally(() => TokenStore.clear()),
        me: () => get('/auth/me'),
        refresh: () => post('/auth/refresh').then(d => {
                if (d?.token)
                    TokenStore.set(d.token);
                return d;
            }),
        forgotPassword: (email) => post('/auth/forgot-password', {email}),
        resetPassword: (token, email, password, password_confirmation) =>
            post('/auth/reset-password', {token, email, password, password_confirmation}),
    },
    profile: {
        get: () => get('/profile'),
        update: (data) => put('/profile', data),
        changePassword: (data) => put('/profile/password', data),
        stats: () => get('/profile/stats'),
    },
    facilities: {
        list: (params) => get('/facilities', params),
        byCounty: (countyId) => get('/facilities/county/' + countyId),
        find: (id) => get('/facilities/' + id),
    },
    sections: {
        list: () => get('/sections'),
        find: (id) => get('/sections/' + id),
        fullSchema: () => get('/sections/schema/full'),
        clearSchemaCache: () => {
        },
    },
    assessments: {
        list: (params) => get('/assessments', params),
        find: (id) => get('/assessments/' + id),
        update: (id, data) => put('/assessments/' + id, data),
        delete: (id) => del('/assessments/' + id),
        submit: (id) => post('/assessments/' + id + '/submit'),
        updateSectionProgress: (assessmentId, sectionCode, done) =>
            put('/assessments/' + assessmentId + '/sections/' + sectionCode + '/progress', {done}),
        create: (facility_id, assessment_type, assessment_date) =>
            post('/assessments', {facility_id, assessment_type, assessment_date}),
    },
    humanResources: {
        get: (assessmentId) => get('/assessments/' + assessmentId + '/human-resources'),
        save: (assessmentId, responses) =>
            post('/assessments/' + assessmentId + '/human-resources', {responses}),
    },
    healthProducts: {
        get: (assessmentId) => get('/assessments/' + assessmentId + '/health-products'),
        save: (assessmentId, responses, departmentId = null) =>
            post('/assessments/' + assessmentId + '/health-products', {
                responses,
                ...(departmentId !== null ? {department_id: departmentId} : {}),
            }),
    },
    responses: {
        list: (assessmentId) => get('/assessments/' + assessmentId + '/responses'),
        bulkSave: (assessmentId, sectionCode, responses, explanations) =>
            post('/assessments/' + assessmentId + '/responses', {
                section_code: sectionCode,
                responses: responses ?? {},
                explanations: explanations ?? {},
            }),
    },
    reports: {
        show: (assessmentId) => get('/assessments/' + assessmentId + '/report'),
        full: (assessmentId) => get('/assessments/' + assessmentId + '/report'),
        summary: (assessmentId) => get('/assessments/' + assessmentId + '/report/summary'),
        downloadPdf: (assessmentId) => get('/assessments/' + assessmentId + '/report/pdf'),
        emailReport: (assessmentId, emails) => post('/assessments/' + assessmentId + '/report/email', { emails }),
        emailJobStatus: (assessmentId, jobId) => get('/assessments/' + assessmentId + '/report/email/' + jobId),
        emailJobs: () => get('/reports/email-jobs'),
        dashboard: () => get('/reports/dashboard'),
        sectionAverages: () => get('/reports/section-averages'),
    },
    mentorships: {
        list: () => get('/mentorships'),
        find: (id) => get('/mentorships/' + id),
        classes: (id) => get('/mentorships/' + id + '/classes'),
        classDetail: (id, classId) => get('/mentorships/' + id + '/classes/' + classId),
    },
    modules: {
        list: (classId) => get('/classes/' + classId + '/modules'),
        start: (moduleId) => post('/modules/' + moduleId + '/start'),
        complete: (moduleId) => post('/modules/' + moduleId + '/complete'),
        sessions: (moduleId) => get('/modules/' + moduleId + '/sessions'),
    },
    attendance: {
        roster: (moduleId) => get('/modules/' + moduleId + '/attendance'),
        mark: (moduleId, participantId, status) =>
            post('/modules/' + moduleId + '/attendance/' + participantId, { status }),
        bulk: (moduleId, attendances) =>
            post('/modules/' + moduleId + '/attendance/bulk', { attendances }),
    },
    me: {
        classes: () => get('/me/classes'),
        classDetail: (classId) => get('/me/classes/' + classId),
        attend: (classId, moduleId) =>
            post('/me/classes/' + classId + '/modules/' + moduleId + '/attend'),
    },
    trainings: {
        list: () => get('/trainings'),
        find: (id) => get('/trainings/' + id),
        participants: (id) => get('/trainings/' + id + '/participants'),
    },
    participants: {
        list: (classId) => get('/classes/' + classId + '/participants'),
        progress: (participantId) => get('/participants/' + participantId + '/progress'),
    },
    lookups: {
        programs: () => get('/programs'),
        programModules: (programId) => get('/programs/' + programId + '/modules'),
        counties: () => get('/counties'),
        userSearch: (q, facilityId) => get('/users/search?q=' + encodeURIComponent(q) + (facilityId ? '&facility_id=' + facilityId : '')),
    },
    mentorshipCreate: {
        create: (payload) => post('/mentorships', payload),
        update: (id, payload) => put('/mentorships/' + id, payload),
        submit: (id) => post('/mentorships/' + id + '/submit'),
    },
    classLifecycle: {
        start: (classId) => post('/classes/' + classId + '/start'),
        end: (classId) => post('/classes/' + classId + '/end'),
        enrollMentee: (classId, userId) => post('/classes/' + classId + '/mentees', { user_id: userId }),
        removeMentee: (classId, participantId) => del('/classes/' + classId + '/mentees/' + participantId),
    },
    sessions: {
        update: (sessionId, payload) => put('/sessions/' + sessionId, payload),
    },
    trainingActions: {
        enroll: (trainingId) => post('/trainings/' + trainingId + '/enroll'),
        attendance: (trainingId) => post('/trainings/' + trainingId + '/attendance'),
    },
    resources: {
        list: (type) => get('/resources' + (type ? '?type=' + type : '')),
    },
};

// ─────────────────────────────────────────────────────────────────────────────
// Offline-aware API (default export)
// ─────────────────────────────────────────────────────────────────────────────

const api = {
    setToken: (t) => TokenStore.set(t),
    clearToken: () => TokenStore.clear(),
    getToken: () => TokenStore.get(),

    // ── Auth (no offline cache — login requires network, session restore uses cached user) ──
    auth: {
        login: async (email, password, deviceName) => {
            const data = await _rawApi.auth.login(email, password, deviceName);
            // Cache user for offline session restore
            if (data?.user)
                await offlineStore.saveUser(data.user);
            return data;
        },
        logout: async () => {
            try {
                await _rawApi.auth.logout();
            } catch { /* ignore server-side logout errors */ }
            TokenStore.clear();
            await offlineStore.clearAll();
        },
        logoutAll: _rawApi.auth.logoutAll,
        me: async () => {
            try {
                const data = await _rawApi.auth.me();
                if (data?.user ?? data)
                    await offlineStore.saveUser(data?.user ?? data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getUser();
                    if (cached) {
                        console.log("[API] Offline — using cached user");
                        return {user: cached};
                    }
                }
                throw e;
            }
        },
        refresh: _rawApi.auth.refresh,
        forgotPassword: _rawApi.auth.forgotPassword,
        resetPassword: _rawApi.auth.resetPassword,
    },

    // ── Profile ──────────────────────────────────────────────────────────────
    profile: _rawApi.profile,

    // ── Facilities (cached reads — fetches ALL pages) ─────────────────────────
    facilities: {
        list: async () => {
            try {
                // Fetch first page with a large per_page to minimise round-trips.
                // The API paginates at 50 by default; most deployments have < 10 000
                // active facilities so a single request with per_page=10000 usually
                // returns everything at once.
                const first = await _rawApi.facilities.list({ per_page: 10000, page: 1 });
                const extractArr = (d) =>
                    Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [];

                let all = extractArr(first);
                const lastPage = first?.meta?.last_page ?? 1;

                // If the server still has more pages, fetch them in parallel.
                if (lastPage > 1) {
                    const pages = await Promise.all(
                        Array.from({ length: lastPage - 1 }, (_, i) =>
                            _rawApi.facilities.list({ per_page: 10000, page: i + 2 })
                        )
                    );
                    for (const page of pages) all = all.concat(extractArr(page));
                }

                if (all.length > 0) {
                    await offlineStore.saveFacilities(all);
                    console.log(`[API] Cached ${all.length} facilities for offline use`);
                }
                return all;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getFacilities();
                    if (cached && cached.length > 0) {
                        console.log(`[API] Offline — returning ${cached.length} cached facilities`);
                        return cached;
                    }
                }
                throw e;
            }
        },
        byCounty: _rawApi.facilities.byCounty,
        find: _rawApi.facilities.find,
    },

    // ── Sections / Schema (cached) ───────────────────────────────────────────
    sections: {
        list: _rawApi.sections.list,
        find: _rawApi.sections.find,
        fullSchema: async () => {
            try {
                const data = await _rawApi.sections.fullSchema();
                const arr = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0)
                    await offlineStore.saveSchema(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getSchema();
                    if (cached && cached.length > 0) {
                        console.log("[API] Offline — using cached schema");
                        return cached;
                    }
                }
                throw e;
            }
        },
        clearSchemaCache: () => {
            offlineStore.saveSchema(null);
        },
    },

    // ── Assessments (cached reads, queued writes) ────────────────────────────
    assessments: {
        list: async (params) => {
            try {
                const data = await _rawApi.assessments.list(params);
                const arr = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0)
                    await offlineStore.saveAssessments(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getAssessments();
                    if (cached.length > 0) {
                        console.log(`[API] Offline — returning ${cached.length} cached assessments`);
                        return cached;
                    }
                }
                throw e;
            }
        },
        find: async (id) => {
            try {
                const data = await _rawApi.assessments.find(id);
                const a = data?.data ?? data;
                if (a?.id)
                    await offlineStore.saveAssessment(a);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getAssessment(id);
                    if (cached) {
                        console.log(`[API] Offline — returning cached assessment ${id}`);
                        return cached;
                    }
                }
                throw e;
            }
        },
        update: _rawApi.assessments.update,
        delete: _rawApi.assessments.delete,

        submit: async (id) => {
            try {
                return await _rawApi.assessments.submit(id);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing submit for assessment ${id}`);
                    await syncQueue.enqueue({type: "assessments.submit", assessmentId: id});
                    return {queued: true, message: "Submission queued — will sync when online"};
                }
                throw e;
            }
        },

        updateSectionProgress: async (assessmentId, sectionCode, done) => {
            try {
                return await _rawApi.assessments.updateSectionProgress(assessmentId, sectionCode, done);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing progress update ${sectionCode}`);
                    await syncQueue.enqueue({
                        type: "assessments.progress",
                        assessmentId, sectionCode, done,
                    });
                    return {queued: true};
                }
                throw e;
            }
        },

        create: async (facility_id, assessment_type, assessment_date, facilityMeta, user, sectionCodes) => {
            try {
                const data = await _rawApi.assessments.create(facility_id, assessment_type, assessment_date);
                const a = data?.assessment ?? data?.data ?? data;
                if (a?.id) await offlineStore.saveAssessment(a);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log('[API] Offline — creating provisional assessment');
                    const tempId = 'offline_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
                    // Build section_progress from cached schema codes
                    const sectionProgress = {};
                    (sectionCodes ?? []).forEach(code => { sectionProgress[code] = false; });
                    const provisional = {
                        id: tempId,
                        facility_id,
                        facility_name: facilityMeta?.name ?? '',
                        mfl_code: facilityMeta?.mfl_code ?? '',
                        county: facilityMeta?.county ?? '',
                        subcounty: facilityMeta?.subcounty ?? '',
                        assessment_type,
                        assessment_date,
                        assessor_id: user?.id ?? null,
                        assessor_name: user?.name ?? '',
                        status: 'in_progress',
                        section_progress: sectionProgress,
                        section_scores: {},
                        section_progress_detail: {},
                        responses: {},
                        overall_percentage: null,
                        overall_grade: null,
                        _isOffline: true,
                    };
                    await offlineStore.saveAssessment(provisional);
                    await syncQueue.enqueue({
                        type: 'assessments.create',
                        tempId,
                        facility_id,
                        assessment_type,
                        assessment_date,
                    });
                    return {_provisional: true, assessment: provisional};
                }
                throw e;
            }
        },
    },

    // ── Human Resources (cached reads, queued writes) ────────────────────────
    humanResources: {
        get: async (assessmentId) => {
            try {
                const data = await _rawApi.humanResources.get(assessmentId);
                // Cache the FULL cadre structure (cadre_id, cadre_name, field values)
                if (data?.data)
                    await offlineStore.saveHR(assessmentId, {structure: data.data});
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getHR(assessmentId);
                    if (cached?.structure) {
                        console.log(`[API] Offline — returning cached HR for ${assessmentId}`);
                        // Merge any pending (unsaved) values on top of the structure
                        let structure = cached.structure;
                        if (cached.pendingValues && typeof cached.pendingValues === 'object') {
                            structure = structure.map(cadre => {
                                const pending = cached.pendingValues[cadre.cadre_id];
                                if (!pending) return cadre;
                                return { ...cadre, ...pending };
                            });
                        }
                        return {data: structure};
                    }
                    if (cached?.responses) {
                        return {data: cached.responses};
                    }
                }
                throw e;
            }
        },
        save: async (assessmentId, responses) => {
            const existing = await offlineStore.getHR(assessmentId);

            // Update the cached structure so offline reloads see the new values
            let updatedStructure = existing?.structure;
            if (updatedStructure && Array.isArray(responses)) {
                const respMap = {};
                responses.forEach(r => { respMap[r.cadre_id] = r; });
                updatedStructure = updatedStructure.map(cadre => ({
                    ...cadre,
                    ...(respMap[cadre.cadre_id] ?? {}),
                }));
            }

            await offlineStore.saveHR(assessmentId, {
                ...(existing ?? {}),
                ...(updatedStructure ? {structure: updatedStructure} : {}),
                responses,
                pendingValues: null, // clear pending — structure is now up-to-date
                lastSaved: Date.now(),
            });

            try {
                return await _rawApi.humanResources.save(assessmentId, responses);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing HR save for ${assessmentId}`);
                    await syncQueue.enqueue({
                        type: "humanResources.save",
                        assessmentId, responses,
                    });
                    return {queued: true};
                }
                throw e;
            }
        },
    },

    // ── Health Products (cached reads, queued writes) ────────────────────────
    healthProducts: {
        get: async (assessmentId) => {
            try {
                const data = await _rawApi.healthProducts.get(assessmentId);
                // Cache the FULL department/category/commodity structure
                if (data?.data)
                    await offlineStore.saveHP(assessmentId, {structure: data.data});
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getHP(assessmentId);
                    if (cached?.structure) {
                        console.log(`[API] Offline — returning cached HP for ${assessmentId}`);
                        // Merge pending flat-map responses on top of structure
                        let structure = cached.structure;
                        if (cached.pendingFlat && typeof cached.pendingFlat === 'object') {
                            structure = structure.map(dept => ({
                                ...dept,
                                categories: dept.categories.map(cat => ({
                                    ...cat,
                                    commodities: cat.commodities.map(c => {
                                        const key = `${dept.department_id}_${c.commodity_id}`;
                                        return cached.pendingFlat[key] !== undefined
                                            ? { ...c, available: cached.pendingFlat[key] }
                                            : c;
                                    }),
                                })),
                            }));
                        }
                        return {data: structure};
                    }
                    if (cached?.responses) {
                        return {data: cached.responses};
                    }
                }
                throw e;
            }
        },
        save: async (assessmentId, responses, departmentId = null) => {
            const existing = await offlineStore.getHP(assessmentId);

            // Update the cached structure so offline reloads see the new values
            let updatedStructure = existing?.structure;
            if (updatedStructure && Array.isArray(responses)) {
                const respMap = {};
                responses.forEach(r => {
                    respMap[`${r.department_id}_${r.commodity_id}`] = r.available;
                });
                updatedStructure = updatedStructure.map(dept => ({
                    ...dept,
                    categories: dept.categories.map(cat => ({
                        ...cat,
                        commodities: cat.commodities.map(c => {
                            const key = `${dept.department_id}_${c.commodity_id}`;
                            return respMap[key] !== undefined
                                ? { ...c, available: respMap[key] }
                                : c;
                        }),
                    })),
                }));
            }

            await offlineStore.saveHP(assessmentId, {
                ...(existing ?? {}),
                ...(updatedStructure ? {structure: updatedStructure} : {}),
                responses,
                pendingFlat: null, // clear pending — structure is now up-to-date
                lastSaved: Date.now(),
            });

            try {
                return await _rawApi.healthProducts.save(assessmentId, responses, departmentId);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing HP save for ${assessmentId}`);
                    await syncQueue.enqueue({
                        type: "healthProducts.save",
                        assessmentId, responses, departmentId,
                    });
                    return {queued: true};
                }
                throw e;
            }
        },
    },

    // ── Responses (cached reads, queued writes) ──────────────────────────────
    responses: {
        list: async (assessmentId) => {
            try {
                const data = await _rawApi.responses.list(assessmentId);
                if (data) {
                    // Normalize to { responses, explanations } before caching
                    const normalized = {
                        responses: data.responses ?? (('explanations' in (data ?? {})) ? {} : (data ?? {})),
                        explanations: data.explanations ?? {},
                    };
                    await offlineStore.saveResponses(assessmentId, normalized);
                }
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getResponses(assessmentId);
                    if (cached) {
                        console.log(`[API] Offline — returning cached responses for ${assessmentId}`);
                        return cached;
                    }
                }
                throw e;
            }
        },
        bulkSave: async (assessmentId, sectionCode, responses, explanations) => {
            // Always persist locally — normalize to { responses, explanations } shape
            // regardless of whether existing data is flat { CODE: val } or nested.
            const existing = await offlineStore.getResponses(assessmentId) ?? {};
            const existingResponses = existing.responses
                ?? (('explanations' in existing) ? {} : existing);
            const existingExplanations = existing.explanations ?? {};
            const merged = {
                responses: {...existingResponses, ...responses},
                explanations: {...existingExplanations, ...explanations},
            };
            await offlineStore.saveResponses(assessmentId, merged);

            try {
                return await _rawApi.responses.bulkSave(assessmentId, sectionCode, responses, explanations);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing responses for ${assessmentId}/${sectionCode}`);
                    await syncQueue.enqueue({
                        type: "responses.bulkSave",
                        assessmentId, sectionCode, responses, explanations,
                    });
                    return {queued: true};
                }
                throw e;
            }
        },
    },

    // ── Reports ──────────────────────────────────────────────────────────────
    reports: {
        ..._rawApi.reports,

        // Email report — queues locally when offline, dispatches job when online
        emailReport: async (assessmentId, emails) => {
            // Always save a local pending job immediately so the UI can show it
            const localId = "local_" + Date.now() + "_" + Math.random().toString(36).slice(2, 7);
            const localJob = {
                id: localId,
                assessment_id: assessmentId,
                emails,
                status: "local_pending",
                created_at: new Date().toISOString(),
            };
            await offlineStore.saveEmailJob(localJob);

            try {
                const data = await _rawApi.reports.emailReport(assessmentId, emails);
                // Replace local job with server job
                await offlineStore.deleteEmailJob(localId);
                await offlineStore.saveEmailJob({ ...data, assessment_id: assessmentId, emails });
                return { ...data, _localId: localId };
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing email report for assessment ${assessmentId}`);
                    await syncQueue.enqueue({
                        type: "report.email",
                        assessmentId,
                        emails,
                        localJobId: localId,
                    });
                    return { queued: true, localJobId: localId, status: "local_pending" };
                }
                // On hard error, remove the local placeholder
                await offlineStore.deleteEmailJob(localId);
                throw e;
            }
        },

        // Fetch all email jobs — server first, fall back to local cache
        emailJobs: async () => {
            try {
                const data = await _rawApi.reports.emailJobs();
                const jobs = data?.data ?? [];
                await offlineStore.saveEmailJobs(jobs);
                return jobs;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getAllEmailJobs();
                    return Object.values(cached ?? {}).sort(
                        (a, b) => new Date(b.created_at) - new Date(a.created_at)
                    );
                }
                throw e;
            }
        },

        emailJobStatus: _rawApi.reports.emailJobStatus,
    },

    // ── Mentorships (cached reads) ───────────────────────────────────────────
    mentorships: {
        list: async () => {
            try {
                const data = await _rawApi.mentorships.list();
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0) await offlineStore.saveMentorships(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorships();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        find: async (id) => {
            try {
                const data = await _rawApi.mentorships.find(id);
                if (data?.data) await offlineStore.saveMentorship(data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorship(id);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        classes: async (id) => {
            try {
                const data = await _rawApi.mentorships.classes(id);
                const arr = Array.isArray(data?.data) ? data.data : [];
                await offlineStore.saveMentorshipClasses(id, arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorshipClasses(id);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        classDetail: _rawApi.mentorships.classDetail,
    },

    // ── Modules (write ops queued when offline) ──────────────────────────────
    modules: {
        list: async (classId) => {
            try {
                return await _rawApi.modules.list(classId);
            } catch (e) {
                if (isNetworkError(e)) return { data: [] };
                throw e;
            }
        },
        start: async (moduleId) => {
            try {
                return await _rawApi.modules.start(moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorship.module.start', moduleId });
                    return { queued: true };
                }
                throw e;
            }
        },
        complete: async (moduleId) => {
            try {
                return await _rawApi.modules.complete(moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorship.module.complete', moduleId });
                    return { queued: true };
                }
                throw e;
            }
        },
        sessions: _rawApi.modules.sessions,
    },

    // ── Attendance ────────────────────────────────────────────────────────────
    attendance: {
        roster: async (moduleId) => {
            try {
                const data = await _rawApi.attendance.roster(moduleId);
                if (data?.data) await offlineStore.saveAttendance(moduleId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getAttendance(moduleId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        mark: async (moduleId, participantId, status) => {
            try {
                return await _rawApi.attendance.mark(moduleId, participantId, status);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorship.attendance.mark', moduleId, participantId, status });
                    return { queued: true };
                }
                throw e;
            }
        },
        bulk: async (moduleId, attendances) => {
            try {
                return await _rawApi.attendance.bulk(moduleId, attendances);
            } catch (e) {
                if (isNetworkError(e)) {
                    for (const a of attendances) {
                        await syncQueue.enqueue({ type: 'mentorship.attendance.mark', moduleId, participantId: a.participant_id, status: a.status });
                    }
                    return { queued: true };
                }
                throw e;
            }
        },
    },

    // ── Me / Mentee classes ───────────────────────────────────────────────────
    me: {
        classes: async () => {
            try {
                const data = await _rawApi.me.classes();
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0) await offlineStore.saveMyClasses(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMyClasses();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        classDetail: async (classId) => {
            try {
                const data = await _rawApi.me.classDetail(classId);
                if (data?.data) await offlineStore.saveMyClass(classId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMyClass(classId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        attend: async (classId, moduleId) => {
            try {
                return await _rawApi.me.attend(classId, moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentee.attendance.confirm', classId, moduleId });
                    return { queued: true };
                }
                throw e;
            }
        },
    },

    // ── Global trainings ─────────────────────────────────────────────────────
    trainings: {
        list: async () => {
            try {
                const data = await _rawApi.trainings.list();
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0) await offlineStore.saveTrainings(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getTrainings();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        find: async (id) => {
            try {
                const data = await _rawApi.trainings.find(id);
                if (data?.data) await offlineStore.saveTraining(data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getTraining(id);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        participants: _rawApi.trainings.participants,
    },

    // ── Lookup (cache-first, no writes) ─────────────────────────────────────
    lookups: {
        programs: async () => {
            try {
                const data = await _rawApi.lookups.programs();
                const list = data?.data ?? data;
                await offlineStore.setMeta('programs', list);
                return list;
            } catch {
                return (await offlineStore.getMeta('programs')) ?? [];
            }
        },
        programModules: async (programId) => {
            try {
                const data = await _rawApi.lookups.programModules(programId);
                const list = data?.data ?? data;
                await offlineStore.setMeta('program_modules_' + programId, list);
                return list;
            } catch {
                return (await offlineStore.getMeta('program_modules_' + programId)) ?? [];
            }
        },
        counties: async () => {
            try {
                const data = await _rawApi.lookups.counties();
                const list = data?.data ?? data;
                await offlineStore.setMeta('counties', list);
                return list;
            } catch {
                return (await offlineStore.getMeta('counties')) ?? [];
            }
        },
        userSearch: _rawApi.lookups.userSearch,
    },

    // ── Mentorship creation (online→queue) ───────────────────────────────────
    mentorshipCreate: {
        create: async (payload) => {
            try {
                const data = await _rawApi.mentorshipCreate.create(payload);
                return data;
            } catch (e) {
                if (!navigator.onLine) {
                    const tempId = 'local_' + Date.now();
                    const local = { ...payload, id: tempId, _isOffline: true, status: 'draft' };
                    await offlineStore.saveMentorship(local);
                    await syncQueue.enqueue({ type: 'mentorships.create', tempId, payload });
                    return { data: local };
                }
                throw e;
            }
        },
        update: async (id, payload) => {
            try {
                return await _rawApi.mentorshipCreate.update(id, payload);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.update', id, payload });
                    return { data: { id, ...payload } };
                }
                throw e;
            }
        },
        submit: async (id) => {
            try {
                return await _rawApi.mentorshipCreate.submit(id);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.submit', id });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Class lifecycle (online→queue) ───────────────────────────────────────
    classLifecycle: {
        start: async (classId) => {
            try {
                return await _rawApi.classLifecycle.start(classId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.startClass', classId });
                    return { data: { id: classId, status: 'active' } };
                }
                throw e;
            }
        },
        end: (classId) => _rawApi.classLifecycle.end(classId),
        enrollMentee: async (classId, userId) => {
            try {
                return await _rawApi.classLifecycle.enrollMentee(classId, userId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.addMentee', classId, userId });
                    return { data: { participant_id: 'local_' + Date.now(), user_id: userId } };
                }
                throw e;
            }
        },
        removeMentee: async (classId, participantId) => {
            try {
                return await _rawApi.classLifecycle.removeMentee(classId, participantId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.removeMentee', classId, participantId });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Session notes (online→queue, never discard) ──────────────────────────
    sessions: {
        update: async (sessionId, payload) => {
            try {
                const data = await _rawApi.sessions.update(sessionId, payload);
                await offlineStore.saveSession({ id: sessionId, ...payload });
                return data;
            } catch (e) {
                if (!navigator.onLine) {
                    await offlineStore.saveSession({ id: sessionId, ...payload, _pending: true });
                    await syncQueue.enqueue({ type: 'mentorships.updateSession', sessionId, payload });
                    return { data: { id: sessionId, ...payload } };
                }
                throw e;
            }
        },
    },

    // ── Training actions (online→queue) ──────────────────────────────────────
    trainingActions: {
        enroll: async (trainingId) => {
            try {
                return await _rawApi.trainingActions.enroll(trainingId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'trainings.enroll', trainingId });
                    return { data: { participant_id: 'pending', status: 'registered' } };
                }
                throw e;
            }
        },
        attendance: async (trainingId) => {
            try {
                return await _rawApi.trainingActions.attendance(trainingId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'trainings.attendance', trainingId });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Resources (cache with 24h TTL) ───────────────────────────────────────
    resources: {
        list: async (type) => {
            const now = Date.now();
            const cachedAt = (await offlineStore.getResourcesCachedAt()) ?? 0;
            const ttl = 24 * 60 * 60 * 1000;
            if (!type && now - cachedAt < ttl) {
                const cached = await offlineStore.getResources();
                if (cached) return cached;
            }
            try {
                const data = await _rawApi.resources.list(type);
                const list = data?.data ?? data;
                if (!type) {
                    await offlineStore.saveResources(list);
                    await offlineStore.setResourcesCachedAt(now);
                }
                return list;
            } catch {
                return (await offlineStore.getResources()) ?? [];
            }
        },
    },

    // ── Participants ──────────────────────────────────────────────────────────
    participants: {
        list: async (classId) => {
            try {
                const data = await _rawApi.participants.list(classId);
                if (data?.data) await offlineStore.saveParticipants(classId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getParticipants(classId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        progress: _rawApi.participants.progress,
    },

    // ── Prefetch for offline ─────────────────────────────────────────────────
    // Call after initial data load while online. Pre-caches HR structure,
    // HP structure, and responses for all in-progress assessments so they
    // are available if the user goes into the field without connectivity.
    prefetchForOffline: async (assessments) => {
        if (!navigator.onLine)
            return;
        // Facilities are fetched unconditionally — needed before any assessment opens
        try {
            await api.facilities.list();
        } catch {
            // Non-critical
        }
        const inProgress = (assessments ?? []).filter(a => a.status === "in_progress");
        if (inProgress.length === 0)
            return;

        console.log(`[API] Prefetching offline data for ${inProgress.length} in-progress assessments…`);

        for (const a of inProgress) {
            try {
                // These calls go through the offline-aware wrapper, so they
                // automatically cache the results in IndexedDB
                await Promise.allSettled([
                    api.responses.list(a.id),
                    api.humanResources.get(a.id),
                    api.healthProducts.get(a.id),
                ]);
            } catch {
                // Non-critical — best effort
            }
        }

        await offlineStore.setMeta("lastPrefetch", Date.now());
        console.log("[API] Prefetch complete");
    },
};

export default api;