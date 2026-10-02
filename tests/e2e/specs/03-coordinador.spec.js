// Recorrido de coordinación: monta un programa nuevo de punta a punta, crea
// la cuenta de su instructor, abre la ficha, matricula y asigna.
const { test, expect } = require('@playwright/test');
const { entrar, avisos, enviar, abrirModal } = require('../helpers');

test.describe.configure({ mode: 'serial' });

const PROGRAMA = { codigo: 'E2E01', nombre: 'PROGRAMA DE PRUEBA DE EXTREMO A EXTREMO' };
const COMPETENCIA = { codigo: '990000001', nombre: 'COMPETENCIA DE PRUEBA DE EXTREMO A EXTREMO' };
const RAP = { codigo: '990000001-01', denominacion: 'RESULTADO DE PRUEBA DE EXTREMO A EXTREMO' };
const INSTRUCTOR = { nombre: "Ana María O'Neill", email: 'e2e.instructora@sena.edu.co' };
const FICHA = '9900001';
const APRENDIZ = { nombre: 'luis ángel peña', email: 'e2e.aprendiz@soy.sena.edu.co', documento: '99000111' };

let claveInstructora = '';

test.beforeEach(async ({ page }) => { await entrar(page, 'coordinador'); });

/** Elige en un <select> la opción cuyo texto contiene `texto`. */
async function elegir(page, selector, texto) {
  const valor = await page.$eval(selector, (s, t) => {
    const o = [...s.options].find(o => o.textContent.toUpperCase().includes(t.toUpperCase()));
    return o ? o.value : null;
  }, texto);
  expect(valor, `opción «${texto}» en ${selector}`).not.toBeNull();
  await page.selectOption(selector, valor);
}

test('crea un programa, su competencia y su resultado de aprendizaje', async ({ page }) => {
  await page.goto('/index.php/programas');
  await abrirModal(page, 'modalCrear');
  await page.fill('#modalCrear [name="codigo"]', PROGRAMA.codigo);
  await page.fill('#modalCrear [name="nombre"]', PROGRAMA.nombre);
  await page.fill('#modalCrear [name="duracion_horas"]', '1200');
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/cread|registrad/i);
  await expect(page.locator('main')).toContainText(PROGRAMA.nombre);

  await page.goto('/index.php/competencias');
  await abrirModal(page, 'modalCrear');
  await elegir(page, '#modalCrear [name="programa_id"]', PROGRAMA.codigo);
  await page.fill('#modalCrear [name="codigo"]', COMPETENCIA.codigo);
  await page.fill('#modalCrear [name="nombre"]', COMPETENCIA.nombre);
  await page.fill('#modalCrear [name="horas"]', '40');
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain('Competencia registrada.');

  await page.goto('/index.php/resultados-aprendizaje');
  await abrirModal(page, 'modalCrear');
  const programa = page.locator('#modalCrear [name="programa_id"]');
  if (await programa.count()) await elegir(page, '#modalCrear [name="programa_id"]', PROGRAMA.codigo);
  await elegir(page, '#modalCrear [name="competencia_id"]', COMPETENCIA.codigo);
  await page.fill('#modalCrear [name="codigo"]', RAP.codigo);
  await page.fill('#modalCrear [name="denominacion"]', RAP.denominacion);
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain('Resultado de aprendizaje registrado.');
});

test('no admite un código de competencia repetido en el mismo programa', async ({ page }) => {
  await page.goto('/index.php/competencias');
  await abrirModal(page, 'modalCrear');
  await elegir(page, '#modalCrear [name="programa_id"]', PROGRAMA.codigo);
  await page.fill('#modalCrear [name="codigo"]', COMPETENCIA.codigo);
  await page.fill('#modalCrear [name="nombre"]', 'OTRA COMPETENCIA CON EL MISMO CÓDIGO');
  await page.fill('#modalCrear [name="horas"]', '20');
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain(`ya tiene una competencia con el código ${COMPETENCIA.codigo}`);
});

test('crea la cuenta de una instructora con clave temporal que debe cambiar al entrar', async ({ page, browser }) => {
  await page.goto('/index.php/usuarios');
  await abrirModal(page, 'modalCrear');
  await page.fill('#modalCrear [name="nombre"]', INSTRUCTOR.nombre);
  await page.fill('#modalCrear [name="email"]', INSTRUCTOR.email);
  await page.selectOption('#modalCrear [name="rol"]', 'instructor');
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain('Cuenta creada.');
  claveInstructora = (await page.locator('.credencial-temporal code').innerText()).trim();
  expect(claveInstructora).toMatch(/^(?=.*\d)(?=.*[A-Za-z])[A-Za-z0-9]{10}$/);

  // La clave temporal se muestra una sola vez.
  await page.reload();
  await expect(page.locator('.credencial-temporal')).toHaveCount(0);
  // El nombre con apóstrofo no se escapa dos veces.
  await page.goto('/index.php/usuarios?search=e2e.instructora');
  await expect(page.locator('main')).toContainText("ANA MARÍA O'NEILL");

  // Primera entrada: obliga a cambiar la clave antes de usar el sistema.
  const ctx = await browser.newContext();
  const nueva = await ctx.newPage();
  await entrar(nueva, INSTRUCTOR.email, claveInstructora);
  await nueva.goto('/index.php/actividades');
  expect(nueva.url()).toContain('/perfil');
  await nueva.fill('#pw-cur', claveInstructora);
  await nueva.fill('[name="password_nueva"], #pw-new', 'Instructora2026');
  await nueva.fill('[name="password_confirmar"], #pw-conf', 'Instructora2026');
  await enviar(nueva, 'form:has(input[value=contrasena]) button[type=submit]');
  await nueva.goto('/index.php/actividades');
  expect(nueva.url()).toContain('/actividades');
  await ctx.close();
});

test('no admite dos cuentas con el mismo correo aunque cambien las mayúsculas', async ({ page }) => {
  await page.goto('/index.php/usuarios');
  await abrirModal(page, 'modalCrear');
  // Las cuentas de aprendiz se crean al matricular, con su ficha: aquí no se ofrecen.
  await expect(page.locator('#modalCrear [name="rol"] option[value="aprendiz"]')).toHaveCount(0);
  await expect(page.locator('#modalCrear')).toContainText('Los aprendices se crean al matricularlos');
  await page.fill('#modalCrear [name="nombre"]', 'OTRA PERSONA');
  await page.fill('#modalCrear [name="email"]', INSTRUCTOR.email.toUpperCase());
  await page.selectOption('#modalCrear [name="rol"]', 'coordinador');
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain(`Ya existe una cuenta con el correo ${INSTRUCTOR.email}`);
});

test('el formulario de cuentas solo ofrece los cambios que se pueden guardar', async ({ page }) => {
  // Un instructor no pasa a aprendiz: la opción no se ofrece (las cuentas de
  // aprendiz nacen en Matrículas).
  await page.goto('/index.php/usuarios?rol=instructor');
  await page.locator('[data-modal="#modalEditar"]').first().click();
  await page.waitForSelector('#modalEditar.show');
  await expect(page.locator('#editar_rol option[value="aprendiz"]')).toHaveJSProperty('disabled', true);
  await expect(page.locator('#editar_rol option[value="coordinador"]')).toHaveJSProperty('disabled', false);
  await page.keyboard.press('Escape');

  // Quien desertó recupera el acceso desde Matrículas: su fila lleva allí y no
  // ofrece un «Activar» que el servidor rechazaría.
  await page.goto('/index.php/usuarios?rol=aprendiz&estado=inactivo');
  const fila = page.locator('tbody tr').first();
  await expect(fila.locator('a[href*="/index.php/matriculas?search="]')).toHaveCount(1);
  await expect(fila.locator('input[name="action"][value="estado"]')).toHaveCount(0);
});

test('abre la ficha del programa nuevo con la instructora como líder', async ({ page }) => {
  await page.goto('/index.php/fichas');
  await abrirModal(page, 'modalCrear');
  await page.fill('#modalCrear [name="numero_ficha"]', FICHA);
  await elegir(page, '#modalCrear [name="programa_id"]', PROGRAMA.codigo);
  await elegir(page, '#modalCrear [name="instructor_id"]', "O'NEILL");
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain('Ficha creada.');

  // Número repetido: se rechaza.
  await abrirModal(page, 'modalCrear');
  await page.fill('#modalCrear [name="numero_ficha"]', FICHA);
  await elegir(page, '#modalCrear [name="programa_id"]', PROGRAMA.codigo);
  await elegir(page, '#modalCrear [name="instructor_id"]', "O'NEILL");
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain(FICHA);
});

test('matricula un aprendiz: cuenta con clave temporal y una evaluación pendiente por RAP', async ({ page }) => {
  await page.goto('/index.php/matriculas');
  await abrirModal(page, 'modalCrear');
  await page.fill('#modalCrear [name="nombre"]', APRENDIZ.nombre);
  await page.fill('#modalCrear [name="email"]', APRENDIZ.email);
  await page.fill('#modalCrear [name="numero_documento"]', APRENDIZ.documento);
  await elegir(page, '#modalCrear [name="ficha_id"]', FICHA);
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain('Aprendiz matriculado.');
  await expect(page.locator('.credencial-temporal code')).toHaveText(/^[A-Za-z0-9]{10}$/);

  await page.goto(`/index.php/matriculas?search=${APRENDIZ.documento}`);
  await expect(page.locator('main')).toContainText('LUIS ÁNGEL PEÑA');

  await page.goto(`/index.php/evaluaciones?search=${encodeURIComponent(RAP.codigo)}`);
  await expect(page.locator('main')).toContainText(RAP.codigo);

  // Un documento con letras no es válido para una cédula.
  await page.goto('/index.php/matriculas');
  await abrirModal(page, 'modalCrear');
  await page.fill('#modalCrear [name="nombre"]', 'OTRO APRENDIZ');
  await page.fill('#modalCrear [name="email"]', 'e2e.otro@soy.sena.edu.co');
  await page.selectOption('#modalCrear [name="tipo_documento"]', 'CC');
  await page.fill('#modalCrear [name="numero_documento"]', 'AB12345');
  await elegir(page, '#modalCrear [name="ficha_id"]', FICHA);
  await enviar(page, '#modalCrear button[type=submit]');
  expect((await avisos(page)).join(' ')).toMatch(/document/i);
});

test('asigna otro instructor a la competencia y la ficha muestra sus cifras', async ({ page }) => {
  await page.goto('/index.php/asignaciones');
  await abrirModal(page, 'modalAsignar');
  await elegir(page, '#modalAsignar [name="ficha_id"]', FICHA);
  await expect(page.locator('#modalAsignar [name="competencia_id"] option[value]:not([value=""])')).not.toHaveCount(0);
  await elegir(page, '#modalAsignar [name="competencia_id"]', COMPETENCIA.codigo);
  await elegir(page, '#modalAsignar [name="instructor_id"]', 'JORGE SALAS');
  await enviar(page, '#modalAsignar button[type=submit]');
  expect((await avisos(page)).join(' ')).toContain('Instructor asignado');

  await page.goto(`/index.php/fichas?search=${FICHA}`);
  await page.click(`a[href*="/fichas/ver"]`);
  await expect(page.locator('main')).toContainText(FICHA);
  await expect(page.locator('main')).toContainText('LUIS ÁNGEL PEÑA');
});

test('exporta el listado de usuarios y el de fichas en Excel', async ({ page }) => {
  for (const ruta of ['/usuarios', '/fichas']) {
    await page.goto(`/index.php${ruta}`);
    const [descarga] = await Promise.all([page.waitForEvent('download'), page.click(`a[href*="${ruta}/exportar"]`)]);
    expect(descarga.suggestedFilename()).toMatch(/\.(xlsx|csv)$/);
  }
});
