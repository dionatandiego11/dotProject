<?php
/**
 * Repository CalendarEvent
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\CalendarEventEntity;
use DateTime;

class CalendarEventRepository extends BaseRepository
{
    protected string $table = 'dotp_events';
    protected string $primaryKey = 'event_id';

    protected function hydrate(array $data): CalendarEventEntity
    {
        $entity = new CalendarEventEntity();
        $entity->setId((int) $data['event_id']);
        $entity->setTitle($data['event_title']);
        $entity->setDescription($data['event_description'] ?? null);
        $entity->setUserId((int) $data['event_owner']);
        $entity->setProjectId($data['event_project'] ? (int) $data['event_project'] : null);
        $entity->setTaskId($data['event_task'] ? (int) $data['event_task'] : null);
        $entity->setStartDate(new DateTime($data['event_start_date']));
        $entity->setEndDate(!empty($data['event_end_date']) ? new DateTime($data['event_end_date']) : null);
        $entity->setAllDay((bool) ($data['event_all_day'] ?? 0));
        $entity->setColor($data['event_color'] ?? null);
        $entity->setType((int) ($data['event_type'] ?? 0));
        $entity->setStatus((int) ($data['event_status'] ?? 0));
        
        if (!empty($data['event_created'])) {
            $entity->setCreatedAt(new DateTime($data['event_created']));
        }

        return $entity;
    }

    protected function extract(object $entity): array
    {
        if (!$entity instanceof CalendarEventEntity) {
            throw new \InvalidArgumentException('Entity must be CalendarEventEntity');
        }

        return [
            'event_id' => $entity->getId(),
            'event_title' => $entity->getTitle(),
            'event_description' => $entity->getDescription(),
            'event_owner' => $entity->getUserId(),
            'event_project' => $entity->getProjectId(),
            'event_task' => $entity->getTaskId(),
            'event_start_date' => $entity->getStartDate()->format('Y-m-d H:i:s'),
            'event_end_date' => $entity->getEndDate()?->format('Y-m-d H:i:s'),
            'event_all_day' => $entity->isAllDay() ? 1 : 0,
            'event_color' => $entity->getColor(),
            'event_type' => $entity->getType(),
            'event_status' => $entity->getStatus(),
        ];
    }

    /**
     * Busca eventos por usuário e período
     * @return array<CalendarEventEntity>
     */
    public function findByUserAndPeriod(int $userId, DateTime $start, DateTime $end): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE event_owner = ?
                AND event_status = 0
                AND (
                    (event_start_date BETWEEN ? AND ?)
                    OR (event_end_date BETWEEN ? AND ?)
                    OR (event_start_date <= ? AND event_end_date >= ?)
                )
                ORDER BY event_start_date ASC";
        
        $params = [
            $userId,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ];
        
        $results = $this->db->fetchAll($sql, $params);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Busca eventos de hoje
     * @return array<CalendarEventEntity>
     */
    public function findToday(int $userId): array
    {
        $today = new DateTime();
        $start = $today->format('Y-m-d 00:00:00');
        $end = $today->format('Y-m-d 23:59:59');
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE event_owner = ?
                AND event_status = 0
                AND event_start_date BETWEEN ? AND ?
                ORDER BY event_start_date ASC";
        
        $results = $this->db->fetchAll($sql, [$userId, $start, $end]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Busca eventos futuros
     * @return array<CalendarEventEntity>
     */
    public function findUpcoming(int $userId, int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE event_owner = ?
                AND event_status = 0
                AND event_start_date >= NOW()
                ORDER BY event_start_date ASC
                LIMIT ?";
        
        $results = $this->db->fetchAll($sql, [$userId, $limit]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Busca eventos por projeto
     * @return array<CalendarEventEntity>
     */
    public function findByProject(int $projectId): array
    {
        return $this->findBy(['event_project' => $projectId, 'event_status' => 0], ['event_start_date' => 'ASC']);
    }
}
