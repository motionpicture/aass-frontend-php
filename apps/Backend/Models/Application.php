<?php
namespace Aass\Backend\Models;

class Application extends \Aass\Common\Models\Application
{
    public function updateStatus($id, $status)
    {
        $statement = $this->db->prepare('UPDATE application SET status = :status, updated_at = NOW() WHERE id = :id');
        $result = $statement->execute([
            ':id' => $id,
            ':status' => $status
        ]);

        return $result;
    }

    public function deleteById($id)
    {
        $statement = $this->db->prepare('UPDATE application SET status = :status, updated_at = NOW() WHERE id = :id');
        $result = $statement->execute([
            ':id' => $id,
            ':status' => self::STATUS_DELETED
        ]);

        return $result;
    }
}