<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Entidade de Módulo - Representa um módulo do sistema
 * 
 * @package App\Entity
 */
class ModuleEntity
{
    private ?int $id = null;
    private string $name = '';
    private string $directory = '';
    private string $version = '';
    private string $setupClass = '';
    private string $type = '';
    private bool $active = false;
    private string $uiName = '';
    private string $uiIcon = '';
    private int $uiOrder = 0;
    private bool $uiActive = false;
    private string $description = '';
    private ?string $permissionsItemTable = null;
    private ?string $permissionsItemField = null;
    private ?string $permissionsItemLabel = null;
    
    /**
     * Criar entidade a partir de array
     */
    public static function fromArray(array $data): self
    {
        $entity = new self();
        
        if (isset($data['mod_id'])) {
            $entity->id = (int) $data['mod_id'];
        }
        
        $entity->name = $data['mod_name'] ?? '';
        $entity->directory = $data['mod_directory'] ?? '';
        $entity->version = $data['mod_version'] ?? '';
        $entity->setupClass = $data['mod_setup_class'] ?? '';
        $entity->type = $data['mod_type'] ?? '';
        $entity->active = (bool) ($data['mod_active'] ?? 0);
        $entity->uiName = $data['mod_ui_name'] ?? '';
        $entity->uiIcon = $data['mod_ui_icon'] ?? '';
        $entity->uiOrder = (int) ($data['mod_ui_order'] ?? 0);
        $entity->uiActive = (bool) ($data['mod_ui_active'] ?? 0);
        $entity->description = $data['mod_description'] ?? '';
        $entity->permissionsItemTable = $data['permissions_item_table'] ?? null;
        $entity->permissionsItemField = $data['permissions_item_field'] ?? null;
        $entity->permissionsItemLabel = $data['permissions_item_label'] ?? null;
        
        return $entity;
    }
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDirectory(): string { return $this->directory; }
    public function getVersion(): string { return $this->version; }
    public function getSetupClass(): string { return $this->setupClass; }
    public function getType(): string { return $this->type; }
    public function isActive(): bool { return $this->active; }
    public function getUiName(): string { return $this->uiName; }
    public function getUiIcon(): string { return $this->uiIcon; }
    public function getUiOrder(): int { return $this->uiOrder; }
    public function isUiActive(): bool { return $this->uiActive; }
    public function getDescription(): string { return $this->description; }
    public function getPermissionsItemTable(): ?string { return $this->permissionsItemTable; }
    public function getPermissionsItemField(): ?string { return $this->permissionsItemField; }
    public function getPermissionsItemLabel(): ?string { return $this->permissionsItemLabel; }
    
    // Setters
    public function setName(string $name): void { $this->name = $name; }
    public function setDirectory(string $directory): void { $this->directory = $directory; }
    public function setVersion(string $version): void { $this->version = $version; }
    public function setSetupClass(string $setupClass): void { $this->setupClass = $setupClass; }
    public function setType(string $type): void { $this->type = $type; }
    public function setActive(bool $active): void { $this->active = $active; }
    public function setUiName(string $uiName): void { $this->uiName = $uiName; }
    public function setUiIcon(string $uiIcon): void { $this->uiIcon = $uiIcon; }
    public function setUiOrder(int $uiOrder): void { $this->uiOrder = $uiOrder; }
    public function setUiActive(bool $uiActive): void { $this->uiActive = $uiActive; }
    public function setDescription(string $description): void { $this->description = $description; }
    public function setPermissionsItemTable(?string $table): void { $this->permissionsItemTable = $table; }
    public function setPermissionsItemField(?string $field): void { $this->permissionsItemField = $field; }
    public function setPermissionsItemLabel(?string $label): void { $this->permissionsItemLabel = $label; }
    
    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'directory' => $this->directory,
            'version' => $this->version,
            'setup_class' => $this->setupClass,
            'type' => $this->type,
            'active' => $this->active,
            'ui_name' => $this->uiName,
            'ui_icon' => $this->uiIcon,
            'ui_order' => $this->uiOrder,
            'ui_active' => $this->uiActive,
            'description' => $this->description,
            'permissions_item_table' => $this->permissionsItemTable,
            'permissions_item_field' => $this->permissionsItemField,
            'permissions_item_label' => $this->permissionsItemLabel,
            'is_core' => $this->isCore(),
            'is_user' => $this->isUserModule(),
        ];
    }
    
    /**
     * Verificar se é módulo core
     */
    public function isCore(): bool
    {
        return $this->type === 'core';
    }
    
    /**
     * Verificar se é módulo de usuário
     */
    public function isUserModule(): bool
    {
        return $this->type === 'user';
    }
    
    /**
     * Verificar se está visível no menu
     */
    public function isMenuVisible(): bool
    {
        return $this->active && $this->uiActive;
    }
    
    /**
     * Obter URL do ícone
     */
    public function getIconUrl(): string
    {
        if (empty($this->uiIcon)) {
            return '/images/icons/module.png';
        }
        return '/images/icons/' . $this->uiIcon;
    }
    
    /**
     * Obter URL do módulo
     */
    public function getUrl(): string
    {
        return '/index.php?m=' . $this->directory;
    }
    
    /**
     * Verificar se requer setup
     */
    public function requiresSetup(): bool
    {
        return !empty($this->setupClass);
    }
    
    /**
     * Comparar versões
     */
    public function compareVersion(string $otherVersion): int
    {
        return version_compare($this->version, $otherVersion);
    }
}
