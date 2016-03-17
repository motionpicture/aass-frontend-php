<?php
namespace Aass\Frontend\Models;

class Application extends \Aass\Common\Models\Application
{
    /**
     * 作成する
     *
     * @return boolean
     */
    public function create($mediaId)
    {
        $statement = $this->db->prepare('INSERT INTO application (media_id, status, created_at, updated_at)'
                                      . ' VALUES (:mediaId, :status, NOW(), NOW())');
        $result = $statement->execute([
            ':mediaId' => $mediaId,
            ':status' => self::STATUS_CREATED
        ]);

        return $result;
    }
}