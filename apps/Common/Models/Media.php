<?php
namespace Aass\Common\Models;

class Media extends Base
{
    public $id;
    public $eventId;
    public $title; // 動画名
    public $description; // 動画概要
    public $uploadedBy; // 動画登録者名
    public $status; // 状態
    public $size; // サイズ
    public $extension; // ファイル拡張子
    public $playtimeString; // 再生時間
    public $playtimeSeconds; // 再生時間
    public $url; // ストリーミングURL
    public $assetId; // アセットID
    public $jobId; // ジョブID
    public $jobState; // ジョブ進捗
    public $jobStartAt; // ジョブ開始日時
    public $jobEndAt; // ジョブ終了日時
    public $deletedAt; // 削除日時

    const STATUS_ASSET_CREATED = 1; // アセット作成済み(エンコード待ち)
    const STATUS_JOB_CREATED = 2; // ジョブ作成済み(エンコード中)
    const STATUS_ENCODED = 3; // エンコード済み
    const STATUS_DELETED = 4; // 削除済み
    const STATUS_ERROR = 5; // エンコード失敗

    public static function status2string($status)
    {
        $strings = [
            self::STATUS_ASSET_CREATED => 'アップロード完了',
            self::STATUS_JOB_CREATED => 'エンコード中',
            self::STATUS_ENCODED => 'エンコード完了',
            self::STATUS_DELETED => '削除済み',
            self::STATUS_ERROR => 'エンコード失敗',
        ];

        return (isset($strings[$status])) ? $strings[$status] : null;
    }
}