// Reinstala la base de las pruebas con los datos de demostración, para que
// cada ejecución parta del mismo estado (la semilla es determinista).
const { execFileSync } = require('child_process');
const path = require('path');

module.exports = async () => {
  const base = process.env.E2E_DB || 'sena_e2e';
  // bin/instalar.php BORRA la base: nunca contra otra que no sea la de pruebas.
  if (!/^sena_e2e[a-z0-9_]*$/.test(base)) {
    throw new Error(`E2E_DB debe empezar por "sena_e2e" (es "${base}"): la instalación borra esa base.`);
  }
  execFileSync(process.env.E2E_PHP || 'php', ['bin/instalar.php', '--confirmar-borrado-total', '--demo'], {
    cwd: path.resolve(__dirname, '..', '..'),
    env: { ...process.env, DB_NAME: base },
    stdio: 'pipe',
  });
};
