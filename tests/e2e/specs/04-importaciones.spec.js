// Importaciones en dos pasos con los archivos de docs/ejemplos: nada se guarda
// en la vista previa; al confirmar se guardan las filas válidas; reimportar no
// duplica.
const path = require('path');
const { test, expect } = require('@playwright/test');
const { EJEMPLOS, entrar, avisos, enviar } = require('../helpers');

test.describe.configure({ mode: 'serial' });

/** Sube un archivo en la pantalla de importación y devuelve la vista previa. */
async function analizar(page, ruta, archivo, ficha = null) {
  await page.goto(`/index.php${ruta}`);
  if (ficha) {
    const valor = await page.$eval('#imp_ficha_id', (s, f) => ([...s.options].find(o => o.textContent.includes(f)) || {}).value, ficha);
    await page.selectOption('#imp_ficha_id', valor);
  }
  await page.setInputFiles('#archivo', path.join(EJEMPLOS, archivo));
  await enviar(page, 'form[enctype] button[type=submit]');
  const filas = await page.$$eval('.tabla-previa tbody tr', ts => ts.map(t => t.innerText.replace(/\s+/g, ' ').trim()));
  return { filas };
}

/** Confirma la vista previa y devuelve el texto del panel de resultado. */
async function confirmar(page) {
  await enviar(page, 'input[value=confirmar] ~ button');
  const texto = (await page.locator('main').innerText()).replace(/\s+/g, ' ');
  expect(texto).toContain('Importación terminada');
  return texto;
}

test('usuarios: vista previa sin guardar, confirmación y reimportación sin duplicar', async ({ page }) => {
  await entrar(page, 'coordinador');
  const { filas } = await analizar(page, '/usuarios/importar', 'usuarios.csv');
  expect(filas).toHaveLength(3);
  // Aún no existen: la vista previa no guarda nada.
  await page.goto('/index.php/usuarios?search=aprojas.ejemplo');
  await expect(page.locator('main')).not.toContainText('aprojas.ejemplo@sena.edu.co');

  // Al volver, la vista previa pendiente sigue ahí para confirmarla.
  await page.goto('/index.php/usuarios/importar');
  await expect(page.locator('.tabla-previa tbody tr')).toHaveCount(3);
  expect(await confirmar(page)).toMatch(/\b3 creados/i);
  await page.goto('/index.php/usuarios?search=ejemplo');
  await expect(page.locator('main')).toContainText('aprojas.ejemplo@sena.edu.co');
  await expect(page.locator('main')).toContainText('CAMILA ANDREA SUÁREZ PEÑA');

  const otra = await analizar(page, '/usuarios/importar', 'usuarios.csv');
  expect(otra.filas.join(' ')).toMatch(/ya tiene cuenta: se omitirá/i);
});

test('competencias y luego sus resultados de aprendizaje', async ({ page }) => {
  await entrar(page, 'coordinador');
  const c = await analizar(page, '/competencias/importar', 'competencias.csv');
  expect(c.filas).toHaveLength(2);
  expect(await confirmar(page)).toMatch(/\b2 cread/i);

  const r = await analizar(page, '/resultados-aprendizaje/importar', 'resultados.csv');
  expect(r.filas).toHaveLength(3);
  expect(await confirmar(page)).toMatch(/\b3 cread/i);
  await page.goto('/index.php/resultados-aprendizaje?search=220501099-01');
  await expect(page.locator('main')).toContainText('ELABORAR EL MANUAL TÉCNICO DE LA SOLUCIÓN');
});

test('matrículas desde CSV en una ficha elegida', async ({ page }) => {
  await entrar(page, 'coordinador');
  const { filas } = await analizar(page, '/matriculas/importar', 'matriculas.csv', '2912345');
  expect(filas).toHaveLength(3);
  expect(await confirmar(page)).toMatch(/\b3 /);
  await page.goto('/index.php/matriculas?search=1117600003');
  await expect(page.locator('main')).toContainText('DANIELA ACOSTA MUÑOZ');
});

test('matrículas desde un Excel (.xlsx) generado por el propio sistema', async ({ page }) => {
  await entrar(page, 'coordinador');
  // Mismas personas que el CSV ya importado: deben leerse las 3 filas y
  // reconocerse como ya matriculadas, no aparecer vacías.
  const { filas } = await analizar(page, '/matriculas/importar', 'matriculas.xlsx', '2912345');
  expect(filas).toHaveLength(3);
  expect(filas.join(' ')).toContain('1117600001');
});

test('juicios del reporte de Sofia Plus, cargados por el instructor líder de la ficha', async ({ page }) => {
  await entrar(page, 'instructor');
  const { filas } = await analizar(page, '/evaluaciones/importar', 'juicios_sofia.csv');
  expect(filas.length).toBeGreaterThanOrEqual(3);
  await confirmar(page);

  // A y D registrados; el «POR EVALUAR» no borra nada.
  await page.goto('/index.php/evaluaciones?search=1117500101');
  const texto = await page.locator('main').innerText();
  expect(texto).toContain('220501094-01');
  expect(texto).toContain('220501094-02');
});
