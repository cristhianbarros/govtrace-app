// Lo que comparten los flujos de punta a punta (it. 40a): las cuentas que
// deja tests/e2e/fixture.php, los dominios de esta corrida y los enlaces del
// correo de desarrollo (MAIL_MAILER=log escribe cada correo en storage/logs/mail.log).
import { closeSync, fstatSync, openSync, readSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const organization = new URL(process.env.E2E_BASE_URL ?? 'http://veeduria-e2e.govtrace.localhost:8080');
const apex = organization.hostname.replace(/^veeduria-e2e\./, '');
const port = organization.port ? `:${organization.port}` : '';

export const ORG = organization.origin;
export const CENTRAL = `${organization.protocol}//${apex}${port}`;
export const orgUrl = (subdomain) => `${organization.protocol}//${subdomain}.${apex}${port}`;

export const PASSWORD = 'Veeduria#2026';
export const PEOPLE = {
    superAdmin: 'e2e.superadmin@govtrace.test',
    admin: 'e2e.admin@correo.co',
    veedor: 'e2e.veedor@correo.co',
};
export const WORKSITE = 'Parque de pruebas sin conexión';

export async function logIn(page, base, email, password = PASSWORD) {
    await page.goto(`${base}/login`);
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Contraseña').fill(password);
    await page.getByRole('button', { name: 'Entrar' }).click();
}

const MAIL_LOG = fileURLToPath(new URL('../../../storage/logs/mail.log', import.meta.url));
const TAIL_BYTES = 4 * 1024 * 1024;
const LINK = /https?:\/\/[^\s"'<>\])]+\/(?:set-password|reset-password)\/[^\s"'<>\])]+/;

/** The link of the newest mail to $email, on this run's port (the mail log can be large: only its end is read). */
export function latestLinkTo(email) {
    const file = openSync(MAIL_LOG, 'r');
    const size = fstatSync(file).size;
    const buffer = Buffer.alloc(Math.min(size, TAIL_BYTES));
    readSync(file, buffer, 0, buffer.length, size - buffer.length);
    closeSync(file);

    const entries = buffer.toString('utf8').split(/^(?=\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\] )/m).reverse();
    for (const entry of entries) {
        const link = entry.includes(email) ? entry.match(LINK) : null;
        if (link) {
            const url = new URL(link[0]);
            url.port = organization.port;
            return url.toString();
        }
    }
    throw new Error(`No hay un correo con enlace para ${email} en storage/logs/mail.log`);
}

/** A link of this environment, on this run's port (the one of APP_URL can differ in CI). */
export function onThisPort(link) {
    const url = new URL(link);
    url.port = organization.port;
    return url.toString();
}

/** It. 40b: "Salir" vive en el menú de cuenta de la cabecera, y siempre pregunta antes. */
export async function logOut(page) {
    await page.locator('header button[aria-haspopup="menu"]').click();
    await page.getByRole('menuitem', { name: 'Salir' }).click();
    await page.getByRole('button', { name: 'Sí, cerrar sesión' }).click();
    await page.waitForURL('**/login');
}

