<?php
/**
 * File Controller
 * 
 * Controller para API de upload e gerenciamento de arquivos.
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Dto\ApiResponse;
use DotProject\Service\FileService;

/**
 * Controller de Arquivos
 */
class FileController extends BaseController
{
    private FileService $fileService;
    
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->fileService = new FileService();
    }
    
    /**
     * POST /v1/tasks/:id/files
     * Upload de arquivo para uma tarefa
     */
    public function upload(int $taskId): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // Verifica se a tarefa existe
            if (!$this->fileService->taskExists($taskId)) {
                $this->response->json(ApiResponse::notFound('Tarefa não encontrada')->toArray(), 404)->send();
                return;
            }
            
            // Verifica permissão
            if (!$this->fileService->canAccessTask($taskId, $userId)) {
                $this->response->error('Acesso negado', 403)->send();
                return;
            }
            
            // Processa upload
            $file = $this->request->getUploadedFile('file');
            
            if (!$file) {
                $this->response->json(ApiResponse::error('Nenhum arquivo enviado')->toArray(), 400)->send();
                return;
            }
            
            // Validações
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) {
                $this->response->json(ApiResponse::error('Arquivo muito grande. Máximo 10MB')->toArray(), 400)->send();
                return;
            }
            
            // Tipos permitidos
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf',
                'text/plain', 'text/csv',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip', 'application/x-zip-compressed'
            ];
            
            if (!in_array($file['type'], $allowedTypes)) {
                $this->response->json(ApiResponse::error('Tipo de arquivo não permitido')->toArray(), 400)->send();
                return;
            }
            
            $uploadedFile = $this->fileService->upload($file, $taskId, $userId);
            
            $this->response->json(ApiResponse::success(
                $uploadedFile,
                'Arquivo enviado com sucesso'
            )->toArray(), 201)->send();
            
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * GET /v1/tasks/:id/files
     * Lista arquivos de uma tarefa
     */
    public function list(int $taskId): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            if (!$this->fileService->canAccessTask($taskId, $userId)) {
                $this->response->error('Acesso negado', 403)->send();
                return;
            }
            
            $files = $this->fileService->getTaskFiles($taskId);
            
            $this->response->json(ApiResponse::success(['files' => $files])->toArray())->send();
            
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * DELETE /v1/files/:id
     * Remove um arquivo
     */
    public function delete(int $fileId): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            if (!$this->fileService->canDeleteFile($fileId, $userId)) {
                $this->response->error('Acesso negado', 403)->send();
                return;
            }
            
            $this->fileService->delete($fileId);
            
            $this->response->json(ApiResponse::success(null, 'Arquivo removido')->toArray())->send();
            
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * GET /v1/files/:id/download
     * Download de arquivo
     */
    public function download(int $fileId): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $file = $this->fileService->getFile($fileId);
            
            if (!$file) {
                $this->response->json(ApiResponse::notFound('Arquivo não encontrado')->toArray(), 404)->send();
                return;
            }
            
            if (!$this->fileService->canAccessTask($file['file_task_id'], $userId)) {
                $this->response->error('Acesso negado', 403)->send();
                return;
            }
            
            $filePath = $this->fileService->getFilePath($file);
            
            if (!file_exists($filePath)) {
                $this->response->json(ApiResponse::error('Arquivo não encontrado no servidor')->toArray(), 404)->send();
                return;
            }
            
            // Headers para download
            header('Content-Type: ' . $file['file_mime_type']);
            header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
            header('Content-Length: ' . $file['file_size']);
            header('Cache-Control: no-cache');
            
            readfile($filePath);
            exit;
            
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    private function getCurrentUserId(): ?int
    {
        return $this->request->getParam('_user_id');
    }
}
