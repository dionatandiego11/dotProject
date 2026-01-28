<?php
/**
 * User Event
 * 
 * Evento de dominio para usuarios.
 * 
 * @package DotProject\Event
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Event;

use DotProject\Core\Event;
use DotProject\Entity\UserEntity;

/**
 * Evento relacionado a usuarios
 */
class UserEvent extends Event
{
    private ?UserEntity $user;
    private ?int $userId;
    private ?array $changedFields;
    private ?string $username;
    
    public function __construct(
        string $name, 
        ?UserEntity $user = null, 
        ?int $userId = null,
        ?string $username = null,
        ?array $changedFields = null
    ) {
        parent::__construct($name);
        $this->user = $user;
        $this->userId = $userId ?? ($user ? $user->getId() : null);
        $this->username = $username ?? ($user ? $user->getUsername() : null);
        $this->changedFields = $changedFields;
        
        $this->setData([
            'user_id' => $this->userId,
            'username' => $this->username,
            'changed_fields' => $changedFields,
            'timestamp' => time(),
        ]);
    }
    
    public function getUser(): ?UserEntity
    {
        return $this->user;
    }
    
    public function getUserId(): ?int
    {
        return $this->userId;
    }
    
    public function getUsername(): ?string
    {
        return $this->username;
    }
    
    public function getChangedFields(): ?array
    {
        return $this->changedFields;
    }
    
    public function wasChanged(string $field): bool
    {
        if ($this->changedFields === null) {
            return false;
        }
        return in_array($field, $this->changedFields, true);
    }
}
