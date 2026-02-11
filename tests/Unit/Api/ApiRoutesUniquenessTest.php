<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;

class ApiRoutesUniquenessTest extends TestCase
{
    public function testApiRouteFilesDoNotRegisterDuplicateMethodPathRoutes(): void
    {
        $apiPath = dirname(__DIR__, 3) . '/api.php';
        $apiContents = file_get_contents($apiPath);

        $this->assertIsString($apiContents);
        $this->assertNotSame('', $apiContents);

        preg_match_all(
            '/[\'"](api_routes_[^\'"]+\.php)[\'"]/',
            $apiContents,
            $routeFileMatches
        );

        $routeFiles = array_values(array_unique($routeFileMatches[1] ?? []));
        $this->assertNotEmpty($routeFiles, 'Nenhum arquivo de rota encontrado em api.php.');

        $seen = [];
        $duplicates = [];

        foreach ($routeFiles as $routeFile) {
            $routeFilePath = dirname($apiPath) . '/' . $routeFile;
            $this->assertFileExists($routeFilePath, "Arquivo de rota ausente: {$routeFile}");

            $contents = file_get_contents($routeFilePath);
            $this->assertIsString($contents);

            preg_match_all(
                '/\$router->(get|post|put|delete)\(\s*([\'"])(.*?)\2\s*,/i',
                $contents,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $method = strtoupper((string) $match[1]);
                $path = '/' . trim((string) $match[3], '/');
                $key = $method . ' ' . $path;

                if (isset($seen[$key])) {
                    $duplicates[] = $key;
                    continue;
                }

                $seen[$key] = true;
            }
        }

        $this->assertNotEmpty($seen, 'Nenhuma rota encontrada nos arquivos api_routes_*.php.');

        $this->assertSame(
            [],
            $duplicates,
            'Rotas duplicadas detectadas nos arquivos de rota: ' . implode(', ', $duplicates)
        );
    }
}
