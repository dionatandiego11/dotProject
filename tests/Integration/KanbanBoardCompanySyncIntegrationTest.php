<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Api\Controller\KanbanController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Cache;
use DotProject\Core\Database;
use PHPUnit\Framework\TestCase;

class KanbanBoardCompanySyncIntegrationTest extends TestCase
{
    private Database $db;
    private ?int $unidadeId = null;
    private ?int $projectId = null;
    private ?int $boardId = null;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
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
                $this->db->execute(sprintf(
                    'DELETE FROM `%s` WHERE kanban_task_column_id IN (%s)',
                    $this->db->table('kanban_tasks'),
                    $ids
                ));
            }

            $this->db->delete('kanban_columns', sprintf('column_board_id = %d', $boardId));
            $this->db->delete('kanban_boards', sprintf('board_id = %d', $boardId));
            $this->boardId = null;
        }

        if ($this->projectId !== null) {
            $this->db->delete('projects', sprintf('project_id = %d', (int) $this->projectId));
            $this->projectId = null;
        }

        if ($this->unidadeId !== null) {
            $unidadeId = (int) $this->unidadeId;
            $this->db->delete('companies', sprintf('company_id = %d', $unidadeId));
            $this->db->delete('unidades_organizacionais', sprintf('unidade_id = %d', $unidadeId));
            $this->unidadeId = null;
        }

        (new Cache())->clear();
    }

    public function testCreateBoardAutoCreatesCompanyMirrorForUnidade(): void
    {
        $rootId = (int) ($this->db->fetchValue(
            "SELECT unidade_id
             FROM dotp_unidades_organizacionais
             WHERE unidade_status = 'ativo'
               AND unidade_nivel_id = 1
             ORDER BY unidade_id
             LIMIT 1"
        ) ?? 0);

        if ($rootId <= 0) {
            $this->markTestSkipped('Base sem unidade raiz para criar unidade de teste.');
        }

        $unidadeNome = 'IT Unidade Sync ' . bin2hex(random_bytes(3));
        $unidadeId = $this->db->insert('unidades_organizacionais', [
            'unidade_nivel_id' => 2,
            'unidade_pai_id' => $rootId,
            'unidade_nome' => $unidadeNome,
            'unidade_sigla' => 'ITSYNC',
            'unidade_status' => 'ativo',
            'unidade_pode_criar_projetos' => 1,
            'unidade_pode_criar_programas' => 0,
        ]);
        $this->assertNotFalse($unidadeId, 'Falha ao criar unidade temporaria para teste');
        $this->unidadeId = (int) $unidadeId;

        // Simula cenário legado: unidade existe, company correspondente ainda não.
        $this->db->delete('companies', sprintf('company_id = %d', (int) $unidadeId));

        $projectId = $this->db->insert('projects', [
            'project_company' => (int) $unidadeId,
            'project_company_internal' => 0,
            'project_department' => 0,
            'project_name' => 'Projeto Sync Kanban',
            'project_short_name' => 'ITSYNC',
            'project_owner' => 1,
            'project_creator' => 1,
            'project_status' => 1,
            'project_percent_complete' => 0,
            'project_color_identifier' => '#4A90D9',
            'project_priority' => 1,
            'project_type' => 0,
            'project_start_date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertNotFalse($projectId, 'Falha ao criar projeto temporario para teste');
        $this->projectId = (int) $projectId;

        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn([
            'name' => 'Board Sync Test',
            'project_id' => (int) $projectId,
            'unidade_id' => (int) $unidadeId,
        ]);
        $request->method('getParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $key === '_user_id' ? 1 : $default
            );

        $capturedPayload = null;
        $capturedStatus = null;
        $response = $this->getMockBuilder(Response::class)
            ->onlyMethods(['json', 'error', 'send'])
            ->getMock();

        $response->method('json')
            ->willReturnCallback(function (mixed $data, int $status = 200) use (&$capturedPayload, &$capturedStatus, $response) {
                $capturedPayload = $data;
                $capturedStatus = $status;
                return $response;
            });
        $response->method('error')
            ->willReturnCallback(function (string $message, int $status = 400) use (&$capturedPayload, &$capturedStatus, $response) {
                $capturedPayload = ['error' => true, 'message' => $message];
                $capturedStatus = $status;
                return $response;
            });
        $response->method('send')->willReturnCallback(static function (): void {
        });

        $controller = new KanbanController($request, $response);
        $controller->createBoard();

        $this->assertSame(201, $capturedStatus, 'Criacao de board deveria funcionar para unidade sem company previa.');
        $this->assertIsArray($capturedPayload);
        $this->assertTrue((bool) ($capturedPayload['success'] ?? false));

        $this->boardId = (int) ($capturedPayload['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $this->boardId);

        $companyRow = $this->db->fetchOne(sprintf(
            'SELECT company_id, company_name FROM `%s` WHERE company_id = %d',
            $this->db->table('companies'),
            (int) $unidadeId
        ));
        $this->assertNotNull($companyRow, 'Company espelho da unidade deveria ser criada automaticamente.');
        $this->assertSame((int) $unidadeId, (int) ($companyRow['company_id'] ?? 0));
        $this->assertSame($unidadeNome, (string) ($companyRow['company_name'] ?? ''));

        $boardRow = $this->db->fetchOne(sprintf(
            'SELECT board_company FROM `%s` WHERE board_id = %d',
            $this->db->table('kanban_boards'),
            $this->boardId
        ));
        $this->assertNotNull($boardRow);
        $this->assertSame((int) $unidadeId, (int) ($boardRow['board_company'] ?? 0));
    }
}
