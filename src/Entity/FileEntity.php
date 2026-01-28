<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;

/**
 * Entidade de Arquivo - Representa um arquivo no sistema dotProject
 * 
 * @package App\Entity
 */
class FileEntity
{
    private ?int $id = null;
    private string $realFilename;
    private ?int $folderId = null;
    private ?int $projectId = null;
    private ?int $taskId = null;
    private string $name;
    private ?int $parentId = null;
    private ?string $description = null;
    private ?string $type = null;
    private ?int $ownerId = null;
    private ?DateTimeImmutable $date = null;
    private int $size = 0;
    private float $version = 0;
    private string $icon = 'obj/';
    private ?int $category = null;
    private string $checkout = '';
    private ?string $checkoutReason = null;
    private int $versionId = 0;
    
    // Relacionamentos (lazy loaded)
    private ?ProjectEntity $project = null;
    private ?TaskEntity $task = null;
    private ?UserEntity $owner = null;
    
    /**
     * Criar entidade a partir de array de dados
     */
    public static function fromArray(array $data): self
    {
        $entity = new self();
        
        if (isset($data['file_id'])) {
            $entity->id = (int) $data['file_id'];
        }
        
        $entity->realFilename = $data['file_real_filename'] ?? '';
        $entity->folderId = isset($data['file_folder']) ? (int) $data['file_folder'] : null;
        $entity->projectId = isset($data['file_project']) ? (int) $data['file_project'] : null;
        $entity->taskId = isset($data['file_task']) ? (int) $data['file_task'] : null;
        $entity->name = $data['file_name'] ?? '';
        $entity->parentId = isset($data['file_parent']) ? (int) $data['file_parent'] : null;
        $entity->description = $data['file_description'] ?? null;
        $entity->type = $data['file_type'] ?? null;
        $entity->ownerId = isset($data['file_owner']) ? (int) $data['file_owner'] : null;
        $entity->size = (int) ($data['file_size'] ?? 0);
        $entity->version = (float) ($data['file_version'] ?? 0);
        $entity->icon = $data['file_icon'] ?? 'obj/';
        $entity->category = isset($data['file_category']) ? (int) $data['file_category'] : null;
        $entity->checkout = $data['file_checkout'] ?? '';
        $entity->checkoutReason = $data['file_co_reason'] ?? null;
        $entity->versionId = (int) ($data['file_version_id'] ?? 0);
        
        if (!empty($data['file_date'])) {
            try {
                $entity->date = new DateTimeImmutable($data['file_date']);
            } catch (\Exception $e) {
                $entity->date = null;
            }
        }
        
        return $entity;
    }
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getRealFilename(): string { return $this->realFilename; }
    public function getFolderId(): ?int { return $this->folderId; }
    public function getProjectId(): ?int { return $this->projectId; }
    public function getTaskId(): ?int { return $this->taskId; }
    public function getName(): string { return $this->name; }
    public function getParentId(): ?int { return $this->parentId; }
    public function getDescription(): ?string { return $this->description; }
    public function getType(): ?string { return $this->type; }
    public function getOwnerId(): ?int { return $this->ownerId; }
    public function getDate(): ?DateTimeImmutable { return $this->date; }
    public function getSize(): int { return $this->size; }
    public function getVersion(): float { return $this->version; }
    public function getIcon(): string { return $this->icon; }
    public function getCategory(): ?int { return $this->category; }
    public function getCheckout(): string { return $this->checkout; }
    public function getCheckoutReason(): ?string { return $this->checkoutReason; }
    public function getVersionId(): int { return $this->versionId; }
    public function getProject(): ?ProjectEntity { return $this->project; }
    public function getTask(): ?TaskEntity { return $this->task; }
    public function getOwner(): ?UserEntity { return $this->owner; }
    
    // Setters
    public function setRealFilename(string $filename): void { $this->realFilename = $filename; }
    public function setFolderId(?int $folderId): void { $this->folderId = $folderId; }
    public function setProjectId(?int $projectId): void { $this->projectId = $projectId; }
    public function setTaskId(?int $taskId): void { $this->taskId = $taskId; }
    public function setName(string $name): void { $this->name = $name; }
    public function setParentId(?int $parentId): void { $this->parentId = $parentId; }
    public function setDescription(?string $description): void { $this->description = $description; }
    public function setType(?string $type): void { $this->type = $type; }
    public function setOwnerId(?int $ownerId): void { $this->ownerId = $ownerId; }
    public function setDate(?DateTimeImmutable $date): void { $this->date = $date; }
    public function setSize(int $size): void { $this->size = $size; }
    public function setVersion(float $version): void { $this->version = $version; }
    public function setIcon(string $icon): void { $this->icon = $icon; }
    public function setCategory(?int $category): void { $this->category = $category; }
    public function setCheckout(string $checkout): void { $this->checkout = $checkout; }
    public function setCheckoutReason(?string $reason): void { $this->checkoutReason = $reason; }
    public function setVersionId(int $versionId): void { $this->versionId = $versionId; }
    public function setProject(?ProjectEntity $project): void { $this->project = $project; }
    public function setTask(?TaskEntity $task): void { $this->task = $task; }
    public function setOwner(?UserEntity $owner): void { $this->owner = $owner; }
    
    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'real_filename' => $this->realFilename,
            'folder_id' => $this->folderId,
            'project_id' => $this->projectId,
            'task_id' => $this->taskId,
            'name' => $this->name,
            'parent_id' => $this->parentId,
            'description' => $this->description,
            'type' => $this->type,
            'owner_id' => $this->ownerId,
            'date' => $this->date?->format('Y-m-d H:i:s'),
            'size' => $this->size,
            'size_formatted' => $this->getFormattedSize(),
            'version' => $this->version,
            'icon' => $this->icon,
            'category' => $this->category,
            'checkout' => $this->checkout,
            'checkout_reason' => $this->checkoutReason,
            'version_id' => $this->versionId,
            'is_checked_out' => $this->isCheckedOut(),
            'is_image' => $this->isImage(),
            'extension' => $this->getExtension(),
        ];
    }
    
    /**
     * Formatar tamanho do arquivo
     */
    public function getFormattedSize(): string
    {
        $size = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        
        return round($size, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * Verificar se arquivo está bloqueado (checkout)
     */
    public function isCheckedOut(): bool
    {
        return !empty($this->checkout);
    }
    
    /**
     * Verificar se é uma imagem
     */
    public function isImage(): bool
    {
        if (empty($this->type)) {
            return false;
        }
        return str_starts_with($this->type, 'image/');
    }
    
    /**
     * Obter extensão do arquivo
     */
    public function getExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }
    
    /**
     * Verificar se pertence a um projeto
     */
    public function belongsToProject(): bool
    {
        return $this->projectId !== null && $this->projectId > 0;
    }
    
    /**
     * Verificar se pertence a uma tarefa
     */
    public function belongsToTask(): bool
    {
        return $this->taskId !== null && $this->taskId > 0;
    }
    
    /**
     * Verificar se está em uma pasta
     */
    public function isInFolder(): bool
    {
        return $this->folderId !== null && $this->folderId > 0;
    }
    
    /**
     * Obter URL de download
     */
    public function getDownloadUrl(): string
    {
        return '/api/files/' . $this->id . '/download';
    }
    
    /**
     * Obter caminho físico do arquivo
     */
    public function getFilePath(): string
    {
        $basePath = dirname(__DIR__, 2) . '/files';
        return $basePath . '/' . $this->realFilename;
    }
    
    /**
     * Verificar se arquivo existe fisicamente
     */
    public function exists(): bool
    {
        return file_exists($this->getFilePath());
    }
}
