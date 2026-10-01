// El ciclo central del seguimiento con dos personas reales de la demostración:
// el instructor asignado a la competencia califica, corrige con motivo y abre un plan de
// mejoramiento; el aprendiz entrega su evidencia; el instructor la revisa y
// cierra el plan, y el RAP queda aprobado. Cada paso se mira desde los dos lados.
const path = require('path');
const { test, expect } = require('@playwright/test');
const { ARCHIVOS, entrar, avisos, enviar } = require('../helpers');

test.describe.configure({ mode: 'serial' });

const estado = { nombre: '', evaluacion: 0, rap: '' };

test('el aprendiz se identifica y parte con juicios pendientes', async ({ page }) => {
  await entrar(page, 'aprendiz');
  estado.nombre = (await page.locator('.menu-usuario .dropdown-header').innerText()).trim();
  expect(estado.nombre.length).toBeGreaterThan(5);
  await page.goto('/index.php/evaluaciones?concepto=pendiente');
  await expect(page.locator('main')).toContainText(/\d{9}-\d{2}/);
});

test('el instructor asignado emite un juicio A con retroalimentación', async ({ page }) => {
  await entrar(page, 'instructor5');
  await page.goto(`/index.php/evaluaciones?concepto=pendiente&search=${encodeURIComponent(estado.nombre)}`);
  const boton = page.locator('tbody tr, article').filter({ hasText: estado.nombre }).locator('button[data-modal="#modalEvaluar"]').first();
  const valores = JSON.parse(await boton.getAttribute('data-valores'));
  estado.evaluacion = valores.evaluacion_id;
  estado.rap = (await boton.locator('xpath=ancestor::*[self::tr or self::article][1]').innerText()).match(/\d{9}-\d{2}/)[0];
  await boton.click();
  await page.waitForSelector('#modalEvaluar.show');
  await page.click('label[for=juicioA]');
  await page.fill('#juicioComentario', 'Buen trabajo en la caracterización <b>sin HTML</b>');
  await enviar(page, '#modalEvaluar button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain('Juicio registrado: A');
});

test('cambiarlo a D exige motivo y deja historial', async ({ page }) => {
  await entrar(page, 'instructor5');
  await page.goto(`/index.php/evaluaciones?concepto=A&search=${encodeURIComponent(estado.rap)}`);
  const fila = page.locator('tbody tr, article').filter({ hasText: estado.nombre }).first();
  await fila.locator('button[data-modal="#modalEvaluar"]').click();
  await page.waitForSelector('#modalEvaluar.show');
  await page.click('label[for=juicioD]');
  await expect(page.locator('[data-bloque-motivo]')).toBeVisible();
  await expect(page.locator('#juicioMotivo')).toHaveAttribute('required', '');

  // Aunque se salte el navegador, el servidor también exige el motivo.
  const [token, tab] = await page.evaluate(() => [window.__csrfToken, window.__tabId]);
  await page.request.post('/index.php/evaluaciones', {
    form: { action: 'evaluar', evaluacion_id: String(estado.evaluacion), concepto: 'D', comentario: '', motivo: '', csrf_token: token, _tab: tab },
    maxRedirects: 0,   // si siguiera la redirección, esa visita consumiría el aviso
  });
  await page.goto('/index.php/evaluaciones');
  expect((await avisos(page)).join(' ')).toMatch(/motivo/i);

  await page.goto(`/index.php/evaluaciones?concepto=A&search=${encodeURIComponent(estado.rap)}`);
  await page.locator('tbody tr, article').filter({ hasText: estado.nombre }).first().locator('button[data-modal="#modalEvaluar"]').click();
  await page.waitForSelector('#modalEvaluar.show');
  await page.click('label[for=juicioD]');
  await page.fill('#juicioMotivo', 'El informe no incluye el diagrama de procesos');
  await enviar(page, '#modalEvaluar button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain('Juicio registrado: D');

  await page.goto(`/index.php/evaluaciones?concepto=D&search=${encodeURIComponent(estado.rap)}`);
  await page.locator('tbody tr, article').filter({ hasText: estado.nombre }).first().locator('button[data-modal="#modalDetalle"]').click();
  await page.waitForSelector('#modalDetalle.show');
  const historial = await page.locator('#modalDetalle [data-lista-historial] li').allInnerTexts();
  expect(historial[0]).toContain('A → D');
  expect(historial[0]).toContain('diagrama de procesos');
  const comentario = await page.locator('#modalDetalle [data-campo=comentario]').innerText();
  // La retroalimentación se guarda y se muestra como texto: sin etiquetas HTML.
  expect(comentario).toContain('sin HTML');
  expect(comentario).not.toContain('<b>');
});

test('el aprendiz ve su D y el aviso', async ({ page }) => {
  await entrar(page, 'aprendiz');
  await page.goto(`/index.php/evaluaciones?search=${encodeURIComponent(estado.rap)}`);
  await expect(page.locator('main')).toContainText(estado.rap);
  const res = await page.request.get('/index.php/api/notificaciones');
  const datos = await res.json();
  expect(JSON.stringify(datos)).toMatch(/juicio/i);
});

test('el instructor abre un plan de mejoramiento sobre el RAP en D', async ({ page }) => {
  await entrar(page, 'instructor5');
  await page.goto(`/index.php/mejoramiento?search=${encodeURIComponent(estado.rap)}`);
  const boton = page.locator(`button[data-modal="#modalCrear"][data-valores*='"evaluacion_id":${estado.evaluacion},']`);
  await expect(boton).toHaveCount(1);
  await boton.click();
  await page.waitForSelector('#modalCrear.show');
  await expect(page.locator('#modalCrear [data-campo=resumen]')).toContainText(estado.rap);
  await page.fill('#plan_act', 'Rehacer el informe de requisitos con el diagrama de procesos y sustentarlo');
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/plan/i);
});

test('el aprendiz entrega la evidencia del plan; un PHP disfrazado de PDF se rechaza', async ({ page }) => {
  await entrar(page, 'aprendiz');
  await page.goto('/index.php/mejoramiento');
  await expect(page.locator('main')).toContainText(estado.rap);

  await page.goto('/index.php/evidencias');
  await page.click('button[data-bs-target="#modalEnviar"]');
  await page.waitForSelector('#modalEnviar.show');
  await page.fill('#ev_titulo', 'Archivo que no es un PDF');
  await page.selectOption('#ev_rap', String(estado.evaluacion));
  await page.setInputFiles('#ev_archivo', path.join(ARCHIVOS, 'php-disfrazado.pdf'));
  await enviar(page, '#modalEnviar button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/no es|no corresponde|tipo|formato/i);

  await page.click('button[data-bs-target="#modalEnviar"]');
  await page.waitForSelector('#modalEnviar.show');
  await page.fill('#ev_titulo', 'Informe de requisitos corregido');
  await page.selectOption('#ev_rap', String(estado.evaluacion));
  await page.fill('#ev_desc', 'Incluye el diagrama de procesos');
  await page.setInputFiles('#ev_archivo', path.join(ARCHIVOS, 'evidencia.pdf'));
  await enviar(page, '#modalEnviar button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/enviada|recibid|registrad/i);

  await page.goto('/index.php/mejoramiento');
  await expect(page.locator('main')).toContainText(/en curso/i);
});

test('el instructor descarga y revisa la evidencia, y cierra el plan como cumplido', async ({ page }) => {
  await entrar(page, 'instructor5');
  await page.goto('/index.php/evidencias?estado=enviada');
  const tarjeta = page.locator('article, tr').filter({ hasText: 'Informe de requisitos corregido' }).first();
  const enlace = await tarjeta.locator('a[href*="/evidencias/archivo"]').first().getAttribute('href');
  const archivo = await page.request.get(enlace);
  expect(archivo.status()).toBe(200);
  expect((await archivo.body()).subarray(0, 5).toString()).toBe('%PDF-');
  expect(archivo.headers()['x-content-type-options']).toBe('nosniff');

  await tarjeta.locator('button[data-modal="#modalRevisar"]').click();
  await page.waitForSelector('#modalRevisar.show');
  await page.click('label[for=rev_aprobada]');
  await page.fill('#rev_retro', 'El informe ya incluye el diagrama de procesos');
  await enviar(page, '#modalRevisar button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/revis/i);

  await page.goto('/index.php/mejoramiento?estado=vigente');
  await page.locator('article, tr').filter({ hasText: estado.rap }).filter({ hasText: estado.nombre }).first()
    .locator('button[data-modal="#modalCerrar"]').click();
  await page.waitForSelector('#modalCerrar.show');
  await page.click('label[for=cierre_ok]');
  await page.fill('#cierre_obs', 'Sustentó el informe corregido');
  await enviar(page, '#modalCerrar button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/plan/i);
});

test('el aprendiz termina con el RAP aprobado, el plan cumplido y la evidencia aprobada', async ({ page }) => {
  await entrar(page, 'aprendiz');
  await page.goto(`/index.php/evaluaciones?concepto=A&search=${encodeURIComponent(estado.rap)}`);
  await expect(page.locator('main')).toContainText(estado.rap);
  await page.goto('/index.php/mejoramiento');
  await expect(page.locator('main')).toContainText(/cumplido/i);
  await page.goto('/index.php/evidencias');
  await expect(page.locator('main')).toContainText(/aprobada/i);

  // La descarga de una evidencia exige permiso: un instructor ajeno no la obtiene.
  const enlace = await page.locator('a[href*="/evidencias/archivo"]').first().getAttribute('href');
  await entrar(page, 'instructor4');
  const r = await page.request.get(enlace, { maxRedirects: 0 });
  expect(r.status()).not.toBe(200);
});
