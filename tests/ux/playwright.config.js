// make ux-check (it. 40a): cada pantalla de cada rol, en un Chromium de verdad,
// contra la app de make up y la organización de tests/e2e/fixture.php.
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    timeout: 300_000,
    retries: 0,
    workers: 1,
    reporter: [['list']],
    outputDir: '../../storage/framework/testing/ux/results',
    use: {
        locale: 'es-CO',
        timezoneId: 'America/Bogota',
        geolocation: { latitude: 11.2411, longitude: -74.199, accuracy: 10 },
        permissions: ['geolocation'],
        // axe entra a la página como un script en línea, y la CSP no lo dejaría.
        bypassCSP: true,
    },
});
