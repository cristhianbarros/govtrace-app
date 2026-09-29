// make e2e (R-TST-03): la app de make up, en un Chromium de verdad. El
// navegador corre en el contenedor de Playwright con la red del host: Chrome
// resuelve *.localhost a 127.0.0.1, donde escucha el proxy.
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    timeout: 90_000,
    retries: 0,
    reporter: [['list']],
    outputDir: '../../storage/framework/testing/e2e',
    use: {
        ...devices['Pixel 7'],
        baseURL: process.env.E2E_BASE_URL ?? 'http://veeduria-e2e.govtrace.localhost:8080',
        geolocation: { latitude: 11.2419, longitude: -74.199, accuracy: 12 },
        permissions: ['geolocation'],
        locale: 'es-CO',
        timezoneId: 'America/Bogota',
        serviceWorkers: 'allow',
        trace: 'retain-on-failure',
    },
});
