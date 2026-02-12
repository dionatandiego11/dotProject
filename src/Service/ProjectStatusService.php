<?php
/**
 * Project Status Service
 *
 * Encapsulates project status transition rules, status labels,
 * and status/percent normalization.  Extracted from ProjectController.
 *
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

/**
 * Project Status Service
 *
 * Pure business-logic service — no HTTP dependency.
 */
class ProjectStatusService
{
    /** @var array<int, string> */
    private const STATUS_LABELS = [
        0 => 'Nao definido',
        1 => 'Proposto',
        2 => 'Em planejamento',
        3 => 'Em progresso',
        4 => 'Em espera',
        5 => 'Completo',
        6 => 'Arquivado',
    ];

    /**
     * Allowed transitions per status.
     *
     * @var array<int, int[]>
     */
    private const TRANSITIONS = [
        0 => [1, 2, 3, 4],
        1 => [0, 2, 3, 4],
        2 => [0, 1, 3, 4],
        3 => [4, 5],
        4 => [3, 5],
        5 => [3, 6],
        6 => [],
    ];

    public function getLabel(int $status): string
    {
        return self::STATUS_LABELS[$status] ?? 'Desconhecido';
    }

    /**
     * @return array<int, string>
     */
    public function getAllLabels(): array
    {
        return self::STATUS_LABELS;
    }

    /**
     * @return int[]
     */
    public function getAllowedTransitions(int $currentStatus): array
    {
        return self::TRANSITIONS[$currentStatus] ?? [0, 1, 2, 3, 4, 5, 6];
    }

    public function isAllowedTransition(int $currentStatus, int $targetStatus): bool
    {
        if ($currentStatus === $targetStatus) {
            return true;
        }

        return in_array($targetStatus, $this->getAllowedTransitions($currentStatus), true);
    }

    /**
     * Normalize status/percent_complete coherence.
     *
     * Rules:
     *  - Status 5 (Completo) ⇒ percent = 100
     *  - Status 0 (Não definido) ⇒ percent = 0
     *  - percent always clamped 0–100
     *
     * @param array<string, mixed> $payload  Modified in-place
     */
    public function normalizeStatusPercent(array &$payload, ?int $currentStatus, ?int $currentPercent): void
    {
        $hasStatus = array_key_exists('status', $payload)
            && $payload['status'] !== null
            && $payload['status'] !== '';
        $hasPercent = array_key_exists('percent_complete', $payload)
            && $payload['percent_complete'] !== null
            && $payload['percent_complete'] !== '';

        if (!$hasStatus && !$hasPercent) {
            return;
        }

        $status = $hasStatus ? (int) $payload['status'] : (int) ($currentStatus ?? 0);
        $percent = $hasPercent ? (int) $payload['percent_complete'] : (int) ($currentPercent ?? 0);
        $percent = max(0, min(100, $percent));

        if ($status === 5) {
            $percent = 100;
        } elseif ($status === 0) {
            $percent = 0;
        }

        if ($hasStatus) {
            $payload['status'] = $status;
        }
        if ($hasPercent || in_array($status, [0, 5], true)) {
            $payload['percent_complete'] = $percent;
        }
    }

    /**
     * Build a descriptive validation-error message for an invalid transition.
     *
     * @return array<string, string>  Keyed by 'status'.
     */
    public function transitionErrorPayload(int $currentStatus, int $targetStatus): array
    {
        $allowed = $this->getAllowedTransitions($currentStatus);
        $allowedLabels = array_map(
            fn(int $s): string => $this->getLabel($s),
            $allowed
        );

        return [
            'status' => sprintf(
                'Transicao de status invalida de "%s" para "%s". Permitidos: %s.',
                $this->getLabel($currentStatus),
                $this->getLabel($targetStatus),
                empty($allowedLabels) ? 'nenhum' : implode(', ', $allowedLabels)
            ),
        ];
    }
}
