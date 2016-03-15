<?php
namespace Aass\Backend\Models;

class Event extends \Aass\Common\Models\Event
{
    public function getAll()
    {
        $query = <<<EOF
SELECT
    e.id, e.user_id, e.place, e.email
    , a.media_id AS media_id, a.media_title AS media_title, a.media_uploaded_by AS media_uploaded_by, a.media_url AS media_url, a.media_status AS media_status, a.media_job_end_at AS media_job_end_at
    , a.id AS application_id, a.status AS application_status
 FROM event AS e
 LEFT JOIN (
     SELECT
         a2.id, a2.media_id, a2.status
         , m.event_id, m.title AS media_title, m.uploaded_by AS media_uploaded_by, m.url AS media_url, m.status AS media_status, m.job_end_at AS media_job_end_at
     FROM application AS a2 LEFT JOIN media AS m ON m.id = a2.media_id
     WHERE m.status <> :mediaStatus AND a2.status <> :applicationStatus
 ) a ON a.event_id = e.id
 GROUP BY e.id
 ORDER BY held_at DESC
EOF;
        $statement = $this->db->prepare($query);
        $statement->execute([
            'mediaStatus' => Media::STATUS_DELETED,
            'applicationStatus' => Application::STATUS_DELETED
        ]);

        return $statement->fetchAll();
    }

    public function getById($id)
    {
        $statement = $this->db->prepare('SELECT * FROM event WHERE id = :id LIMIT 1');
        $statement->execute([
            ':id' => $id,
        ]);

        return $statement->fetch();
    }

    public function isDuplicateByUserId($id, $userId)
    {
        $query = 'SELECT id FROM event WHERE user_id = :userId';
        $params = [
            ':userId' => $userId,
        ];
        if ($id) {
            $query .= ' AND id <> :id';
            $params['id'] = $id;
        }
        $statement = $this->db->prepare($query);
        $statement->execute($params);

        return ($statement->fetchColumn());
    }

    public function isDuplicateByEmail($id, $email)
    {
        $query = 'SELECT id FROM event WHERE email = :email';
        $params = [
            ':email' => $email,
        ];
        if ($id) {
            $query .= ' AND id <> :id';
            $params['id'] = $id;
        }
        $statement = $this->db->prepare($query);
        $statement->execute($params);

        return ($statement->fetchColumn());
    }

    public function updateFromArray(array $params)
    {
        if (isset($params['id']) && $params['id']) {
            $statement = $this->db->prepare('UPDATE `event` SET `user_id` = :userId, `email` = :email, `password` = :password, `held_at` = :heldAt, `place` = :place, `remarks` = :remarks, updated_at = NOW() WHERE id = :id');
            $result = $statement->execute([
                ':id' => $params['id'],
                ':userId' => $params['user_id'],
                ':email' => $params['email'],
                ':password' => $params['password'],
                ':heldAt' => $params['held_at'],
                ':place' => $params['place'],
                ':remarks' => $params['remarks']
            ]);
        } else {
            $statement = $this->db->prepare('INSERT INTO event (user_id, email, password, held_at, place, remarks, created_at, updated_at) VALUES (:userId, :email, :password, :heldAt, :place, :remarks, NOW(), NOW())');
            $result = $statement->execute([
                ':userId' => $params['user_id'],
                ':email' => $params['email'],
                ':password' => $params['password'],
                ':heldAt' => $params['held_at'],
                ':place' => $params['place'],
                ':remarks' => $params['remarks']
            ]);
        }

        return $result;
    }
}