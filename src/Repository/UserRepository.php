<?php
/**
 * Repository User
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\UserEntity;
use DateTime;

class UserRepository extends BaseRepository
{
    protected string $table = 'dotp_users';
    protected string $primaryKey = 'user_id';

    protected function hydrate(array $data): UserEntity
    {
        $entity = new UserEntity();
        $entity->setId((int) $data['user_id']);
        $entity->setUsername($data['user_username']);
        $entity->setPassword($data['user_password']);
        $entity->setContactId($data['user_contact'] ? (int) $data['user_contact'] : null);
        $entity->setCompanyId($data['user_company'] ? (int) $data['user_company'] : null);
        $entity->setDepartmentId($data['user_department'] ? (int) $data['user_department'] : null);
        $entity->setStatus((int) ($data['user_status'] ?? 0));
        
        // Dados do contato (se existirem)
        if (isset($data['contact_first_name'])) {
            $entity->setFirstName($data['contact_first_name']);
        }
        if (isset($data['contact_last_name'])) {
            $entity->setLastName($data['contact_last_name']);
        }
        if (isset($data['contact_email'])) {
            $entity->setEmail($data['contact_email']);
        }
        if (isset($data['contact_phone'])) {
            $entity->setPhone($data['contact_phone']);
        }
        
        if (!empty($data['user_last_login'])) {
            $entity->setLastLogin(new DateTime($data['user_last_login']));
        }

        return $entity;
    }

    protected function extract(object $entity): array
    {
        if (!$entity instanceof UserEntity) {
            throw new \InvalidArgumentException('Entity must be UserEntity');
        }

        return [
            'user_id' => $entity->getId(),
            'user_username' => $entity->getUsername(),
            'user_password' => $entity->getPassword(),
            'user_contact' => $entity->getContactId(),
            'user_company' => $entity->getCompanyId(),
            'user_department' => $entity->getDepartmentId(),
            'user_status' => $entity->getStatus(),
        ];
    }

    public function save(object $entity): bool
    {
        if (!$entity instanceof UserEntity) {
            throw new \InvalidArgumentException('Entity must be UserEntity');
        }

        $data = $this->extract($entity);
        
        if ($entity->getId() === null) {
            unset($data['user_id']);
            $result = $this->db->insert($this->table, $data);
            if ($result) {
                $entity->setId((int) $this->db->lastInsertId());
            }
        } else {
            $id = $data['user_id'];
            unset($data['user_id']);
            $result = $this->db->update($this->table, $data, "user_id = {$id}");
        }

        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Busca usuário por username (com dados do contato)
     */
    public function findByUsername(string $username): ?UserEntity
    {
        $cacheKey = $this->cacheKey("username:{$username}");
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT u.*, c.contact_first_name, c.contact_last_name, c.contact_email, c.contact_phone
                FROM {$this->table} u
                LEFT JOIN dotp_contacts c ON c.contact_id = u.user_contact
                WHERE u.user_username = ?
                LIMIT 1";
        
        $data = $this->db->fetchOne($sql, [$username]);
        if ($data === null) {
            return null;
        }

        $entity = $this->hydrate($data);
        $this->cache->set($cacheKey, $entity, $this->cacheTtl);
        return $entity;
    }

    /**
     * Busca usuário por email
     */
    public function findByEmail(string $email): ?UserEntity
    {
        $sql = "SELECT u.*, c.contact_first_name, c.contact_last_name, c.contact_email
                FROM {$this->table} u
                INNER JOIN dotp_contacts c ON c.contact_id = u.user_contact
                WHERE c.contact_email = ?
                LIMIT 1";
        
        $data = $this->db->fetchOne($sql, [$email]);
        return $data ? $this->hydrate($data) : null;
    }

    /**
     * Busca usuários ativos
     * @return array<UserEntity>
     */
    public function findActive(): array
    {
        $sql = "SELECT u.*, c.contact_first_name, c.contact_last_name, c.contact_email
                FROM {$this->table} u
                LEFT JOIN dotp_contacts c ON c.contact_id = u.user_contact
                WHERE u.user_status = 0
                ORDER BY c.contact_first_name";
        
        $results = $this->db->fetchAll($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Busca usuários por empresa
     * @return array<UserEntity>
     */
    public function findByCompany(int $companyId): array
    {
        return $this->findBy(['user_company' => $companyId, 'user_status' => 0]);
    }

    /**
     * Atualiza último login
     */
    public function updateLastLogin(int $userId): bool
    {
        $result = $this->db->update(
            $this->table,
            ['user_last_login' => date('Y-m-d H:i:s')],
            "user_id = {$userId}"
        );
        
        if ($result) {
            $this->cache->delete($this->cacheKey("find:{$userId}"));
        }
        
        return $result;
    }
}
