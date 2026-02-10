<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Service\AnalyticsService;
use DotProject\Service\KanbanService;
use DotProject\Service\UnidadeCompanySyncService;
use PHPUnit\Framework\TestCase;

class KanbanFlowIntegrationTest extends TestCase
{
    private Database $db;
    private KanbanService $service;
    private ?int $projectId = null;
    private ?int $boardId = null;
    private ?int $taskId = null;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->service = new KanbanService($this->db);
        (new Cache())->clear();
    }

    protected function tearDown(): void
    {
        if ($this->boardId !== null) {
            $boardId = (int) $this->boardId;
            $columnRows = $this->db->fetchAll(sprintf(
                'SELECT column_id FROM `%s` WHERE column_board_id = %d',
                $this->db->table('kanban_columns'),
                $boardId
            ));

            $columnIds = array_map(static fn(array $r): int => (int) ($r['column_id'] ?? 0), $columnRows);
            if (!empty($columnIds)) {
                $ids = implode(',', $columnIds);
                $this->db->execute(
                    sprintf('DELETE FROM `%s` WHERE kanban_task_column_id IN (%s)', $this->db->table('kanban_tasks'), $ids)
                );
            }

            $this->db->delete('kanban_columns', sprintf('column_board_id = %d', $boardId));
            $this->db->delete('kanban_boards', sprintf('board_id = %d', $boardId));
            $this->boardId = null;
        }

        if ($this->taskId !== null) {
            $this->db->delete('tasks', sprintf('task_id = %d', (int) $this->taskId));
            $this->taskId = null;
        }

        if ($this->projectId !== null) {
            $this->db->delete('projects', sprintf('project_id = %d', (int) $this->projectId));
            $this->projectId = null;
        }

        (new Cache())->clear();
    }

    public function testTaskMovesThroughDefaultKanbanColumns(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade ativa para teste de fluxo Kanban.');
        }

        $synced = (new UnidadeCompanySyncService($this->db))
            ->ensureCompanyForUnidade((int) $unidade['id'], (string) $unidade['nome']);
        $this->assertTrue($synced, 'Falha ao sincronizar unidade/company para teste de fluxo Kanban.');

        $projectId = $this->db->insert('projects', [
            'project_company' => (int) $unidade['id'],
            'project_company_internal' => 0,
            'project_department' => 0,
            'project_name' => 'IT Kanban Flow ' . bin2hex(random_bytes(3)),
            'project_short_name' => 'ITFLOW',
            'project_owner' => 1,
            'project_creator' => 1,
            'project_status' => 1,
            'project_percent_complete' => 0,
            'project_color_identifier' => '#4A90D9',
            'project_priority' => 1,
            'project_type' => 0,
            'project_start_date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertNotFalse($projectId, 'Falha ao criar projeto para fluxo Kanban.');
        $this->projectId = (int) $projectId;

        $board = $this->service->createBoard([
            'name' => 'Kanban Flow Board',
            'project_id' => (int) $projectId,
            'company_id' => (int) $unidade['id'],
        ], 1);
        $this->boardId = $board->getId();
        $this->assertNotNull($this->boardId);

        $taskId = $this->db->insert('tasks', [
            'task_name' => 'Fluxo Backlog-ToDo-InProgress-Done',
            'task_project' => (int) $projectId,
            'task_owner' => 1,
            'task_creator' => 1,
            'task_start_date' => date('Y-m-d H:i:s'),
            'task_end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'task_status' => 0,
            'task_priority' => 1,
            'task_percent_complete' => 0,
            'task_description' => 'Teste de fluxo Kanban',
        ]);
        $this->assertNotFalse($taskId, 'Falha ao criar tarefa para fluxo Kanban.');
        $this->taskId = (int) $taskId;

        $boardData = $this->service->getBoard((int) $this->boardId, 1);
        $this->assertIsArray($boardData);
        $columns = $boardData['columns'] ?? [];
        $this->assertNotEmpty($columns);

        $backlogColumnId = $this->findColumnId($columns, 'Backlog');
        $todoColumnId = $this->findColumnId($columns, 'To Do');
        $inProgressColumnId = $this->findColumnId($columns, 'In Progress');
        $doneColumnId = $this->findColumnId($columns, 'Done');

        $this->assertNotNull($backlogColumnId);
        $this->assertNotNull($todoColumnId);
        $this->assertNotNull($inProgressColumnId);
        $this->assertNotNull($doneColumnId);

        $kanbanTask = $this->findKanbanTaskByTaskId($columns, (int) $taskId);
        $this->assertNotNull($kanbanTask, 'Tarefa deveria ser sincronizada automaticamente ao abrir board.');
        $this->assertSame($backlogColumnId, (int) ($kanbanTask['column_id'] ?? 0));
        $taskState = $this->taskState((int) $taskId);
        $projectState = $this->projectState((int) $projectId);
        $this->assertSame(0, (int) ($taskState['task_status'] ?? -1));
        $this->assertSame(0, (int) ($taskState['task_percent_complete'] ?? -1));
        $this->assertSame(1, (int) ($projectState['project_status'] ?? -1));
        $this->assertSame(0, (int) ($projectState['project_percent_complete'] ?? -1));

        $this->assertTrue($this->service->moveTask((int) $kanbanTask['id'], (int) $todoColumnId, 0, 1));
        $columns = ($this->service->getBoard((int) $this->boardId, 1)['columns'] ?? []);
        $kanbanTask = $this->findKanbanTaskByTaskId($columns, (int) $taskId);
        $this->assertNotNull($kanbanTask);
        $this->assertSame($todoColumnId, (int) ($kanbanTask['column_id'] ?? 0));
        $taskState = $this->taskState((int) $taskId);
        $projectState = $this->projectState((int) $projectId);
        $this->assertSame(1, (int) ($taskState['task_status'] ?? -1));
        $this->assertSame(0, (int) ($taskState['task_percent_complete'] ?? -1));
        $this->assertSame(3, (int) ($projectState['project_status'] ?? -1));
        $this->assertSame(0, (int) ($projectState['project_percent_complete'] ?? -1));

        $this->assertTrue($this->service->moveTask((int) $kanbanTask['id'], (int) $inProgressColumnId, 0, 1));
        $columns = ($this->service->getBoard((int) $this->boardId, 1)['columns'] ?? []);
        $kanbanTask = $this->findKanbanTaskByTaskId($columns, (int) $taskId);
        $this->assertNotNull($kanbanTask);
        $this->assertSame($inProgressColumnId, (int) ($kanbanTask['column_id'] ?? 0));
        $taskState = $this->taskState((int) $taskId);
        $projectState = $this->projectState((int) $projectId);
        $this->assertSame(2, (int) ($taskState['task_status'] ?? -1));
        $this->assertSame(50, (int) ($taskState['task_percent_complete'] ?? -1));
        $this->assertSame(3, (int) ($projectState['project_status'] ?? -1));
        $this->assertSame(50, (int) ($projectState['project_percent_complete'] ?? -1));

        $this->assertTrue($this->service->moveTask((int) $kanbanTask['id'], (int) $doneColumnId, 0, 1));
        $columns = ($this->service->getBoard((int) $this->boardId, 1)['columns'] ?? []);
        $kanbanTask = $this->findKanbanTaskByTaskId($columns, (int) $taskId);
        $this->assertNotNull($kanbanTask);
        $this->assertSame($doneColumnId, (int) ($kanbanTask['column_id'] ?? 0));
        $taskState = $this->taskState((int) $taskId);
        $projectState = $this->projectState((int) $projectId);
        $this->assertSame(3, (int) ($taskState['task_status'] ?? -1));
        $this->assertSame(100, (int) ($taskState['task_percent_complete'] ?? -1));
        $this->assertSame(5, (int) ($projectState['project_status'] ?? -1));
        $this->assertSame(100, (int) ($projectState['project_percent_complete'] ?? -1));
    }

    public function testMoveToDoneInvalidatesAnalyticsDashboardCache(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade ativa para teste de sincronizacao Kanban -> Dashboard.');
        }

        $synced = (new UnidadeCompanySyncService($this->db))
            ->ensureCompanyForUnidade((int) $unidade['id'], (string) $unidade['nome']);
        $this->assertTrue($synced, 'Falha ao sincronizar unidade/company para teste Kanban -> Dashboard.');

        $projectId = $this->db->insert('projects', [
            'project_company' => (int) $unidade['id'],
            'project_company_internal' => 0,
            'project_department' => 0,
            'project_name' => 'IT Kanban Analytics ' . bin2hex(random_bytes(3)),
            'project_short_name' => 'ITKANBAN',
            'project_owner' => 1,
            'project_creator' => 1,
            'project_status' => 1,
            'project_percent_complete' => 0,
            'project_color_identifier' => '#3B82F6',
            'project_priority' => 1,
            'project_type' => 0,
            'project_start_date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertNotFalse($projectId, 'Falha ao criar projeto para teste Kanban -> Dashboard.');
        $this->projectId = (int) $projectId;

        $board = $this->service->createBoard([
            'name' => 'Kanban Analytics Board',
            'project_id' => (int) $projectId,
            'company_id' => (int) $unidade['id'],
        ], 1);
        $this->boardId = $board->getId();
        $this->assertNotNull($this->boardId);

        $taskId = $this->db->insert('tasks', [
            'task_name' => 'Validar cache dashboard analytics',
            'task_project' => (int) $projectId,
            'task_owner' => 1,
            'task_creator' => 1,
            'task_start_date' => date('Y-m-d H:i:s'),
            'task_end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'task_status' => 0,
            'task_priority' => 1,
            'task_percent_complete' => 0,
            'task_description' => 'Nao concluida no baseline',
        ]);
        $this->assertNotFalse($taskId, 'Falha ao criar tarefa para teste Kanban -> Dashboard.');
        $this->taskId = (int) $taskId;

        $boardData = $this->service->getBoard((int) $this->boardId, 1);
        $columns = $boardData['columns'] ?? [];
        $this->assertNotEmpty($columns);

        $doneColumnId = $this->findColumnId($columns, 'Done');
        $this->assertNotNull($doneColumnId);

        $kanbanTask = $this->findKanbanTaskByTaskId($columns, (int) $taskId);
        $this->assertNotNull($kanbanTask, 'Tarefa deveria ser sincronizada automaticamente ao abrir board.');

        $taskState = $this->taskState((int) $taskId);
        $this->assertSame(0, (int) ($taskState['task_percent_complete'] ?? -1));

        $analyticsBeforeMove = new AnalyticsService($this->db);
        $baseline = $analyticsBeforeMove->getDashboardSummary(1);
        $baselineCompleted = (int) (($baseline['tasks']['completed'] ?? 0));

        // Segunda leitura sem mudancas para garantir cache aquecido.
        $cachedBaseline = $analyticsBeforeMove->getDashboardSummary(1);
        $this->assertSame($baselineCompleted, (int) (($cachedBaseline['tasks']['completed'] ?? 0)));

        $this->assertTrue($this->service->moveTask((int) $kanbanTask['id'], (int) $doneColumnId, 0, 1));

        $taskState = $this->taskState((int) $taskId);
        $this->assertSame(3, (int) ($taskState['task_status'] ?? -1));
        $this->assertSame(100, (int) ($taskState['task_percent_complete'] ?? -1));

        // Nova instancia para simular novo request HTTP (sem cache em memoria L1 do request anterior).
        $analyticsAfterMove = new AnalyticsService($this->db);
        $after = $analyticsAfterMove->getDashboardSummary(1);
        $afterCompleted = (int) (($after['tasks']['completed'] ?? 0));
        $this->assertSame(
            $baselineCompleted + 1,
            $afterCompleted,
            'Dashboard analytics deveria refletir imediatamente tarefa movida para Done.'
        );
    }

    /**
     * @return array{id:int,nome:string}|null
     */
    private function pickAnyUnidade(): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT unidade_id, unidade_nome
             FROM dotp_unidades_organizacionais
             WHERE unidade_status = 'ativo'
             ORDER BY unidade_id
             LIMIT 1"
        );

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['unidade_id'],
            'nome' => (string) $row['unidade_nome'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private function findColumnId(array $columns, string $name): ?int
    {
        foreach ($columns as $column) {
            if (strcasecmp((string) ($column['name'] ?? ''), $name) === 0) {
                return (int) ($column['id'] ?? 0);
            }
        }
        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array<string, mixed>|null
     */
    private function findKanbanTaskByTaskId(array $columns, int $taskId): ?array
    {
        foreach ($columns as $column) {
            $tasks = $column['tasks'] ?? [];
            if (!is_array($tasks)) {
                continue;
            }
            foreach ($tasks as $task) {
                if ((int) ($task['task_id'] ?? 0) === $taskId) {
                    return $task;
                }
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function taskState(int $taskId): array
    {
        $row = $this->db->fetchOne(
            sprintf(
                "SELECT task_status, task_percent_complete
                 FROM `%s`
                 WHERE task_id = ?
                 LIMIT 1",
                $this->db->table('tasks')
            ),
            [$taskId]
        );

        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectState(int $projectId): array
    {
        $row = $this->db->fetchOne(
            sprintf(
                "SELECT project_status, project_percent_complete
                 FROM `%s`
                 WHERE project_id = ?
                 LIMIT 1",
                $this->db->table('projects')
            ),
            [$projectId]
        );

        return is_array($row) ? $row : [];
    }
}
