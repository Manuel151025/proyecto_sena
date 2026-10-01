// Control de acceso: lo que un rol no puede ver ni hacer, aunque fuerce la URL
// o envíe el formulario a mano.
const { test, expect } = require('@playwright/test');
const { entrar, avisos } = require('../helpers');

const PROHIBIDAS = {
  aprendiz: ['/usuarios', '/estructura', '/programas', '/competencias', '/resultados-aprendizaje', '/matriculas', '/asignaciones',
    '/reportes', '/logs', '/configuracion', '/usuarios/importar', '/matriculas/importar', '/evaluaciones/importar'],
  // El instructor sí consulta programas, competencias y RAP (HU-11).
  instructor: ['/usuarios', '/estructura', '/logs', '/configuracion', '/usuarios/importar', '/matriculas/importar',
    '/competencias/importar', '/estructura/importar'],
};

for (const [rol, rutas] of Object.entries(PROHIBIDAS)) {
  test(`${rol}: las pantallas de otro rol lo devuelven a su panel con un aviso`, async ({ page }) => {
    await entrar(page, rol);
    for (const ruta of rutas) {
      await page.goto(`/index.php${ruta}`);
      expect(page.url(), `${rol} en ${ruta}`).toMatch(/\/index\.php\/dashboard$/);
      expect((await avisos(page)).join(' '), `${rol} en ${ruta}`).toMatch(/permiso|no tienes/i);
    }
  });
}

/** Envía un POST con la sesión de la página, con o sin el token CSRF. */
async function publicar(page, ruta, datos, { conToken = true } = {}) {
  const [token, tab] = await page.evaluate(() => [window.__csrfToken, window.__tabId]);
  const form = { ...datos, _tab: tab };
  if (conToken) form.csrf_token = token;
  return page.request.post(`/index.php${ruta}`, { form, maxRedirects: 0 });
}

test('un instructor no puede ejecutar acciones de coordinación enviando el formulario a mano', async ({ page }) => {
  await entrar(page, 'instructor');
  const intentos = [
    ['/usuarios', { action: 'crear', nombre: 'INTRUSO', email: 'intruso@sena.edu.co', rol: 'coordinador' }],
    ['/asignaciones', { action: 'asignar', ficha_id: '1', competencia_id: '1', instructor_id: '2' }],
    ['/matriculas', { action: 'retirar', id: '1' }],
    ['/configuracion', { action: 'guardar', system_title: 'X', regional: 'X' }],
  ];
  for (const [ruta, datos] of intentos) {
    const r = await publicar(page, ruta, datos);
    expect(r.status(), `${ruta} ${datos.action}`).toBe(302);
    expect(r.headers().location, `${ruta} ${datos.action}`).toMatch(/\/index\.php\/dashboard$/);
  }
  // Y la cuenta intrusa no se creó.
  await entrar(page, 'coordinador');
  await page.goto('/index.php/usuarios?search=intruso');
  await expect(page.locator('main')).not.toContainText('intruso@sena.edu.co');
});

test('un formulario sin token CSRF se rechaza con 403', async ({ page }) => {
  await entrar(page, 'coordinador');
  const r = await publicar(page, '/usuarios', { action: 'crear', nombre: 'SIN TOKEN', email: 'sintoken@sena.edu.co', rol: 'instructor' }, { conToken: false });
  expect(r.status()).toBe(403);
  expect(await r.text()).toContain('No se pudo procesar el formulario');
});

test('un aprendiz no ve el expediente de otro aunque cambie el id en la URL', async ({ page }) => {
  // La coordinación ve la lista de la ficha: tomamos dos aprendices de ella.
  await entrar(page, 'coordinador');
  await page.goto('/index.php/seguimiento');
  const ficha = await page.$eval('a[href*="ficha_id"]', a => a.getAttribute('href'));
  await page.goto(ficha);
  const lista = await page.$$eval('.lista-aprendices a', as => as.map(a => ({ href: a.getAttribute('href'), texto: a.innerText.split('\n')[0].trim() })));
  expect(lista.length).toBeGreaterThan(2);

  await entrar(page, 'aprendiz');
  const propio = (await page.locator('.menu-usuario .dropdown-header').innerText()).trim();
  const ajeno = lista.find(a => a.texto && !a.texto.toUpperCase().includes(propio.toUpperCase()));
  const id = new URL(ajeno.href, 'http://x/').searchParams.get('aprendiz_id');
  await page.goto(`/index.php/seguimiento?aprendiz_id=${id}`);
  const texto = (await page.locator('main').innerText()).toUpperCase();
  expect(texto).not.toContain(ajeno.texto.toUpperCase());
});
