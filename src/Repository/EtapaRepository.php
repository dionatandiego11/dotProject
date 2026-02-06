<?php
/**
 * Repository para Etapas (PPA)
 *
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\EtapaEntity;
use DateTime;

class EtapaRepository extends BaseRepository
{
    protected string $table = 'dotp_etapas';
    protected string $primaryKey = 'id';

    /**
     * {@inheritdoc}
     */
    protected function hydrate(array $data): EtapaEntity
    {
        $entity = new EtapaEntity();
        $entity->setId((int) $data['id']);
        $entity->setProjetoId((int) $data['projeto_id']);
        $entity->setNumero((int) $data['numero']);
        $entity->setNome($data['nome'] ?? '');
        $entity->setEstado($data['estado'] ?? 'Nao_Iniciada');

        if (!empty($data['data_prevista_inicio'])) {
            $entity->setDataPrevistaInicio(new DateTime($data['data_prevista_inicio']));
        }
        if (!empty($data['data_prevista_fim'])) {
            $entity->setDataPrevistaFim(new DateTime($data['data_prevista_fim']));
        }
        if (!empty($data['data_real_inicio'])) {
            $entity->setDataRealInicio(new DateTime($data['data_real_inicio']));
        }
        if (!empty($data['data_real_fim'])) {
            $entity->setDataRealFim(new DateTime($data['data_real_fim']));
        }

        if (isset($data['responsavel_id'])) {
            $entity->setResponsavelId($data['responsavel_id'] ? (int) $data['responsavel_id'] : null);
        }

        if (isset($data['dias_atraso'])) {
            $entity->setDiasAtraso((int) $data['dias_atraso']);
        }

        if (isset($data['justificativa_atraso'])) {
            $entity->setJustificativaAtraso($data['justificativa_atraso']);
        }

        if (isset($data['evidencia_url'])) {
            $entity->setEvidenciaUrl($data['evidencia_url']);
        }

        if (isset($data['percent_conclusao'])) {
            $entity->setPercentConclusao((float) $data['percent_conclusao']);
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof EtapaEntity) {
            throw new \InvalidArgumentException('Entity must be EtapaEntity');
        }

        return [
            'id' => $entity->getId(),
            'projeto_id' => $entity->getProjetoId(),
            'numero' => $entity->getNumero(),
            'nome' => $entity->getNome(),
            'estado' => $entity->getEstado(),
            'percent_conclusao' => $entity->getPercentConclusao(),
            'data_prevista_inicio' => $entity->getDataPrevistaInicio()?->format('Y-m-d'),
            'data_prevista_fim' => $entity->getDataPrevistaFim()?->format('Y-m-d'),
            'data_real_inicio' => $entity->getDataRealInicio()?->format('Y-m-d'),
            'data_real_fim' => $entity->getDataRealFim()?->format('Y-m-d'),
            'responsavel_id' => $entity->getResponsavelId(),
            'dias_atraso' => $entity->getDiasAtraso(),
            'justificativa_atraso' => $entity->getJustificativaAtraso(),
            'evidencia_url' => $entity->getEvidenciaUrl(),
        ];
    }

    /**
     * Salva (insere/atualiza) a etapa.
     */
    public function save(object $entity): int
    {
        if (!$entity instanceof EtapaEntity) {
            throw new \InvalidArgumentException('Entity must be EtapaEntity');
        }

        $data = $this->extract($entity);
        $result = false;

        if ($entity->getId() === null) {
            unset($data['id']);
            $result = $this->db->insert($this->table, $data);
            if ($result) {
                $entity->setId((int) $this->db->lastInsertId());
            }
        } else {
            $id = $data['id'];
            unset($data['id']);
            $result = $this->db->update(
                $this->table,
                $data,
                "{$this->primaryKey} = {$id}"
            );
        }

        if ($result) {
            $this->clearCache();
        }

        return $result ? (int) $entity->getId() : 0;
    }

    /**
     * Busca etapas por projeto.
     *
     * @return EtapaEntity[]
     */
    public function findByProjetoId(int $projectId): array
    {
        return $this->findBy(
            ['projeto_id' => $projectId],
            ['numero' => 'ASC']
        );
    }
}
