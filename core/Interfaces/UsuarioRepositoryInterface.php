<?php
declare(strict_types=1);

namespace Core\Interfaces;

/**
 * Contrato del repositorio de usuarios.
 *
 * Existe para que UsuariosService dependa de una abstracción y no de la
 * clase concreta (inversión de dependencias): las pruebas pueden sustituir
 * el acceso a datos sin tocar la regla de negocio.
 */
interface UsuarioRepositoryInterface {
    public function listar(array $filtros, int $limite, int $offset): array;
    public function contar(array $filtros): int;
    public function findById(int $id): ?array;
    public function existeEmail(string $email, ?int $exceptoId = null): bool;
    public function crear(array $datos, string $hash, bool $debeCambiar): int;
    public function actualizar(int $id, array $datos): void;
    public function cambiarEstado(int $id, string $estado): void;
    public function fijarPassword(int $id, string $hash, bool $debeCambiar): void;
    public function contarCoordinadoresActivos(?int $excepto = null): int;
    /** @return array{insertados: list<array>, omitidos: list<string>} */
    public function importar(array $filas): array;
}
