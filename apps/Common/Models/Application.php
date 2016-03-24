<?php
namespace Aass\Common\Models;

class Application extends Base
{
    public $id;
    public $media_id;
    public $status; // ステータス

    const STATUS_CREATED = 1; // 申請中
    const STATUS_ACCEPTED = 2; // 承認
    const STATUS_REJECTED = 3; // 却下
    const STATUS_DELETED = 4; // 削除済み
    const STATUS_END = 5; // 上映済み

    public static function status2string($status)
    {
        $strings = [
            self::STATUS_CREATED => '申請中',
            self::STATUS_ACCEPTED => '申請承認済み',
            self::STATUS_REJECTED => '申請却下済み',
            self::STATUS_DELETED => '削除済み',
            self::STATUS_END => '上映済み',
        ];

        return (isset($strings[$status])) ? $strings[$status] : null;
    }

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
}