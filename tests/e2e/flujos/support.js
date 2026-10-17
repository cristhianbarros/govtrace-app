// Lo que comparten los flujos de punta a punta (it. 40a): las cuentas que
// deja tests/e2e/fixture.php, los dominios de esta corrida y los enlaces del
// correo de desarrollo (MAIL_MAILER=log escribe cada correo en storage/logs/mail.log).
import { closeSync, fstatSync, openSync, readSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { expect } from '@playwright/test';

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
    // Tiene cuenta desde antes de la declaración de impedimentos (it. 44c): la hace en su flujo.
    undeclared: 'e2e.sin-declarar@correo.co',
};
export const WORKSITE = 'Parque de pruebas sin conexión';

/** It. 46e (R-PRIV-05): each photo is reviewed, its faces already blurred, before it is attached. */
export async function attachPhoto(page, path) {
    await page.locator('input[type="file"]').first().setInputFiles(path);
    await page.getByRole('button', { name: 'Usar esta foto', exact: true }).click();
    // Con varias pruebas a la vez, el JPEG tarda más en un navegador sin GPU: más que los 5 s de siempre.
    await expect(page.getByRole('button', { name: 'Usar esta foto', exact: true })).toHaveCount(0, { timeout: 30_000 });
}

/** It. 46c (US-064-SEC): the page of the e2e worksite, named by its public id, not its number. */
export async function worksiteUrl(request) {
    const listing = await (await request.get(`${ORG}/public/worksites/list`)).json();

    return `${ORG}/worksite/${listing.data.find((worksite) => worksite.name === WORKSITE).id}`;
}

export async function logIn(page, base, email, password = PASSWORD) {
    await page.goto(`${base}/login`);
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Contraseña').fill(password);
    await page.getByRole('button', { name: 'Entrar' }).click();
}

const MAIL_LOG = fileURLToPath(new URL('../../../storage/logs/mail.log', import.meta.url));
const TAIL_BYTES = 4 * 1024 * 1024;
const LINK = /https?:\/\/[^\s"'<>\])]+\/(?:set-password|reset-password)\/[^\s"'<>\])]+/;
// It. 64: los correos salen por la cola, un momento después de la acción. Se leen
// los de esta corrida (E2E_MAIL_MARK: dónde terminaba el log al empezar, run-e2e.sh),
// así un correo de una corrida anterior a la misma dirección no pasa por el nuevo.
const RUN_MARK = Number(process.env.E2E_MAIL_MARK ?? 0);
const MAIL_WAIT_MS = 20_000;

/** The mails of this run, newest first (the mail log can be large: only its end is read). */
function mailsOfThisRun() {
    let file;
    try {
        file = openSync(MAIL_LOG, 'r');
    } catch {
        return [];
    }
    const size = fstatSync(file).size;
    const from = Math.max(size >= RUN_MARK ? RUN_MARK : 0, size - TAIL_BYTES);
    const buffer = Buffer.alloc(size - from);
    readSync(file, buffer, 0, buffer.length, from);
    closeSync(file);
    return buffer.toString('utf8').split(/^(?=\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\] )/m).reverse();
}

/** What `find` picks from this run's mails, newest first, once the queue sends it. */
async function waitForMail(find, missing) {
    const deadline = Date.now() + MAIL_WAIT_MS;
    for (;;) {
        const found = find(mailsOfThisRun());
        if (found) {
            return found;
        }
        if (Date.now() > deadline) {
            throw new Error(`${missing} en storage/logs/mail.log (esperado ${MAIL_WAIT_MS / 1000} s)`);
        }
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
}

/** The link of the newest mail to $email, on this run's port. */
export function latestLinkTo(email) {
    return waitForMail((entries) => {
        for (const entry of entries) {
            const link = entry.includes(email) ? entry.match(LINK) : null;
            if (link) {
                const url = new URL(link[0]);
                url.port = organization.port;
                return url.toString();
            }
        }
        return null;
    }, `No llegó un correo con enlace para ${email}`);
}

/** It. 44f: the newest mail to $email — its body, to find what the citizen got. */
export const latestMailTo = (email) => waitForMail((entries) => entries.find((entry) => entry.includes(email)), `No llegó un correo para ${email}`);

/** It. 43i: the newest mail to $email that says $text — when that address gets other mails too. */
export const latestMailAbout = (email, text) =>
    waitForMail((entries) => entries.find((entry) => entry.includes(email) && entry.includes(text)), `No llegó un correo para ${email} que diga "${text}"`);

/** It. 44f: the 6-digit code a citizen got to inform a veeduría. */
export const latestCodeTo = async (email) => (await waitForMail((entries) => entries.find((entry) => entry.includes(email) && /es: \d{6}/.test(entry)), `No llegó el código para ${email}`)).match(/es: (\d{6})/)[1];

/** A link of this environment, on this run's port (the one of APP_URL can differ in CI). */
export function onThisPort(link) {
    const url = new URL(link);
    url.port = organization.port;
    return url.toString();
}

/** It. 40b: "Salir" vive en el menú de cuenta de la cabecera, y siempre pregunta antes. */
export async function logOut(page) {
    // El de la cuenta: en un panel, "Más" también es un menú del encabezado.
    await page.locator('[data-test="account-menu"]').click();
    await page.getByRole('menuitem', { name: 'Salir' }).click();
    await page.getByRole('button', { name: 'Sí, cerrar sesión' }).click();
    await page.waitForURL('**/login');
}

