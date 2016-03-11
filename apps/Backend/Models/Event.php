<?php
namespace Aass\Backend\Models;

class Event extends \Aass\Common\Models\Event
{
    public function getAll()
    {
        $statement = $this->db->prepare('SELECT * FROM event ORDER BY held_at DESC');
        $statement->execute();

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