<?php
/**
 * Serviço de auditoria para trilhas de alterações relevantes.
 *
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;

class AuditService
{
    private Database $db;
    private ?bool $auditTableExists = null;
    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Registra alteração de valor_executado em qualquer entidade rastreada.
     *
     * @param array<string, mixed> $contexto
     */
    public function logValorExecutadoChange(
        string $entidadeTipo,
        int $entidadeId,
        ?float $valorAnterior,
        ?float $valorNovo,
        ?int $usuarioId = null,
        string $origem = 'system',
        array $contexto = []
    ): void {
        if ($entidadeId <= 0 || !$this->hasAuditTable()) {
            return;
        }

        $anterior = $this->normalizeDecimal($valorAnterior);
        $novo = $this->normalizeDecimal($valorNovo);

        if ($anterior === null && $novo === null) {
            return;
        }

        if ($anterior !== null && $novo !== null && abs($anterior - $novo) < 0.005) {
            return;
        }

        $payload = [
            'entidade_tipo' => mb_substr(trim($entidadeTipo) !== '' ? trim($entidadeTipo) : 'desconhecida', 0, 40),
            'entidade_id' => $entidadeId,
            'campo' => 'valor_executado',
            'valor_anterior' => $anterior,
            'valor_novo' => $novo,
            'usuario_id' => ($usuarioId !== null && $usuarioId > 0) ? $usuarioId : null,
            'origem' => mb_substr(trim($origem) !== '' ? trim($origem) : 'system', 0, 64),
            'contexto_json' => $contexto !== [] ? json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->tableHasColumn('dotp_audit_log', 'tenant_id') && TenantContext::isEnabled()) {
            $tenantId = TenantContext::getTenantId();
            if ($tenantId !== null && $tenantId > 0) {
                $payload['tenant_id'] = $tenantId;
            }
        }

        try {
            $this->db->insert('audit_log', $payload);
        } catch (\Throwable) {
            // Fail-open: falha de auditoria não pode impedir operação de negócio.
        }
    }

    private function normalizeDecimal(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return round($value, 2);
    }

    private function hasAuditTable(): bool
    {
        if ($this->auditTableExists !== null) {
            return $this->auditTableExists;
        }

        $count = (int) ($this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            ['dotp_audit_log']
        ) ?? 0);

        $this->auditTableExists = $count > 0;
        return $this->auditTableExists;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $key = $table . ':' . $column;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }

        $count = (int) ($this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        ) ?? 0);

        $this->columnExistsCache[$key] = $count > 0;
        return $this->columnExistsCache[$key];
    }
}
