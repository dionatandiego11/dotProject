<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Core\Cache;
use DotProject\Entity\UnidadeOrganizacionalEntity;
use DotProject\Repository\UnidadeOrganizacionalRepository;
use PHPUnit\Framework\TestCase;

class UnidadeOrganizacionalRepositoryIntegrationTest extends TestCase
{
    private UnidadeOrganizacionalRepository $repository;

    protected function setUp(): void
    {
        (new Cache())->clear();
        $this->repository = new UnidadeOrganizacionalRepository();
    }

    public function testFindArvoreReturnsRequestedNodeWhenRaizIsNotGlobalRoot(): void
    {
        $roots = $this->repository->findArvore();
        $nodes = $this->flatten($roots);

        $nonRoot = null;
        foreach ($nodes as $node) {
            if ($node->getPaiId() !== null && $node->getId() !== null) {
                $nonRoot = $node;
                break;
            }
        }

        if ($nonRoot === null || $nonRoot->getId() === null) {
            $this->markTestSkipped('Nenhuma unidade não-raiz encontrada na base para validar a subárvore.');
        }

        $targetId = (int) $nonRoot->getId();
        $subtree = $this->repository->findArvore($targetId);

        $this->assertNotEmpty($subtree);
        $this->assertSame($targetId, $subtree[0]->getId());
    }

    /**
     * @param UnidadeOrganizacionalEntity[] $roots
     * @return UnidadeOrganizacionalEntity[]
     */
    private function flatten(array $roots): array
    {
        $all = [];
        $stack = array_values($roots);

        while (!empty($stack)) {
            $node = array_pop($stack);
            if (!$node instanceof UnidadeOrganizacionalEntity) {
                continue;
            }

            $all[] = $node;
            foreach ($node->getFilhas() as $child) {
                $stack[] = $child;
            }
        }

        return $all;
    }
}
