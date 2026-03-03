/**
 * MNCH Assessment API Service
 * src/services/api.js
 */
const BASE_URL =  'https://mnchkenyamentorship.org/api/v1';

const TokenStore = {
  get:   () => localStorage.getItem('mnch_token'),
  set:   (t) => localStorage.setItem('mnch_token', t),
  clear: () => localStorage.removeItem('mnch_token'),
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
    ...(body ? { body: JSON.stringify(body) } : {}),
  });
  if (res.status === 204) return null;
  const data = await res.json();
  if (!res.ok) {
    const error = new Error(data.message || 'API Error');
    error.status = res.status;
    error.errors = data.errors || {};
    throw error;
  }
  return data;
}

const get  = (path, params) => {
  const qs = params ? '?' + new URLSearchParams(params).toString() : '';
  return request('GET', path + qs);
};
const post = (path, body) => request('POST', path, body);
const put  = (path, body) => request('PUT', path, body);
const del  = (path) => request('DELETE', path);

const api = {
  setToken:   (t) => TokenStore.set(t),
  clearToken: ()  => TokenStore.clear(),
  getToken:   ()  => TokenStore.get(),

  auth: {
    login: (email, password, deviceName) =>
      post('/auth/login', { email, password, device_name: deviceName || 'mobile-app' }),
    logout: () => post('/auth/logout').finally(() => TokenStore.clear()),
    logoutAll: () => post('/auth/logout-all').finally(() => TokenStore.clear()),
    me: () => get('/auth/me'),
    refresh: () => post('/auth/refresh').then(d => { if (d && d.token) TokenStore.set(d.token); return d; }),
    forgotPassword: (email) => post('/auth/forgot-password', { email }),
    resetPassword: (token, email, password, password_confirmation) =>
      post('/auth/reset-password', { token, email, password, password_confirmation }),
  },

  profile: {
    get:            ()     => get('/profile'),
    update:         (data) => put('/profile', data),
    changePassword: (data) => put('/profile/password', data),
    stats:          ()     => get('/profile/stats'),
  },

  facilities: {
    list:     (params)   => get('/facilities', params),
    byCounty: (countyId) => get('/facilities/county/' + countyId),
    find:     (id)       => get('/facilities/' + id),
  },

  sections: {
    list: () => get('/sections'),
    find: (id) => get('/sections/' + id),
    fullSchema: async () => {
      try {
        const cached = sessionStorage.getItem('mnch_schema');
        if (cached) return JSON.parse(cached);
      } catch(e) {}
      const data = await get('/sections/schema/full');
      try { sessionStorage.setItem('mnch_schema', JSON.stringify(data)); } catch(e) {}
      return data;
    },
    clearSchemaCache: () => { try { sessionStorage.removeItem('mnch_schema'); } catch(e) {} },
  },

  assessments: {
    list:   (params) => get('/assessments', params),
    find:   (id)     => get('/assessments/' + id),
    create: (facilityId, assessmentType, assessmentDate) =>
      post('/assessments', { facility_id: facilityId, assessment_type: assessmentType, assessment_date: assessmentDate }),
    update: (id, data) => put('/assessments/' + id, data),
    delete: (id)       => del('/assessments/' + id),
    submit: (id)       => post('/assessments/' + id + '/submit'),
    updateSectionProgress: (assessmentId, sectionCode, done) =>
      put('/assessments/' + assessmentId + '/sections/' + sectionCode + '/progress', { done }),
  },

  responses: {
    list:     (assessmentId)                             => get('/assessments/' + assessmentId + '/responses'),
    bulkSave: (assessmentId, sectionCode, responses, explanations) =>
      post('/assessments/' + assessmentId + '/responses', {
        section_code: sectionCode,
        responses: responses,
        explanations: explanations || {},
      }),
  },

  reports: {
    full:            (assessmentId) => get('/assessments/' + assessmentId + '/report'),
    summary:         (assessmentId) => get('/assessments/' + assessmentId + '/report/summary'),
    downloadPdf:     (assessmentId) => get('/assessments/' + assessmentId + '/report/pdf'),
    dashboard:       ()             => get('/reports/dashboard'),
    sectionAverages: ()             => get('/reports/section-averages'),
  },
};

export default api;
