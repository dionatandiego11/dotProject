<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;

class ApiRoutesUniquenessTest extends TestCase
{
    public function testApiPhpDoesNotRegisterDuplicateMethodPathRoutes(): void
    {
        $apiPath = dirname(__DIR__, 3) . '/api.php';
        $contents = file_get_contents($apiPath);

        $this->assertIsString($contents);
        $this->assertNotSame('', $contents);

        preg_match_all(
            '/\$router->(get|post|put|delete)\(\s*[\'"]([^\'"]+)[\'"]\s*,/i',
            $contents,
            $matches,
            PREG_SET_ORDER
        );

        $this->assertNotEmpty($matches, 'Nenhuma rota encontrada em api.php.');

        $seen = [];
        $duplicates = [];

        foreach ($matches as $match) {
            $method = strtoupper((string) $match[1]);
            $path = '/' . trim((string) $match[2], '/');
            $key = $method . ' ' . $path;

            if (isset($seen[$key])) {
                $duplicates[] = $key;
                continue;
            }

            $seen[$key] = true;
        }

        $this->assertSame(
            [],
            $duplicates,
            'Rotas duplicadas detectadas em api.php: ' . implode(', ', $duplicates)
        );
    }
}

