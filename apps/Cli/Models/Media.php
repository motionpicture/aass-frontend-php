<?php
namespace Aass\Cli\Models;

class Media extends \Aass\Common\Models\Media
{
    public function getByStatus($status)
    {
        $statement = $this->db->prepare('SELECT id, title, status, filename, extension, asset_id, job_id, job_state FROM media WHERE status = :status');
        $statement->execute([
            'status' => $status
        ]);

        return $statement->fetch();
    }

    public function updateJob($id, $jobId, $jobState)
    {
        $statement = $this->db->prepare('UPDATE media SET status = :status, job_id = :jobId, job_state = :jobState, updated_at = NOW() WHERE id = :id');
        $result = $statement->execute([
            ':id' => $id,
            ':jobId' => $jobId,
            ':jobState' => $jobState,
            ':status' => self::STATUS_JOB_CREATED
        ]);

        return $result;
    }

    public function updateJobState($id, $state, $url, $status)
    {
        $statement = $this->db->prepare('UPDATE media SET url = :url, job_state = :jobState, status = :status, updated_at = NOW() WHERE id = :id');
        $result = $statement->execute([
            ':id' => $id,
            ':url' => $url,
            ':jobState' => $state,
            ':status' => $status
        ]);

        return $result;
    }
}