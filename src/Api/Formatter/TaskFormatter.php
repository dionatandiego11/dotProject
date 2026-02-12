<?php
/**
 * Task Formatter
 *
 * Responsible for serializing task rows into API payloads.
 *
 * @package DotProject\Api\Formatter
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Formatter;

class TaskFormatter
{
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function format(array $row, bool $detailed = false): array
    {
        $ownerId = !empty($row['task_owner']) ? (int) $row['task_owner'] : null;
        if (array_key_exists('task_assigned_to', $row) && !empty($row['task_assigned_to'])) {
            $assigneeId = (int) $row['task_assigned_to'];
        } else {
            $assigneeId = $ownerId;
        }

        $data = [
            'id' => (int) $row['task_id'],
            'name' => $row['task_name'],
            'project' => [
                'id' => (int) ($row['task_project'] ?? 0),
                'name' => $row['project_name'] ?? null,
            ],
            'status' => (int) ($row['task_status'] ?? 0),
            'priority' => (int) ($row['task_priority'] ?? 0),
            'percent_complete' => (int) ($row['task_percent_complete'] ?? 0),
            'start_date' => $row['task_start_date'] ?? null,
            'end_date' => $row['task_end_date'] ?? null,
            'duration' => (int) ($row['task_duration'] ?? 0),
            'owner_id' => $ownerId,
            'assigned_to' => $assigneeId,
            'milestone' => (bool) ($row['task_milestone'] ?? false),
        ];

        if ($detailed) {
            $data['description'] = $row['task_description'] ?? '';
            $data['hours_worked'] = (float) ($row['task_hours_worked'] ?? 0);
            $data['creator_id'] = $row['task_creator'] ? (int) $row['task_creator'] : null;
            $data['parent_id'] = $row['task_parent'] ? (int) $row['task_parent'] : null;
            $data['order'] = (int) ($row['task_order'] ?? 0);
            $data['type'] = (int) ($row['task_type'] ?? 0);
            $data['access'] = (int) ($row['task_access'] ?? 0);
            $data['created'] = $row['task_created'] ?? null;
            $data['updated'] = $row['task_updated'] ?? null;
        }

        return $data;
    }
}
