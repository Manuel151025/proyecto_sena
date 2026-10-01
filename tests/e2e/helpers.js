// Utilidades comunes de las pruebas en navegador.
const path = require('path');

/** Contraseña de todas las cuentas de la demostración (database/semillas/demo.php). */
const CLAVE = 'Demo2026*';

/** Cuentas de la demostración y su papel en ella. */
const CUENTAS = {
  coordinador: 'coordinador@sena.edu.co',
  instructor: 'instructor@sena.edu.co',     // líder de la ficha 2845671 (ADSO)
  instructor2: 'instructor2@sena.edu.co',   // líder de 2912345; califica 220501097 en 2845671
  instructor4: 'instructor4@sena.edu.co',   // líder de 2823782 (ACUI): ajeno a las fichas de ADSO
  instructor5: 'instructor5@sena.edu.co',   // asignado a 220501096 en 2845671 y seguimiento de la etapa práctica
  aprendiz: 'aprendiz2@sena.edu.co',        // matriculado en 2845671
  aprendizEtapa: 'aprendiz@sena.edu.co',    // en etapa práctica en 2845671
  aprendizRecuperacion: 'aprendiz5@sena.edu.co',
};

const EJEMPLOS = path.resolve(__dirname, '..', '..', 'docs', 'ejemplos');
const ARCHIVOS = path.resolve(__dirname, 'archivos');

/** Inicia sesión y deja la página en el panel. */
async function entrar(page, rol, clave = CLAVE) {
  const email = CUENTAS[rol] || rol;
  // Con una sesión abierta, /login.php lleva al panel: se empieza de cero.
  await page.context().clearCookies();
  // Las acciones destructivas y las importaciones piden confirmación
  // (data-confirmar): se aceptan, como haría quien sigue el flujo.
  if (!page.__aceptaDialogos) {
    page.on('dialog', d => d.accept());
    page.__aceptaDialogos = true;
  }
  await page.goto('/login.php');
  await page.fill('#login-email', email);
  await page.fill('#pw-login', clave);
  await Promise.all([page.waitForNavigation(), page.click('form button[type=submit]')]);
}

/**
 * Registra los errores de la página: excepciones de JavaScript, errores de
 * consola (incluidos los bloqueos de la CSP) y respuestas 5xx.
 */
function vigilar(page) {
  const problemas = [];
  page.on('pageerror', e => problemas.push(`[js] ${e.message}`));
  page.on('console', m => {
    const t = m.text();
    // Los 404 se registran con su URL en 'response'; aquí sobrarían sin ella.
    if ((m.type() === 'error' || /Content Security Policy|Refused to/i.test(t)) && !/favicon|serviceWorker|Failed to load resource/i.test(t)) {
      problemas.push(`[consola] ${t}`);
    }
  });
  page.on('response', r => {
    const u = r.url();
    if (r.status() >= 500 || (r.status() === 404 && !/favicon\.ico$/.test(u))) problemas.push(`[http ${r.status()}] ${u}`);
  });
  return problemas;
}

/** Textos de los avisos (flash) visibles. */
async function avisos(page) {
  return page.$$eval('.alert-flat', ns => ns.map(n => n.innerText.replace(/\s+/g, ' ').trim()));
}

/** Pulsa un botón que envía un formulario y espera la página siguiente. */
async function enviar(page, selector) {
  await Promise.all([page.waitForNavigation(), page.click(selector)]);
}

/** Abre un modal con su botón y espera a que esté visible. */
async function abrirModal(page, id) {
  await page.click(`[data-bs-target="#${id}"]`);
  await page.waitForSelector(`#${id}.show`);
}

/** ¿La página tiene desplazamiento horizontal? (roto en el teléfono) */
async function desbordaHorizontal(page) {
  return page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
}

module.exports = { CLAVE, CUENTAS, EJEMPLOS, ARCHIVOS, entrar, vigilar, avisos, enviar, abrirModal, desbordaHorizontal };
