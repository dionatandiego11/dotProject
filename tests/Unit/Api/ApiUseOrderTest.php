<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;

class ApiUseOrderTest extends TestCase
{
    public function testApiPhpDeclaresUseBeforeInstantiatingClasses(): void
    {
        $apiPath = dirname(__DIR__, 3) . '/api.php';
        $lines = file($apiPath, FILE_IGNORE_NEW_LINES);

        $this->assertIsArray($lines);
        $this->assertNotEmpty($lines);

        /** @var array<string, int> $useDeclarationLines */
        $useDeclarationLines = [];

        foreach ($lines as $idx => $line) {
            if (!preg_match('/^\s*use\s+([^;]+);/', (string) $line, $matches)) {
                continue;
            }

            $declaration = trim((string) $matches[1]);
            if ($declaration === '') {
                continue;
            }

            $alias = null;
            if (preg_match('/\bas\s+([A-Za-z_][A-Za-z0-9_]*)\s*$/i', $declaration, $aliasMatch)) {
                $alias = (string) $aliasMatch[1];
            } else {
                $parts = explode('\\', $declaration);
                $alias = (string) end($parts);
            }

            if ($alias !== '' && !isset($useDeclarationLines[$alias])) {
                $useDeclarationLines[$alias] = $idx + 1;
            }
        }

        $violations = [];

        foreach ($lines as $idx => $line) {
            $lineNumber = $idx + 1;
            if (!preg_match_all('/\bnew\s+([A-Z][A-Za-z0-9_]*)\s*\(/', (string) $line, $matches)) {
                continue;
            }

            foreach ($matches[1] as $className) {
                $name = (string) $className;
                if (!isset($useDeclarationLines[$name])) {
                    $violations[] = sprintf(
                        'Instanciação de "%s" na linha %d sem use correspondente',
                        $name,
                        $lineNumber
                    );
                    continue;
                }

                if ($useDeclarationLines[$name] > $lineNumber) {
                    $violations[] = sprintf(
                        'Instanciação de "%s" na linha %d antes do use na linha %d',
                        $name,
                        $lineNumber,
                        $useDeclarationLines[$name]
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Ordem de imports inválida em api.php: ' . implode(' | ', $violations)
        );
    }
}
