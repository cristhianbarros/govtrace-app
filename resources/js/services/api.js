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

/** US-019: hasta 5 obras a menos de la geocerca, de la más cercana a la más lejana. */
export const fetchNearbyWorksites = async (latitude, longitude) => (await dataOf(http.get('/worksites/nearby', { params: { latitude, longitude } }))).data;

/** US-008: el reporte con sus archivos y sus SHA-256, como multipart. */
export const sendReport = (form) => dataOf(http.post('/reports', form));

/** Cerrar sesión (US-018 avisa antes si hay reportes sin enviar). */
export const logout = () => dataOf(http.post('/logout'));

/** US-010: sus reportes, con el estado técnico y el editorial por separado. */
export const fetchMyReports = async () => (await dataOf(http.get('/me/reports'))).data;

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

/** US-045-INT: agrupar contratos del territorio en una ficha de obra. */
export const groupContracts = (name, secopContractIds) => dataOf(http.post('/worksites/group', { name, secop_contract_ids: secopContractIds }));

/** US-049-RPT: el resumen del territorio. */
export const fetchSummary = async () => (await dataOf(http.get('/summary'))).data;

/** US-042-SEC: la autorización al Super Administrador (30 días). */
export const fetchSuperAdminAuthorization = async () => (await dataOf(http.get('/authorizations/super-admin'))).data;
export const authorizeSuperAdmin = () => dataOf(http.post('/authorizations/super-admin'));
export const revokeSuperAdmin = () => dataOf(http.delete('/authorizations/super-admin'));

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

/** US-014: la última sincronización con SECOP II, o null. */
export const fetchSecopHealth = async () => (await dataOf(http.get('/admin/secop-health/data'))).data;

/** US-003a */
export const suspendOrganization = (id) => dataOf(http.post(`/admin/organizations/${id}/suspend`));
export const reactivateOrganization = (id) => dataOf(http.post(`/admin/organizations/${id}/reactivate`));

// El sitio público (sin sesión) -----------------------------------------------

/** US-027: los pines del mapa, livianos: [{id, lat, lng, color_pin}] (R-MAP-02); US-028: con filtros. */
export const fetchPins = async (filters = {}) => (await dataOf(http.get('/public/worksites', { params: filters }))).data;

/** US-028: lo que los filtros pueden elegir (los municipios del mapa). */
export const fetchMapFilters = async () => (await dataOf(http.get('/public/worksites/filters'))).data;

/** US-029 / US-017: la obra, sus contratos y su línea de tiempo, al tocar su pin. */
export const fetchWorksite = async (id) => (await dataOf(http.get(`/public/worksites/${id}`))).data;

/** US-025: el Recibo de Inmutabilidad público de una evidencia (su receipt_url). */
export const fetchReceipt = async (url) => (await dataOf(http.get(url))).data;

/**
 * US-024: la prueba de inclusión de un archivo, buscada solo por su hash (el
 * archivo nunca se envía); null si GovTrace no tiene sellado ese archivo.
 * reportId: solo en esa evidencia (el modo contextual).
 */
export async function findProof(sha256, reportId) {
    try {
        return (await dataOf(http.get(`/public/proofs/${sha256}`, { params: reportId ? { report: reportId } : {} }))).data;
    } catch (error) {
        if (error.response?.status === 404) {
            return null;
        }
        throw error;
    }
}
