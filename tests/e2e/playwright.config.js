// Pruebas en navegador por rol. Guía en tests/e2e/README.md.
//
// Arrancan su propio servidor (el integrado de PHP) contra una base aparte,
// sena_e2e, que global-setup.js reinstala con los datos de demostración
// antes de cada ejecución: nunca tocan la base de trabajo.
const { defineConfig } = require('@playwright/test');
const path = require('path');

const PUERTO = process.env.E2E_PUERTO || '8081';
const BASE_DATOS = process.env.E2E_DB || 'sena_e2e';

module.exports = defineConfig({
  testDir: './specs',
  // Los recorridos comparten la base (un paso crea lo que el siguiente usa):
  // un solo trabajador, en orden de archivo.
  fullyParallel: false,
  workers: 1,
  // En el CI, un reintento absorbe la lentitud ocasional de la máquina compartida.
  retries: process.env.CI ? 1 : 0,
  timeout: 90_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'informe' }]],
  outputDir: 'resultados',
  globalSetup: require.resolve('./global-setup.js'),
  use: {
    baseURL: `http://127.0.0.1:${PUERTO}`,
    // En Windows sin los navegadores de Playwright: E2E_CANAL=msedge.
    channel: process.env.E2E_CANAL || undefined,
    locale: 'es-CO',
    timezoneId: 'America/Bogota',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: {
    command: `${process.env.E2E_PHP || 'php'} -S 127.0.0.1:${PUERTO} -t "${path.resolve(__dirname, '..', '..')}"`,
    url: `http://127.0.0.1:${PUERTO}/login.php`,
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
    env: { ...process.env, DB_NAME: BASE_DATOS, APP_URL: '', DEV_MODE: 'true', APP_TIMEZONE: 'America/Bogota' },
  },
});
