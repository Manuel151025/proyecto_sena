// Páginas públicas: inicio de sesión, cierre y recuperación de contraseña.
const { test, expect } = require('@playwright/test');
const { CLAVE, CUENTAS, entrar, vigilar, enviar } = require('../helpers');

test('el inicio de sesión carga con CSP estricta y sin errores', async ({ page }) => {
  const problemas = vigilar(page);
  const r = await page.goto('/login.php');
  expect(r.status()).toBe(200);
  const csp = r.headers()['content-security-policy'] || '';
  expect(csp).toContain("script-src 'self'");
  expect(csp).not.toContain('unsafe-inline');
  expect(r.headers()['x-frame-options']).toBe('DENY');
  await expect(page.locator('#login-email')).toBeVisible();
  await expect(page.locator('#pw-login')).toHaveAttribute('type', 'password');
  await page.click('#toggle-pw-btn');
  await expect(page.locator('#pw-login')).toHaveAttribute('type', 'text');
  expect(problemas).toEqual([]);
});

test('una contraseña incorrecta no entra y el mensaje no dice qué dato falló', async ({ page }) => {
  await page.goto('/login.php');
  await page.fill('#login-email', CUENTAS.coordinador);
  await page.fill('#pw-login', 'NoEsLaClave2026');
  await enviar(page, 'form button[type=submit]');
  expect(page.url()).toContain('/login.php');
  await expect(page.locator('body')).toContainText('Credenciales incorrectas.');

  await page.fill('#login-email', 'nadie.existe@sena.edu.co');
  await page.fill('#pw-login', 'NoEsLaClave2026');
  await enviar(page, 'form button[type=submit]');
  await expect(page.locator('body')).toContainText('Credenciales incorrectas.');
});

test('cada rol entra a su panel y puede cerrar sesión', async ({ page }) => {
  for (const [rol, texto] of [['coordinador', 'Fichas activas'], ['instructor', 'Por calificar'], ['aprendiz', 'Mi progreso por competencia']]) {
    await entrar(page, rol);
    expect(page.url(), rol).toMatch(/\/index\.php\/dashboard$/);
    await expect(page.locator('main, body').first()).toContainText(texto);
    await page.locator('.dropdown [data-bs-toggle="dropdown"]').last().click();
    await enviar(page, 'form[action$="/logout"] button[type=submit]');
    expect(page.url()).toContain('/login.php');
    // Tras cerrar sesión, una página interna vuelve al inicio de sesión.
    await page.goto('/index.php/dashboard');
    expect(page.url()).toContain('/login.php');
  }
});

test('recuperar la contraseña: enlace de un solo uso y la política se aplica', async ({ page, context }) => {
  await page.goto('/recover.php');
  await page.fill('#recover-email', CUENTAS.aprendizRecuperacion);
  await enviar(page, '#recover-submit');
  await expect(page.locator('.rec-exito')).toContainText('Revisa tu correo');
  // En modo desarrollo la página muestra el enlace que iría por correo.
  const enlace = await page.locator('.dev-box a').getAttribute('href');
  expect(enlace).toMatch(/recover\.php\?step=3&token=[0-9a-f]+/);

  await page.goto(enlace);
  // Pasa los controles del navegador (8 caracteres) pero no la política.
  await page.fill('#pw-new', 'soloLetrasAqui');
  await page.fill('#pw-confirm', 'soloLetrasAqui');
  await enviar(page, '#reset-submit');
  await expect(page.locator('body')).toContainText('combinar letras y números');

  const nueva = 'Recuperada2026';
  await page.fill('#pw-new', nueva);
  await page.fill('#pw-confirm', nueva);
  await enviar(page, '#reset-submit');
  expect(page.url()).toContain('/login.php');

  // El mismo enlace ya no sirve.
  await page.goto(enlace);
  await expect(page.locator('#pw-new')).toHaveCount(0);

  // Entra con la nueva contraseña, y la anterior ya no sirve.
  await entrar(page, CUENTAS.aprendizRecuperacion, nueva);
  expect(page.url()).toMatch(/dashboard$/);
  await context.clearCookies();
  await entrar(page, CUENTAS.aprendizRecuperacion, CLAVE);
  expect(page.url()).toContain('/login.php');
});

test('la solicitud de recuperación no revela si el correo existe', async ({ page }) => {
  await page.goto('/recover.php');
  await page.fill('#recover-email', 'no.registrado@sena.edu.co');
  await enviar(page, '#recover-submit');
  await expect(page.locator('.rec-exito')).toContainText('Si el correo electrónico existe en el sistema');
  await expect(page.locator('.dev-box')).toHaveCount(0);
});
