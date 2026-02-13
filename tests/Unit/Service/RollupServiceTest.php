<?php
/**
 * Unit Tests for RollupService
 *
 * Tests the weighted rollup calculation logic with mocked Database and TenantContext.
 */

declare(strict_types=1);

namespace DotProject\Tests\Unit\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;
use DotProject\Service\RollupService;
use PHPUnit\Framework\TestCase;

class RollupServiceTest extends TestCase
{
    private $db;
    private $tenant;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Database::class);
        $this->tenant = $this->createMock(TenantContext::class);
        $this->tenant->method('getTenantId')->willReturn(null); // no tenant filter
    }

    private function makeService(): RollupService
    {
        return new RollupService($this->db, $this->tenant);
    }

    // =====================================================================
    // calcularPercentEtapa
    // =====================================================================

    public function testCalcEtapaWithWeightedTasks(): void
    {
        // 3 tarefas: 50%×2, 80%×3, 100%×5 => (100+240+500)/10 = 84.0
        $this->db->method('fetchOne')->willReturn([
            'soma_ponderada' => 840.0,
            'soma_pesos' => 10.0,
        ]);

        $result = $this->makeService()->calcularPercentEtapa(1);
        $this->assertEquals(84.0, $result);
    }

    public function testCalcEtapaWithZeroWeightsReturnsZero(): void
    {
        $this->db->method('fetchOne')->willReturn([
            'soma_ponderada' => 0,
            'soma_pesos' => 0,
        ]);

        $result = $this->makeService()->calcularPercentEtapa(1);
        $this->assertEquals(0.0, $result);
    }

    // =====================================================================
    // calcularPercentProjeto
    // =====================================================================

    public function testCalcProjetoWithWeightedEtapas(): void
    {
        // 2 etapas: 60%×1, 40%×1 => 100/2 = 50.0
        $this->db->method('fetchOne')->willReturn([
            'soma_ponderada' => 100.0,
            'soma_pesos' => 2.0,
        ]);

        $result = $this->makeService()->calcularPercentProjeto(1);
        $this->assertEquals(50.0, $result);
    }

    // =====================================================================
    // calcularPercentAcao
    // =====================================================================

    public function testCalcAcaoWithBridgeWeights(): void
    {
        // 2 projetos via ponte: 70%×0.6, 30%×0.4 => (42+12)/1 = 54.0
        $this->db->method('fetchOne')->willReturn([
            'soma_ponderada' => 54.0,
            'soma_pesos' => 1.0,
        ]);

        $result = $this->makeService()->calcularPercentAcao(1);
        $this->assertEquals(54.0, $result);
    }

    // =====================================================================
    // calcularPercentPrograma
    // =====================================================================

    public function testCalcProgramaWithOrcamento(): void
    {
        // 2 ações: 80%×100k, 40%×200k => (8000000+8000000)/300k ≈ 53.33
        $this->db->method('fetchOne')->willReturn([
            'soma_ponderada_orcam' => 16000000.0,
            'soma_orcam' => 300000.0,
            'soma_simples' => 120.0,
            'total' => 2,
        ]);

        $result = $this->makeService()->calcularPercentPrograma(1);
        $this->assertEquals(53.33, $result);
    }

    public function testCalcProgramaFallbackToSimpleAverage(): void
    {
        // orcamento = 0 → fallback → soma_simples / total = 150/3 = 50.0
        $this->db->method('fetchOne')->willReturn([
            'soma_ponderada_orcam' => 0.0,
            'soma_orcam' => 0.0,
            'soma_simples' => 150.0,
            'total' => 3,
        ]);

        $result = $this->makeService()->calcularPercentPrograma(1);
        $this->assertEquals(50.0, $result);
    }

    public function testCalcProgramaNoAcoesReturnsZero(): void
    {
        $this->db->method('fetchOne')->willReturn([
            'soma_ponderada_orcam' => 0.0,
            'soma_orcam' => 0.0,
            'soma_simples' => 0.0,
            'total' => 0,
        ]);

        $result = $this->makeService()->calcularPercentPrograma(1);
        $this->assertEquals(0.0, $result);
    }

    // =====================================================================
    // calcularPercentPpa
    // =====================================================================

    public function testCalcPpaWithWeightedProgramas(): void
    {
        // 2 programas: 80%×500k, 60%×300k => (400k+180k)/800k = 72.5
        $this->db->method('fetchAll')->willReturn([
            ['programa_id' => 1, 'percent_execucao' => 80, 'valor_orcamentario_total' => 500000],
            ['programa_id' => 2, 'percent_execucao' => 60, 'valor_orcamentario_total' => 300000],
        ]);

        $result = $this->makeService()->calcularPercentPpa(1);
        $this->assertEquals(72.5, $result);
    }

    public function testCalcPpaFallbackToSimpleAverage(): void
    {
        // orcamento = 0 → fallback → (80+60)/2 = 70.0
        $this->db->method('fetchAll')->willReturn([
            ['programa_id' => 1, 'percent_execucao' => 80, 'valor_orcamentario_total' => 0],
            ['programa_id' => 2, 'percent_execucao' => 60, 'valor_orcamentario_total' => 0],
        ]);

        $result = $this->makeService()->calcularPercentPpa(1);
        $this->assertEquals(70.0, $result);
    }

    public function testCalcPpaEmptyReturnsZero(): void
    {
        $this->db->method('fetchAll')->willReturn([]);

        $result = $this->makeService()->calcularPercentPpa(1);
        $this->assertEquals(0.0, $result);
    }

    // =====================================================================
    // recalcularValorExecutadoAcao
    // =====================================================================

    public function testRecalcularValorExecutadoAcao(): void
    {
        $this->db->method('fetchOne')->willReturn(['total' => 15000.50]);
        $this->db->expects($this->once())
            ->method('execute')
            ->with(
                'UPDATE dotp_acoes SET valor_executado = :valor WHERE id = :id',
                ['valor' => 15000.50, 'id' => 42]
            );

        $result = $this->makeService()->recalcularValorExecutadoAcao(42);
        $this->assertEquals(15000.50, $result);
    }
}
