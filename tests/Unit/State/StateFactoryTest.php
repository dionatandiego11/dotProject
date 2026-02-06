<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\State;

use DotProject\State\Etapa\ConcluidaComAtrasoState;
use DotProject\State\Etapa\EmAndamentoState;
use DotProject\State\Etapa\NaoIniciadaState;
use DotProject\State\Projeto\AguardandoInicioState;
use DotProject\State\Projeto\CanceladoState;
use DotProject\State\StateFactory;
use PHPUnit\Framework\TestCase;

class StateFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        StateFactory::clearCache();
    }

    public function testCreateEtapaStateNormalizesSnakeCase(): void
    {
        $state = StateFactory::createEtapaState('Nao_Iniciada');

        $this->assertInstanceOf(NaoIniciadaState::class, $state);
        $this->assertSame('Nao_Iniciada', $state->getName());
    }

    public function testCreateEtapaStateSupportsConcluidaComAtraso(): void
    {
        $state = StateFactory::createEtapaState('Concluida_Com_Atraso');

        $this->assertInstanceOf(ConcluidaComAtrasoState::class, $state);
        $this->assertSame('Concluida_Com_Atraso', $state->getName());
    }

    public function testCreateProjetoStateSupportsLegacyUnderscoreName(): void
    {
        $state = StateFactory::createProjetoState('Aguardando_Inicio');

        $this->assertInstanceOf(AguardandoInicioState::class, $state);
        $this->assertSame('Aguardando_Inicio', $state->getName());
    }

    public function testCreateProjetoStateSupportsCancelado(): void
    {
        $state = StateFactory::createProjetoState('Cancelado');

        $this->assertInstanceOf(CanceladoState::class, $state);
        $this->assertSame('Cancelado', $state->getName());
    }

    public function testCreateStateCachesByClassName(): void
    {
        $first = StateFactory::createEtapaState('Em_Andamento');
        $second = StateFactory::createEtapaState('Em_Andamento');

        $this->assertInstanceOf(EmAndamentoState::class, $first);
        $this->assertSame($first, $second);
    }

    public function testCreateUnknownStateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        StateFactory::createEtapaState('Estado_Inexistente_123');
    }
}
