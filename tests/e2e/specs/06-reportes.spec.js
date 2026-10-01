// Reportes (RF05, RNF03): cada reporte en los tres formatos, con su firma de
// archivo, y el alcance por rol.
const { test, expect } = require('@playwright/test');
const { entrar, avisos } = require('../helpers');

const FIRMAS = { xlsx: 'PK', pdf: '%PDF', csv: '﻿' };
const hoy = new Date();
const fecha = d => d.toISOString().slice(0, 10);
const RANGO = { desde: fecha(new Date(hoy.getTime() - 300 * 864e5)), hasta: fecha(hoy) };

async function descargar(page, params) {
  const r = await page.request.get(`/index.php/reportes/descargar?${new URLSearchParams(params)}`, { maxRedirects: 0, timeout: 120_000 });
  return { estado: r.status(), cuerpo: r.status() === 200 ? await r.body() : Buffer.alloc(0), r };
}

test('coordinación descarga los seis reportes en Excel, PDF y CSV', async ({ page }) => {
  test.setTimeout(240_000);
  await entrar(page, 'coordinador');
  await page.goto('/index.php/reportes');
  const ficha = await page.$eval('#rep_ficha', s => (s.options[1] || {}).value || '');
  for (const tipo of ['ficha', 'fichas', 'instructor', 'competencia', 'riesgo', 'historial']) {
    for (const formato of ['xlsx', 'pdf', 'csv']) {
      const { estado, cuerpo } = await descargar(page, { tipo, formato, ficha_id: ficha, ...RANGO });
      expect(estado, `${tipo}.${formato}`).toBe(200);
      const firma = formato === 'csv' ? cuerpo.subarray(0, 3).toString('utf8') : cuerpo.subarray(0, FIRMAS[formato].length).toString('latin1');
      expect(firma, `${tipo}.${formato}`).toBe(FIRMAS[formato]);
    }
  }
});

test('un instructor no obtiene el reporte de una ficha que no tiene a cargo', async ({ page }) => {
  await entrar(page, 'instructor4');
  const { estado } = await descargar(page, { tipo: 'ficha', formato: 'xlsx', ficha_id: '1' });
  expect(estado).toBe(302);
  await page.goto('/index.php/reportes');
  expect((await avisos(page)).join(' ')).toMatch(/no tienes a cargo|elige una ficha/i);
});

test('el historial de cambios se limita a periodos de un año', async ({ page }) => {
  await entrar(page, 'coordinador');
  const { estado } = await descargar(page, { tipo: 'historial', formato: 'xlsx', desde: '2020-01-01', hasta: fecha(hoy) });
  expect(estado).toBe(302);
  await page.goto('/index.php/reportes');
  expect((await avisos(page)).join(' ')).toContain('hasta un año');
});

test('las exportaciones neutralizan fórmulas en el CSV', async ({ page }) => {
  await entrar(page, 'coordinador');
  const r = await page.request.get('/index.php/usuarios/exportar?formato=csv');
  expect(r.status()).toBe(200);
  const texto = (await r.body()).toString('utf8');
  // Ninguna celda empieza por un carácter que Excel ejecute como fórmula.
  for (const linea of texto.split(/\r?\n/).slice(1)) {
    for (const celda of linea.split(';')) {
      expect(celda.replace(/^"/, '')).not.toMatch(/^[=+@]/);
    }
  }
});
