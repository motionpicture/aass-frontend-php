<?php
namespace Aass\Common\Models;

class Media extends Base
{
    public $id;
    public $eventId;
    public $title; // 動画名
    public $description; // 動画概要
    public $uploaded_by; // 動画登録者名
    public $status; // 状態
    public $size; // サイズ
    public $extension; // ファイル拡張子
    public $playtime_string; // 再生時間
    public $playtime_seconds; // 再生時間
    public $url_thumbnail; // サムネイルURL
    public $url_mp4; // MP4URL
    public $url_streaming; // ストリーミングURL
    public $asset_id; // アセットID
    public $job_id; // ジョブID
    public $job_state; // ジョブ進捗
    public $job_start_at; // ジョブ開始日時
    public $job_end_at; // ジョブ終了日時
    public $deleted_at; // 削除日時

    const STATUS_ASSET_CREATED = 1; // アセット作成済み(エンコード待ち)
    const STATUS_JOB_CREATED = 2; // ジョブ作成済み(エンコード中)
    const STATUS_JOB_FINISHED = 3; // ジョブ完了
    const STATUS_ENCODED = 4; // JPEG2000エンコード済み
    const STATUS_ERROR = 8; // エンコード失敗
    const STATUS_DELETED = 9; // 削除済み

    public static function status2string($status)
    {
        $strings = [
            self::STATUS_ASSET_CREATED => 'アップロード完了',
            self::STATUS_JOB_CREATED => 'ジョブ進行中',
            self::STATUS_JOB_FINISHED => 'ジョブ完了',
            self::STATUS_ENCODED => 'エンコード完了',
            self::STATUS_ERROR => 'エンコード失敗',
            self::STATUS_DELETED => '削除済み',
        ];

        return (isset($strings[$status])) ? $strings[$status] : null;
    }

    public function getListByEventId($eventId)
    {
        $query = <<<EOF
SELECT * FROM media WHERE event_id = :eventId AND status <> :status
EOF;
        $statement = $this->db->prepare($query);
        $statement->execute([
            'eventId' => $eventId,
            'status' => self::STATUS_DELETED
        ]);

        return $statement->fetchAll();
    }
}