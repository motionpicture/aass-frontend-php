<?php
namespace Aass\Cli\Models;

class Media extends \Aass\Common\Models\Media
{
    public function getByStatus($status)
    {
        $query = <<<EOF
SELECT id, title, status, filename, extension, url_thumbnail, url_mp4, url_streaming, asset_id, job_id, job_state, created_at
 FROM media
 WHERE status = :status
 ORDER BY created_at ASC
EOF;
        $statement = $this->db->prepare($query);
        $statement->execute([
            'status' => $status
        ]);

        return $statement->fetch();
    }

    public function getListByStatus($status, $limit)
    {
        $query = <<<EOF
SELECT id, title, status, filename, extension, url_thumbnail, url_mp4, url_streaming, asset_id, job_id, job_state, created_at
 FROM media
 WHERE status = :status
 ORDER BY created_at ASC
 LIMIT {$limit}
EOF;
        $statement = $this->db->prepare($query);
        $statement->execute([
            'status' => $status,
        ]);

        return $statement->fetchAll();
    }

    public function addJob($id, $jobId, $jobState)
    {
        $query = <<<EOF
UPDATE media SET
 status = :status, job_id = :jobId, job_state = :jobState, updated_at = NOW()
 WHERE id = :id
EOF;
        $statement = $this->db->prepare($query);
        $result = $statement->execute([
            ':id' => $id,
            ':jobId' => $jobId,
            ':jobState' => $jobState,
            ':status' => self::STATUS_JOB_CREATED
        ]);

        return $result;
    }

    public function updateJobState($id, $state, $status, $urls = [])
    {
        $query = <<<EOF
UPDATE media SET
 url_thumbnail = :urlThumbnail, url_mp4 = :urlMp4, url_streaming = :urlStreaming,
 job_state = :jobState, status = :status, updated_at = NOW()
 WHERE id = :id
EOF;
        $statement = $this->db->prepare($query);
        $result = $statement->execute([
            ':id' => $id,
            ':urlThumbnail' => (isset($urls['thumbnail'])) ? $urls['thumbnail'] : null,
            ':urlMp4' => (isset($urls['mp4'])) ? $urls['mp4'] : null,
            ':urlStreaming' => (isset($urls['streaming'])) ? $urls['streaming'] : null,
            ':jobState' => $state,
            ':status' => $status
        ]);

        return $result;
    }

    public function updateStatus($id, $status)
    {
        $query = <<<EOF
UPDATE media SET
 status = :status, updated_at = NOW()
 WHERE id = :id
EOF;
        $statement = $this->db->prepare($query);
        $result = $statement->execute([
            ':id' => $id,
            ':status' => $status
        ]);

        return $result;
    }
}