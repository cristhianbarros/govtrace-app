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

/** US-019: hasta 5 obras a menos de la geocerca, de la más cercana a la más lejana. La ubicación va en el cuerpo, nunca en la URL (it. 45f). */
export const fetchNearbyWorksites = async (latitude, longitude) => (await dataOf(http.post('/worksites/nearby', { latitude, longitude }))).data;

/** It. 44f (US-059-LEG): el ciudadano informa a la veeduría — primero el código a su correo, después el informe (multipart). */
export const requestCitizenCode = (data) => dataOf(http.post('/citizen-reports/code', data));
export const sendCitizenReport = (form) => dataOf(http.post('/citizen-reports', form));
/** Y el Administrador los lee, los responde o los descarta. */
export const fetchCitizenReports = async () => (await dataOf(http.get('/citizen-reports'))).data;
export const answerCitizenReport = (id, answer) => dataOf(http.post(`/citizen-reports/${id}/answer`, { answer }));
export const discardCitizenReport = (id) => dataOf(http.post(`/citizen-reports/${id}/discard`));

/** US-008: el reporte con sus archivos y sus SHA-256, como multipart. */
export const sendReport = (form) => dataOf(http.post('/reports', form));
// It. 43g (V7): el Super Administrador, en nombre de una organización que lo autorizó (US-042-SEC).
export const searchContractsOf = (tenantId) => async (keyword) => (await dataOf(http.get(`/admin/organizations/${tenantId}/contracts/search`, { params: { q: keyword } }))).data;
export const sendReportOnBehalf = (tenantId, form) => dataOf(http.post(`/admin/organizations/${tenantId}/reports`, form));

/** Cerrar sesión (US-018 avisa antes si hay reportes sin enviar). */
export const logout = () => dataOf(http.post('/logout'));
// It. 40c: cambiar la contraseña con la sesión abierta (en una organización o en el panel global).
export const changePassword = (data) => dataOf(http.put('/account/password', data));

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
// It. 43j (V3, US-061-USR): los administradores de la organización, e invitar a otro.
export const fetchAdministrators = async () => (await dataOf(http.get('/administrators'))).data;
export const inviteAdministrator = (data) => dataOf(http.post('/administrators/invite', data));

/** US-006 / US-041-USR */
export const deactivateObserver = (id) => dataOf(http.post(`/observers/${id}/deactivate`));
export const reactivateObserver = (id) => dataOf(http.post(`/observers/${id}/reactivate`));

// US-040-USR: reenviar o revocar una invitación pendiente.
export const resendInvitation = (id) => dataOf(http.post(`/observers/${id}/invitation/resend`));
export const revokeInvitation = (id) => dataOf(http.post(`/observers/${id}/invitation/revoke`));

/** US-012 */
export const fetchTerritory = async () => (await dataOf(http.get('/territory'))).data;
export const searchTerritories = async (keyword) => (await dataOf(http.get('/territory/search', { params: { q: keyword } }))).data;
export const saveTerritory = (codes) => dataOf(http.put('/territory', { codes }));

/** US-015: { data, meta: { current_page, last_page, total } } */
export const fetchContracts = ({ sort, direction, page, q }) => dataOf(http.get('/contracts', { params: { sort, direction, page, ...(q ? { q } : {}) } }));

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
/** It. 44d: el NIT, la inscripción o los dos. */
export const updateOrganizationLegalData = (id, data) => dataOf(http.put(`/admin/organizations/${id}/nit`, data));
// It. 43a (V2): el Administrador de cada organización — asignarlo si no tiene, y reenviar o revocar su invitación.
export const assignAdministrator = (id, data) => dataOf(http.post(`/admin/organizations/${id}/administrators`, data));
export const resendAdministratorInvitation = (id, userId) => dataOf(http.post(`/admin/organizations/${id}/administrators/${userId}/invitation/resend`));
export const revokeAdministratorInvitation = (id, userId) => dataOf(http.post(`/admin/organizations/${id}/administrators/${userId}/invitation/revoke`));
// It. 43j (V3): el que se fue, desactivado; nunca el único activo.
export const deactivateAdministrator = (id, userId) => dataOf(http.post(`/admin/organizations/${id}/administrators/${userId}/deactivate`));
export const reactivateAdministrator = (id, userId) => dataOf(http.post(`/admin/organizations/${id}/administrators/${userId}/reactivate`));

// It. 46a (US-063-USR): varios Super Administradores, y nunca ninguno.
export const fetchSuperAdministrators = async () => (await dataOf(http.get('/admin/super-administrators/data'))).data;
export const inviteSuperAdministrator = (name, email) => dataOf(http.post('/admin/super-administrators', { name, email }));
export const resendSuperAdministratorInvitation = (userId) => dataOf(http.post(`/admin/super-administrators/${userId}/invitation/resend`));
export const revokeSuperAdministratorInvitation = (userId) => dataOf(http.post(`/admin/super-administrators/${userId}/invitation/revoke`));
export const deactivateSuperAdministrator = (userId) => dataOf(http.post(`/admin/super-administrators/${userId}/deactivate`));
export const reactivateSuperAdministrator = (userId) => dataOf(http.post(`/admin/super-administrators/${userId}/reactivate`));

/** US-038-CFG: { configurable, fixed } */
export const fetchParameters = () => dataOf(http.get('/admin/parameters/data'));
export const updateParameter = (key, value) => dataOf(http.put(`/admin/parameters/${key}`, { value }));

/** US-043-MON: todo el log, { data, meta }. */
export const fetchGlobalAuditLog = (page) => dataOf(http.get('/admin/audit/data', { params: { page } }));

/** US-014: la última sincronización con SECOP II, o null. */
export const fetchSecopHealth = async () => (await dataOf(http.get('/admin/secop-health/data'))).data;

// Sellado: la cuenta patrocinadora y la vigencia del contrato (US-022), las fallas (US-047-MNT) y las comisiones (US-004).
export const fetchSealing = () => dataOf(http.get('/admin/sealing/data'));
export const requeueSeals = (seals) => dataOf(http.post('/admin/sealing/requeue', { seals }));
export const fetchSealingCosts = () => dataOf(http.get('/admin/costs/data'));

/** US-053-RPT: el resumen de uso por organización. */
export const fetchUsage = async () => (await dataOf(http.get('/admin/usage/data'))).data;

/** US-003a */
export const suspendOrganization = (id) => dataOf(http.post(`/admin/organizations/${id}/suspend`));
export const reactivateOrganization = (id) => dataOf(http.post(`/admin/organizations/${id}/reactivate`));

// US-003b: la baja definitiva — la primera confirmación da el token de la segunda.
export const startDecommission = (id) => dataOf(http.post(`/admin/organizations/${id}/decommission/start`));
export const confirmDecommission = (id, token, subdomain) => dataOf(http.post(`/admin/organizations/${id}/decommission`, { token, subdomain }));

// El sitio público (sin sesión) -----------------------------------------------

/** US-027: los pines del mapa, livianos: [{id, lat, lng, color_pin}] (R-MAP-02); US-028: con filtros. */
export const fetchPins = async (filters = {}) => (await dataOf(http.get('/public/worksites', { params: filters }))).data;

/** US-028: lo que los filtros pueden elegir (los municipios del mapa). */
// It. 43b (V15): sincronizar con SECOP II ahora, sin esperar a la madrugada.
export const syncSecopNow = () => dataOf(http.post('/admin/secop-health/sync'));
export const fetchMapFilters = async () => (await dataOf(http.get('/public/worksites/filters'))).data;
// It. 40c: el mapa como lista, con los mismos filtros; se pide al abrir la lista.
export const fetchWorksiteList = async (filters = {}) => (await dataOf(http.get('/public/worksites/list', { params: filters }))).data;

/** US-051-RPT: las estadísticas públicas del territorio. */
export const fetchPublicStats = () => dataOf(http.get('/public/stats'));

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

// It. 43k (V10, US-062-ALT): una veeduría pide su alta desde el Inicio; el Super Administrador la decide.
export const requestOrganization = (data) => dataOf(http.post('/organization-requests', data));
export const fetchOrganizationRequests = async () => (await dataOf(http.get('/admin/organization-requests/data'))).data;
export const rejectOrganizationRequest = (id, reason) => dataOf(http.post(`/admin/organization-requests/${id}/reject`, { reason }));

