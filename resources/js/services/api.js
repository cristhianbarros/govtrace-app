// El API de la organización, en su propio subdominio (routes/tenant.php).
// Siempre JSON: un 422 trae el mensaje de la regla que no se cumplió, con
// las mismas palabras que el dominio (ver services/errors.js).
import axios from 'axios';

const http = axios.create({
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

const dataOf = async (request) => (await request).data;

// El veedor ------------------------------------------------------------------

/** US-016: obras del territorio que el veedor puede reportar. */
export const searchContracts = async (keyword) => (await dataOf(http.get('/contracts/search', { params: { q: keyword } }))).data;

/** US-008: el reporte con sus archivos y sus SHA-256, como multipart. */
export const sendReport = (form) => dataOf(http.post('/reports', form));

// El Administrador -----------------------------------------------------------

/** US-036 / US-037: "hidden" (por revisar) o "published". */
export const fetchInbox = async (status) => (await dataOf(http.get('/inbox', { params: { status } }))).data;

/** decision: "publish", "reject" o "withdraw"; los dos últimos, con motivo. */
export const decideOnEvidence = (reportId, decision, reason) => dataOf(http.post(`/reports/${reportId}/${decision}`, { reason }));

/** US-005 */
export const fetchObservers = async () => (await dataOf(http.get('/observers'))).data;
export const inviteObserver = (email) => dataOf(http.post('/observers/invite', { email }));

/** US-006 / US-041-USR */
export const deactivateObserver = (id) => dataOf(http.post(`/observers/${id}/deactivate`));
export const reactivateObserver = (id) => dataOf(http.post(`/observers/${id}/reactivate`));

/** US-012 */
export const fetchTerritory = async () => (await dataOf(http.get('/territory'))).data;
export const searchTerritories = async (keyword) => (await dataOf(http.get('/territory/search', { params: { q: keyword } }))).data;
export const saveTerritory = (codes) => dataOf(http.put('/territory', { codes }));

/** US-015: { data, meta: { current_page, last_page, total } } */
export const fetchContracts = ({ sort, direction, page }) => dataOf(http.get('/contracts', { params: { sort, direction, page } }));

/** US-007: nombre de fantasía y logo (multipart). */
export const fetchProfile = async () => (await dataOf(http.get('/organization/profile'))).data;
export const saveProfile = (form) => dataOf(http.post('/organization/profile', form));

/** US-043-MON: el log de la organización, { data, meta }. */
export const fetchAuditLog = (page) => dataOf(http.get('/audit', { params: { page } }));

/** US-035 */
export const fetchWorksites = async () => (await dataOf(http.get('/worksites'))).data;
export const correctWorksiteLocation = (worksiteId, { latitude, longitude }) =>
    dataOf(http.patch(`/worksites/${worksiteId}/location`, { latitude, longitude }));

// El Super Administrador (panel global) ---------------------------------------

/** US-001 */
export const fetchOrganizations = async () => (await dataOf(http.get('/admin/organizations/data'))).data;
export const registerOrganization = (data) => dataOf(http.post('/admin/organizations', data));

/** US-011 */
export const fetchOrganizationDetail = async (id) => (await dataOf(http.get(`/admin/organizations/${id}`))).data;
export const updateOrganizationNit = (id, nit) => dataOf(http.put(`/admin/organizations/${id}/nit`, { nit }));

/** US-038-CFG: { configurable, fixed } */
export const fetchParameters = () => dataOf(http.get('/admin/parameters/data'));
export const updateParameter = (key, value) => dataOf(http.put(`/admin/parameters/${key}`, { value }));

/** US-043-MON: todo el log, { data, meta }. */
export const fetchGlobalAuditLog = (page) => dataOf(http.get('/admin/audit/data', { params: { page } }));

/** US-003a */
export const suspendOrganization = (id) => dataOf(http.post(`/admin/organizations/${id}/suspend`));
export const reactivateOrganization = (id) => dataOf(http.post(`/admin/organizations/${id}/reactivate`));
