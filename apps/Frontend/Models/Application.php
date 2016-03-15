<?php
namespace Aass\Frontend\Models;

class Application extends \Aass\Common\Models\Application
{
    public function getByEventId($eventId)
    {
        $statement = $this->db->prepare('SELECT a.id, a.media_id, a.status FROM application AS a LEFT JOIN media AS m ON m.id = a.media_id WHERE m.event_id = :eventId AND m.status <> :mediaStatus AND a.status <> :applicationStatus');
        $statement->execute([
            'eventId' => $eventId,
            'mediaStatus' => Media::STATUS_DELETED,
            'applicationStatus' => self::STATUS_DELETED
        ]);

        return $statement->fetch();
    }

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