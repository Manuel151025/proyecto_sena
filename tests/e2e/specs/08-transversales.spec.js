// Calendario, avisos, perfil, configuración y bitácora.
const { test, expect } = require('@playwright/test');
const { entrar, avisos, enviar, abrirModal } = require('../helpers');

test.describe.configure({ mode: 'serial' });

const EVENTO = 'Socialización de la fase de análisis (e2e)';

test('el instructor crea un evento para su ficha y el aprendiz lo ve con su aviso', async ({ page }) => {
  await entrar(page, 'instructor');
  await page.goto('/index.php/calendario');
  await abrirModal(page, 'modalEvento');
  await page.fill('#ev_titulo', EVENTO);
  const ficha = await page.$eval('#ev_ficha', s => ([...s.options].find(o => o.textContent.includes('2845671')) || {}).value);
  await page.selectOption('#ev_ficha', ficha);
  await enviar(page, '#modalEvento button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/evento/i);

  await entrar(page, 'aprendiz');
  const avisosApi = await (await page.request.get('/index.php/api/notificaciones')).json();
  expect(JSON.stringify(avisosApi)).toContain('e2e');
  const hoy = new Date();
  const desde = new Date(hoy.getTime() - 40 * 864e5).toISOString().slice(0, 10);
  const hasta = new Date(hoy.getTime() + 40 * 864e5).toISOString().slice(0, 10);
  const eventos = await (await page.request.get(`/index.php/calendario/api?start=${desde}&end=${hasta}`)).json();
  expect(JSON.stringify(eventos)).toContain('e2e');
});

test('un aprendiz de otra ficha no ve ese evento', async ({ page }) => {
  // Aprendiz de la ficha 2823782 (ACUI): no está en 2845671.
  await entrar(page, 'coordinador');
  await page.goto('/index.php/matriculas?ficha_id=4');
  const email = await page.locator('main').innerText().then(t => (t.match(/[a-z0-9._]+@soy\.sena\.edu\.co/) || [])[0]);
  expect(email).toBeTruthy();
  await entrar(page, email);
  const hoy = new Date();
  const desde = new Date(hoy.getTime() - 40 * 864e5).toISOString().slice(0, 10);
  const hasta = new Date(hoy.getTime() + 40 * 864e5).toISOString().slice(0, 10);
  const eventos = await (await page.request.get(`/index.php/calendario/api?start=${desde}&end=${hasta}`)).json();
  expect(JSON.stringify(eventos)).not.toContain('e2e');
});

test('el perfil cambia el nombre y rechaza una contraseña actual incorrecta', async ({ page }) => {
  await entrar(page, 'instructor2');
  await page.goto('/index.php/perfil');
  await page.fill('#perfil-nombre', "Jorge Iván Salas D'Costa");
  await enviar(page, 'form:has(input[value=datos]) button[type=submit]');
  await expect(page.locator('.menu-usuario .dropdown-header')).toHaveText("JORGE IVÁN SALAS D'COSTA");

  await page.fill('#pw-cur', 'NoEsLaActual2026');
  await page.fill('#pw-nueva', 'OtraClave2026');
  await page.fill('#pw-confirmar', 'OtraClave2026');
  await enviar(page, 'form:has(input[value=contrasena]) button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/actual/i);
});

test('coordinación cambia la configuración y queda en la bitácora', async ({ page }) => {
  await entrar(page, 'coordinador');
  await page.goto('/index.php/configuracion');
  await page.fill('#cfg_regional', 'Centro Tecnológico de la Amazonia (prueba)');
  await enviar(page, 'form:has(input[value=guardar]) button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/guardad|actualizad/i);
  await expect(page.locator('#cfg_regional')).toHaveValue('Centro Tecnológico de la Amazonia (prueba)');

  await page.goto('/index.php/logs?modulo=Configuracion');
  await expect(page.locator('main')).toContainText(/configuraci/i);
  const [descarga] = await Promise.all([page.waitForEvent('download'), page.click('a[href*="/logs/exportar"]')]);
  expect(descarga.suggestedFilename()).toMatch(/\.(xlsx|csv)$/);
});

test('la campana marca los avisos como leídos', async ({ page }) => {
  await entrar(page, 'aprendiz');
  const antes = await (await page.request.get('/index.php/api/notificaciones')).json();
  expect(antes.count).toBeGreaterThan(0);
  const [token, tab] = await page.evaluate(() => [window.__csrfToken, window.__tabId]);
  const r = await page.request.post('/index.php/api/notificaciones', { form: { csrf_token: token, _tab: tab, action: 'marcar_todas' } });
  expect((await r.json()).ok).toBe(true);
  const despues = await (await page.request.get('/index.php/api/notificaciones')).json();
  expect(despues.count).toBe(0);
});
