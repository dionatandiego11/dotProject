<?php
/**
 * Task Status Service
 *
 * Encapsulates task status validation and status/percent normalization.
 *
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

class TaskStatusService
{
    public const MIN_STATUS = 0;
    public const MAX_STATUS = 7;

    public function isValidStatus(int $status): bool
    {
        return $status >= self::MIN_STATUS && $status <= self::MAX_STATUS;
    }

    /**
     * Keep task status/percent coherence for canonical workflow statuses.
     *
     * @param array<string, mixed> $payload Modified in place.
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

        if ($status === 0) {
            $percent = 0;
        } elseif ($status === 3) {
            $percent = 100;
        } elseif ($status === 1 && ($percent < 0 || $percent > 99)) {
            $percent = 0;
        } elseif ($status === 2 && ($percent <= 0 || $percent >= 100)) {
            $percent = 50;
        }

        if ($hasStatus) {
            $payload['status'] = $status;
        }

        if ($hasPercent || in_array($status, [0, 1, 2, 3], true)) {
            $payload['percent_complete'] = $percent;
        }
    }
}
