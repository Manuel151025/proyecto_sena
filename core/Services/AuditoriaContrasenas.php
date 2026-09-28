<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Support\PoliticaContrasena;
use Core\Support\Transaccion;
use PDO;

/**
 * Busca cuentas cuya contraseña es una conocida y las anula.
 *
 * El historial público del repositorio contiene un volcado SQL de mayo de
 * 2026 con 123 cuentas, todas con la contraseña `admin123`, y la base del
 * VPS se creó a partir de ese volcado: cualquier cuenta de producción que
 * nunca cambió su clave se abre con una contraseña publicada. Se añaden
 * las que el propio sistema usó por defecto en otras épocas.
 *
 * Anular = asignar una clave temporal aleatoria que hay que cambiar al
 * entrar. La anterior deja de servir en el acto.
 */
final class AuditoriaContrasenas {
    /** Contraseñas a probar, tal cual (bcrypt distingue mayúsculas). */
    public const CANDIDATAS = ['admin123', 'Admin123', 'Sena2026', 'sena2026', 'SENA2026', 'Demo2026*',
                               '123456', '12345678', 'password'];

    private PDO $db;

    /** @param list<string> $candidatas */
    public function __construct(?PDO $db = null, private readonly array $candidatas = self::CANDIDATAS) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Cuentas con una contraseña conocida. Cada verificación bcrypt cuesta
     * decenas de milisegundos, así que el resultado se memoriza por hash:
     * las cuentas del volcado comparten el mismo, y se paga una sola vez.
     *
     * @param list<int>|null $ids Limitar a estas cuentas (todas si null).
     * @param callable(int $hechas, int $total):void|null $progreso
     * @return list<array{id:int, email:string, rol:string, estado:string, clave:string}>
     */
    public function buscar(?array $ids = null, ?callable $progreso = null): array {
        $sql = 'SELECT id, email, rol, estado, password FROM usuarios';
        $params = [];
        if ($ids !== null) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if ($ids === []) {
                return [];
            }
            $sql .= ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        }
        $st = $this->db->prepare($sql . ' ORDER BY rol, email');
        $st->execute($params);
        $filas = $st->fetchAll(PDO::FETCH_ASSOC);

        $porHash = [];
        $halladas = [];
        foreach ($filas as $i => $u) {
            $hash = (string)$u['password'];
            if (!array_key_exists($hash, $porHash)) {
                $porHash[$hash] = $this->claveConocida($hash);
            }
            if ($porHash[$hash] !== null) {
                $halladas[] = ['id' => (int)$u['id'], 'email' => (string)$u['email'], 'rol' => (string)$u['rol'],
                               'estado' => (string)$u['estado'], 'clave' => $porHash[$hash]];
            }
            if ($progreso !== null) {
                $progreso($i + 1, count($filas));
            }
        }
        return $halladas;
    }

    /**
     * Asigna a cada cuenta una clave temporal nueva, obligatoria de cambiar,
     * y lo deja en la bitácora. Todo o nada.
     *
     * @param list<array{id:int, email:string, rol:string}> $cuentas
     * @return list<array{id:int, email:string, rol:string, temporal:string}>
     */
    public function anular(array $cuentas): array {
        $auditoria = new Auditoria($this->db);
        return Transaccion::ejecutar($this->db, function () use ($cuentas, $auditoria): array {
            $st = $this->db->prepare('UPDATE usuarios SET password = ?, debe_cambiar_password = 1 WHERE id = ?');
            $r = [];
            foreach ($cuentas as $c) {
                $temporal = PoliticaContrasena::temporal();
                $st->execute([password_hash($temporal, PASSWORD_DEFAULT), (int)$c['id']]);
                $auditoria->registrar(null, Auditoria::PASSWORD, 'Consola',
                    "Anuló una contraseña conocida de {$c['email']} (bin/auditar-claves.php)", 'usuarios', (int)$c['id']);
                $r[] = ['id' => (int)$c['id'], 'email' => (string)$c['email'], 'rol' => (string)$c['rol'], 'temporal' => $temporal];
            }
            return $r;
        });
    }

    private function claveConocida(string $hash): ?string {
        foreach ($this->candidatas as $c) {
            if (password_verify($c, $hash)) {
                return $c;
            }
        }
        return null;
    }
}
