<?php
declare(strict_types=1);

namespace Core\Interfaces;

interface UsuarioRepositoryInterface {
    /**
     * Obtiene todos los usuarios.
     * 
     * @return array
     */
    public function getAll(): array;

    /**
     * Elimina un usuario por su ID.
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Busca un usuario por su ID.
     * 
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array;

    /**
     * Crea un nuevo usuario.
     * 
     * @param array $data
     * @return bool
     */
    public function create(array $data): bool;

    /**
     * Crea múltiples usuarios de forma masiva.
     * 
     * @param array $usersData
     * @return int Número de registros insertados
     */
    public function createMultiple(array $usersData): int;

    /**
     * Actualiza un usuario existente.
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool;
}
