<?php
/**
 * Servico de Notificacoes
 * 
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DateTime;
use DotProject\Core\Logger;
use DotProject\Entity\EtapaEntity;
use DotProject\Entity\Notification;
use DotProject\Entity\ProjetoEntity;
use DotProject\Repository\NotificationRepository;

class NotificationService
{
    private Logger $logger;
    private NotificationRepository $repository;

    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->repository = new NotificationRepository();
    }

    /**
     * Lista notificacoes do usuario
     *
     * @return array<Notification>
     */
    public function getUserNotifications(int $userId, bool $unreadOnly = false, int $limit = 50): array
    {
        return $this->repository->findByUser($userId, $unreadOnly, $limit);
    }

    /**
     * Conta notificacoes nao lidas
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->repository->countUnread($userId);
    }

    /**
     * Marca notificacao como lida
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = $this->repository->find($notificationId);
        if (!$notification instanceof Notification) {
            return false;
        }
        if ($notification->getUserId() !== $userId) {
            return false;
        }

        $notification->markAsRead();
        return $this->repository->save($notification);
    }

    /**
     * Marca todas as notificacoes como lidas
     */
    public function markAllAsRead(int $userId): bool
    {
        return $this->repository->markAllAsRead($userId);
    }

    /**
     * Cria notificacao simples
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $data = null
    ): ?Notification {
        if ($userId <= 0) {
            return null;
        }

        $notification = new Notification();
        $notification
            ->setUserId($userId)
            ->setType($type)
            ->setTitle($title)
            ->setMessage($message)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setData($data)
            ->setCreatedAt(new DateTime());

        if (!$this->repository->save($notification)) {
            $this->logger->error('Falha ao salvar notificacao', [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
            ]);
            return null;
        }

        return $notification;
    }

    /**
     * Cria notificacao baseada em template
     */
    public function createFromTemplate(
        int $userId,
        string $type,
        array $variables,
        ?string $entityType = null,
        ?int $entityId = null
    ): ?Notification {
        $templates = $this->getTemplates();
        $template = $templates[$type] ?? [
            'title' => 'Notificacao',
            'message' => 'Voce recebeu uma nova notificacao.',
        ];

        $title = $this->applyTemplate($template['title'], $variables);
        $message = $this->applyTemplate($template['message'], $variables);

        return $this->create($userId, $type, $title, $message, $entityType, $entityId, $variables);
    }

    /**
     * Notifica atraso de projeto
     */
    public function notificarAtrasoProjeto(ProjetoEntity $projeto, int $diasAtraso): void
    {
        $coordenadorId = $projeto->getCoordenadorId();
        $nome = $projeto->getNome();

        $mensagem = "Projeto '{$nome}' esta atrasado ha {$diasAtraso} dias. " .
                   "Etapa atual: {$projeto->getEtapaAtual()->getNome()}";

        $this->enviarNotificacao($coordenadorId, $mensagem, 'alerta');

        // Notifica secretario tambem
        $programa = $projeto->getPrograma();
        if ($programa) {
            // Aqui buscariamos o secretario da unidade
            // $this->enviarNotificacao($secretarioId, $mensagem, 'alerta');
        }

        $this->logger->warning("Notificacao de atraso enviada: {$mensagem}");
    }

    /**
     * Notifica etapa proxima do prazo (7 dias)
     */
    public function notificarProximoPrazo(EtapaEntity $etapa): void
    {
        $projeto = $etapa->getProjeto();
        if (!$projeto) {
            return;
        }

        $dataFim = $etapa->getDataPrevistaFim()?->format('d/m/Y');
        $mensagem = "Etapa '{$etapa->getNome()}' do projeto '{$projeto->getNome()}' " .
                   "vence em {$dataFim} (7 dias ou menos).";

        $this->enviarNotificacao($projeto->getCoordenadorId(), $mensagem, 'aviso');
    }

    /**
     * Notifica etapa atrasada
     */
    public function notificarAtraso(EtapaEntity $etapa, ?int $diasAtraso): void
    {
        $projeto = $etapa->getProjeto();
        if (!$projeto) {
            return;
        }

        $mensagem = "Etapa '{$etapa->getNome()}' do projeto '{$projeto->getNome()}' " .
                   "esta atrasada ha {$diasAtraso} dias. " .
                   "Justificativa obrigatoria ao concluir.";

        $this->enviarNotificacao($projeto->getCoordenadorId(), $mensagem, 'alerta');
    }

    /**
     * Notifica estado critico (> 30 dias atraso)
     */
    public function notificarCritico(EtapaEntity $etapa): void
    {
        $projeto = $etapa->getProjeto();
        if (!$projeto) {
            return;
        }

        $dias = $etapa->getDiasAtraso();
        $mensagem = "CRITICO: Etapa '{$etapa->getNome()}' do projeto '{$projeto->getNome()}' " .
                   "esta atrasada ha {$dias} dias. Acao imediata necessaria!";

        // Notifica coordenador
        $this->enviarNotificacao($projeto->getCoordenadorId(), $mensagem, 'critico');

        // Notifica secretario
        // $this->enviarNotificacao($secretarioId, $mensagem, 'critico');

        // Notifica controlador
        // $this->enviarNotificacao($controladorId, $mensagem, 'critico');
    }

    /**
     * Notifica conclusao de projeto
     */
    public function notificarProjetoConcluido(ProjetoEntity $projeto): void
    {
        $mensagem = "Projeto '{$projeto->getNome()}' foi concluido com sucesso!";

        $this->enviarNotificacao($projeto->getCoordenadorId(), $mensagem, 'sucesso');

        // Notifica secretario
        $programa = $projeto->getPrograma();
        if ($programa) {
            // $this->enviarNotificacao($secretarioId, $mensagem, 'sucesso');
        }
    }

    /**
     * Notifica que todas tarefas de etapa foram concluidas
     */
    public function notificarTarefasEtapaConcluidas(EtapaEntity $etapa): void
    {
        $projeto = $etapa->getProjeto();
        if (!$projeto) {
            return;
        }

        $mensagem = "Todas as tarefas da etapa '{$etapa->getNome()}' do projeto " .
                   "'{$projeto->getNome()}' foram concluidas. " .
                   "Voce pode finalizar a etapa.";

        $this->enviarNotificacao($projeto->getCoordenadorId(), $mensagem, 'info');
    }

    /**
     * Metodo base para envio de notificacao
     *
     * @todo Implementar integracao real (email, push, etc)
     */
    private function enviarNotificacao(int $userId, string $mensagem, string $tipo = 'info'): void
    {
        // Aqui seria implementado:
        // - Salvar na tabela de notificacoes
        // - Enviar email
        // - Enviar push notification
        // - etc

        $this->logger->info("Notificacao [{$tipo}] para usuario {$userId}: {$mensagem}");

        // Exemplo de insert na tabela de notificacoes:
        // $sql = "INSERT INTO notificacoes (user_id, mensagem, tipo, lida, data_criacao)
        //         VALUES (?, ?, ?, 0, NOW())";
        // $this->db->execute($sql, [$userId, $mensagem, $tipo]);
    }

    /**
     * Templates basicos por tipo
     *
     * @return array<string, array{title: string, message: string}>
     */
    private function getTemplates(): array
    {
        return [
            Notification::TYPE_PROJECT_STATUS => [
                'title' => 'Status do projeto atualizado',
                'message' => 'O projeto "{project_name}" agora esta {new_status}.',
            ],
            Notification::TYPE_TASK_ASSIGNED => [
                'title' => 'Nova tarefa atribuida',
                'message' => 'Voce recebeu a tarefa "{task_name}" em "{project_name}".',
            ],
            Notification::TYPE_TASK_COMPLETED => [
                'title' => 'Tarefa concluida',
                'message' => '{completed_by} concluiu a tarefa "{task_name}".',
            ],
            Notification::TYPE_TASK_OVERDUE => [
                'title' => 'Tarefa atrasada',
                'message' => 'A tarefa "{task_name}" venceu em {due_date}.',
            ],
            Notification::TYPE_SYSTEM => [
                'title' => 'Notificacao do sistema',
                'message' => 'Voce recebeu uma notificacao do sistema.',
            ],
        ];
    }

    /**
     * Substitui variaveis em strings de template
     */
    private function applyTemplate(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }
        return strtr($template, $replacements);
    }
}
