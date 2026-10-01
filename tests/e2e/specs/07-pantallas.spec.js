// Barrido de todas las pantallas de cada rol: responden, no tienen errores de
// JavaScript ni bloqueos de la CSP, y en el teléfono no desbordan a lo ancho.
const { test, expect } = require('@playwright/test');
const { entrar, vigilar, desbordaHorizontal } = require('../helpers');

const RUTAS = {
  coordinador: ['/dashboard', '/usuarios', '/usuarios/importar', '/estructura', '/estructura/importar', '/programas', '/competencias',
    '/competencias/importar', '/resultados-aprendizaje', '/resultados-aprendizaje/importar', '/fichas', '/fichas/ver?id=1',
    '/matriculas', '/matriculas/importar', '/asignaciones', '/proyectos', '/fases', '/actividades', '/evaluaciones',
    '/evaluaciones/importar', '/seguimiento', '/seguimiento?ficha_id=1', '/evidencias', '/retroalimentacion', '/mejoramiento',
    '/reportes', '/logs', '/perfil', '/calendario', '/configuracion'],
  instructor: ['/dashboard', '/fichas', '/fichas/ver?id=1', '/matriculas', '/asignaciones', '/proyectos', '/fases', '/actividades',
    '/evaluaciones', '/evaluaciones/importar', '/seguimiento', '/seguimiento?ficha_id=1', '/evidencias', '/retroalimentacion',
    '/mejoramiento', '/reportes', '/perfil', '/calendario'],
  aprendiz: ['/dashboard', '/fichas', '/proyectos', '/fases', '/actividades', '/evaluaciones', '/seguimiento', '/evidencias',
    '/retroalimentacion', '/mejoramiento', '/perfil', '/calendario'],
};

for (const movil of [false, true]) {
  test.describe(movil ? 'en el teléfono (390 px, tema oscuro)' : 'en escritorio', () => {
    if (movil) {
      test.use({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    }
    for (const [rol, rutas] of Object.entries(RUTAS)) {
      test(`${rol}: todas sus pantallas`, async ({ page }) => {
        test.setTimeout(180_000);
        if (movil) await page.addInitScript(() => { try { localStorage.setItem('sena-theme', 'dark'); } catch (e) { /* */ } });
        const problemas = vigilar(page);
        await entrar(page, rol);
        for (const ruta of rutas) {
          const r = await page.goto(`/index.php${ruta}`);
          expect(r.status(), `${rol} ${ruta}`).toBe(200);
          expect(page.url(), `${rol} ${ruta} no debe devolver al inicio de sesión`).not.toContain('/login.php');
          await page.waitForLoadState('networkidle');
          expect(problemas, `${rol} ${ruta}`).toEqual([]);
          if (movil) expect(await desbordaHorizontal(page), `${rol} ${ruta} desborda a lo ancho`).toBe(false);
          await expect(page.locator('.alert-flat.danger, .alert-danger'), `${rol} ${ruta} muestra un error`).toHaveCount(0);
        }
      });
    }
  });
}
