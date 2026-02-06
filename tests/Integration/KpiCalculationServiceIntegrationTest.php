<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Core\Database;
use DotProject\Service\KpiCalculationService;
use PHPUnit\Framework\TestCase;

class KpiCalculationServiceIntegrationTest extends TestCase
{
    private Database $db;
    private KpiCalculationService $service;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->service = new KpiCalculationService();
    }

    public function testGetDashboardSecretarioHandlesVinculoStatusSchema(): void
    {
        $statusColumn = $this->resolveVinculoStatusColumn();
        $activeValue = $statusColumn === 'vinculo_status' ? 'ativo' : 1;

        $userId = $this->db->fetchValue(
            "SELECT vinculo_user_id
             FROM dotp_usuario_unidades
             WHERE {$statusColumn} = ?
             ORDER BY vinculo_user_id
             LIMIT 1",
            [$activeValue]
        );

        if ($userId === null) {
            $this->markTestSkipped('Base sem usuario com vinculo ativo para validar dashboard secretario.');
        }

        $data = $this->service->getDashboardSecretario((int) $userId);
        $this->assertIsArray($data);
    }

    public function testGetDashboardCoordenadorDoesNotDependOnLegacyTaskColumns(): void
    {
        $userId = (int) ($this->db->fetchValue(
            "SELECT project_coordenador_id
             FROM dotp_projects
             WHERE project_coordenador_id IS NOT NULL
               AND project_coordenador_id <> 0
             ORDER BY project_coordenador_id
             LIMIT 1"
        ) ?? 1);

        $data = $this->service->getDashboardCoordenador($userId);
        $this->assertIsArray($data);
    }

    public function testGetDashboardTecnicoWorksWithoutTaskAssignedToColumn(): void
    {
        $userId = 1;
        $data = $this->service->getDashboardTecnico($userId);
        $this->assertIsArray($data);
    }

    public function testGetEstatisticasTarefasUsesCompatibleTaskSchema(): void
    {
        $projectId = (int) ($this->db->fetchValue(
            "SELECT project_id
             FROM dotp_projects
             ORDER BY project_id
             LIMIT 1"
        ) ?? 1);

        $stats = $this->service->getEstatisticasTarefas($projectId);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('concluidas', $stats);
        $this->assertArrayHasKey('em_andamento', $stats);
        $this->assertArrayHasKey('a_fazer', $stats);
        $this->assertArrayHasKey('bloqueadas', $stats);
    }

    private function resolveVinculoStatusColumn(): string
    {
        $hasStatus = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_usuario_unidades'
               AND column_name = 'vinculo_status'"
        ) ?? 0);

        return $hasStatus > 0 ? 'vinculo_status' : 'vinculo_ativo';
    }
}
