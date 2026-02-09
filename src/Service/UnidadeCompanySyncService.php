<?php
/**
 * Sincroniza unidade organizacional com company legada.
 *
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;

class UnidadeCompanySyncService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function ensureCompanyForUnidade(int $unidadeId, ?string $unidadeNome = null): bool
    {
        if ($unidadeId <= 0) {
            return false;
        }

        $nome = trim((string) ($unidadeNome ?? ''));
        if ($nome === '') {
            $nome = $this->resolveUnidadeNome($unidadeId);
        }

        if ($nome === '') {
            return false;
        }

        $companiesTable = $this->db->table('companies');
        $existing = $this->db->fetchOne(
            sprintf(
                "SELECT company_id, company_name
                 FROM `%s`
                 WHERE company_id = ?
                 LIMIT 1",
                $companiesTable
            ),
            [$unidadeId]
        );

        if ($existing === null) {
            $inserted = $this->db->insert('companies', [
                'company_id' => $unidadeId,
                'company_module' => 0,
                'company_name' => $nome,
                'company_owner' => 0,
                'company_type' => 0,
            ]);

            return $inserted !== false;
        }

        $currentName = trim((string) ($existing['company_name'] ?? ''));
        $isLegacyPlaceholder = preg_match('/^Empresa\s+\d+$/i', $currentName) === 1;
        $mustUpdateName = $currentName === '' || $isLegacyPlaceholder || $currentName !== $nome;

        if ($mustUpdateName) {
            return $this->db->update('companies', ['company_name' => $nome], 'company_id = ' . (int) $unidadeId);
        }

        return true;
    }

    private function resolveUnidadeNome(int $unidadeId): string
    {
        $unidadesTable = $this->db->table('unidades_organizacionais');
        $nome = $this->db->fetchValue(
            sprintf(
                "SELECT unidade_nome
                 FROM `%s`
                 WHERE unidade_id = ?
                 LIMIT 1",
                $unidadesTable
            ),
            [$unidadeId]
        );

        return trim((string) ($nome ?? ''));
    }
}
