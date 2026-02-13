<?php
/**
 * Unit Tests for SaudeCalculator
 *
 * Tests the health status calculation logic with mocked Database and TenantContext.
 * Covers: calcularSaudeProjeto, avaliarPrazo (via public API), piorStatus, piorEntreDois.
 */

declare(strict_types=1);

namespace DotProject\Tests\Unit\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;
use DotProject\Service\SaudeCalculator;
use PHPUnit\Framework\TestCase;

class SaudeCalculatorTest extends TestCase
{
    private $db;
    private $tenant;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Database::class);
        $this->tenant = $this->createMock(TenantContext::class);
        $this->tenant->method('getTenantId')->willReturn(null);
    }

    private function makeCalculator(): SaudeCalculator
    {
        return new SaudeCalculator($this->db, $this->tenant);
    }

    // =====================================================================
    // calcularSaudeProjeto — testa avaliarPrazo indiretamente
    // =====================================================================

    public function testProjetoSemDataFimEhEmDia(): void
    {
        // Sem bloqueados
        $this->db->method('fetchOne')->willReturn(['total' => 0]);

        $result = $this->makeCalculator()->calcularSaudeProjeto([
            'project_id' => 1,
            'project_end_date' => null,
            'project_percent_complete' => 50,
        ]);

        $this->assertEquals('em_dia', $result);
    }

    public function testProjeto100PorcentoEhEmDia(): void
    {
        // Mesmo com data vencida, 100% → em_dia
        $this->db->method('fetchOne')->willReturn(['total' => 0]);

        $ontem = (new \DateTime('yesterday'))->format('Y-m-d');
        $result = $this->makeCalculator()->calcularSaudeProjeto([
            'project_id' => 1,
            'project_end_date' => $ontem,
            'project_percent_complete' => 100,
        ]);

        $this->assertEquals('em_dia', $result);
    }

    public function testProjetoVencidoEhCritico(): void
    {
        // Data vencida e < 100%
        $this->db->method('fetchOne')->willReturn(['total' => 0]);

        $ontem = (new \DateTime('yesterday'))->format('Y-m-d');
        $result = $this->makeCalculator()->calcularSaudeProjeto([
            'project_id' => 1,
            'project_end_date' => $ontem,
            'project_percent_complete' => 50,
        ]);

        $this->assertEquals('critico', $result);
    }

    public function testProjetoProximoPrazoBaixoProgressoEhAtencao(): void
    {
        // Vence em 10 dias, só 40% → atenção
        $this->db->method('fetchOne')->willReturn(['total' => 0]);

        $em10dias = (new \DateTime('+10 days'))->format('Y-m-d');
        $result = $this->makeCalculator()->calcularSaudeProjeto([
            'project_id' => 1,
            'project_end_date' => $em10dias,
            'project_percent_complete' => 40,
        ]);

        $this->assertEquals('atencao', $result);
    }

    public function testProjetoProximoPrazoAltoProgressoEhEmDia(): void
    {
        // Vence em 10 dias, 85% → em_dia (acima do limiar de 80%)
        $this->db->method('fetchOne')->willReturn(['total' => 0]);

        $em10dias = (new \DateTime('+10 days'))->format('Y-m-d');
        $result = $this->makeCalculator()->calcularSaudeProjeto([
            'project_id' => 1,
            'project_end_date' => $em10dias,
            'project_percent_complete' => 85,
        ]);

        $this->assertEquals('em_dia', $result);
    }

    public function testProjetoComBloqueioEhImpedido(): void
    {
        // Tem tarefas bloqueadas → impedido (prioridade máxima)
        $this->db->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                ['total' => 1], // tarefas bloqueadas
                ['total' => 0], // etapas bloqueadas
            );

        $result = $this->makeCalculator()->calcularSaudeProjeto([
            'project_id' => 1,
            'project_end_date' => (new \DateTime('+30 days'))->format('Y-m-d'),
            'project_percent_complete' => 50,
        ]);

        $this->assertEquals('impedido', $result);
    }

    public function testProjetoPrazoDistanteEhEmDia(): void
    {
        // Vence em 60 dias → em_dia (fora do limiar de 15 dias)
        $this->db->method('fetchOne')->willReturn(['total' => 0]);

        $em60dias = (new \DateTime('+60 days'))->format('Y-m-d');
        $result = $this->makeCalculator()->calcularSaudeProjeto([
            'project_id' => 1,
            'project_end_date' => $em60dias,
            'project_percent_complete' => 30,
        ]);

        $this->assertEquals('em_dia', $result);
    }

    // =====================================================================
    // Constantes e STATUS_VALIDOS
    // =====================================================================

    public function testStatusValidosConstante(): void
    {
        $expected = ['em_dia', 'atencao', 'critico', 'impedido'];
        $this->assertEquals($expected, SaudeCalculator::STATUS_VALIDOS);
    }

    // =====================================================================
    // piorStatus e piorEntreDois (testados via Reflection)
    // =====================================================================

    public function testPiorStatusComListaVaziaRetornaEmDia(): void
    {
        $calc = $this->makeCalculator();
        $method = new \ReflectionMethod($calc, 'piorStatus');
        $method->setAccessible(true);

        $this->assertEquals('em_dia', $method->invoke($calc, []));
    }

    public function testPiorStatusRetornaMaisGrave(): void
    {
        $calc = $this->makeCalculator();
        $method = new \ReflectionMethod($calc, 'piorStatus');
        $method->setAccessible(true);

        $this->assertEquals('critico', $method->invoke($calc, ['em_dia', 'atencao', 'critico']));
        $this->assertEquals('impedido', $method->invoke($calc, ['impedido', 'em_dia']));
        $this->assertEquals('atencao', $method->invoke($calc, ['em_dia', 'atencao']));
        $this->assertEquals('em_dia', $method->invoke($calc, ['em_dia', 'em_dia']));
    }

    public function testPiorEntreDois(): void
    {
        $calc = $this->makeCalculator();
        $method = new \ReflectionMethod($calc, 'piorEntreDois');
        $method->setAccessible(true);

        $this->assertEquals('critico', $method->invoke($calc, 'critico', 'em_dia'));
        $this->assertEquals('impedido', $method->invoke($calc, 'atencao', 'impedido'));
        $this->assertEquals('atencao', $method->invoke($calc, 'atencao', 'em_dia'));
        $this->assertEquals('em_dia', $method->invoke($calc, 'em_dia', 'em_dia'));
    }

    // =====================================================================
    // avaliarPrazo (via Reflection)
    // =====================================================================

    public function testAvaliarPrazoComDataInvalidaRetornaEmDia(): void
    {
        $calc = $this->makeCalculator();
        $method = new \ReflectionMethod($calc, 'avaliarPrazo');
        $method->setAccessible(true);

        $this->assertEquals('em_dia', $method->invoke($calc, 'not-a-date', 50.0));
    }

    public function testAvaliarPrazoNullRetornaEmDia(): void
    {
        $calc = $this->makeCalculator();
        $method = new \ReflectionMethod($calc, 'avaliarPrazo');
        $method->setAccessible(true);

        $this->assertEquals('em_dia', $method->invoke($calc, null, 0.0));
        $this->assertEquals('em_dia', $method->invoke($calc, '', 0.0));
    }
}
