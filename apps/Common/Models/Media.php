<?php
namespace Aass\Common\Models;

class Media extends Base
{
    public $id;
    public $showId;
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

    const STATUS_UPLOADED = 'UPLOADED'; // アップロード済み(アセット作成待ち)
    const STATUS_ASSET_CREATED = 'ASSET_CREATED'; // アセット作成済み(エンコード待ち)
    const STATUS_JOB_CREATED = 'JOB_CREATED'; // ジョブ作成済み(エンコード中)
    const STATUS_ENCODED = 'ENCODED'; // エンコード済み
    const STATUS_DELETED = 'DELETED'; // 削除済み
    const STATUS_ERROR = 'ERROR'; // エンコード失敗

    public static function getUploadedFilePath($mediaEntity)
    {
        $fileName = "{$mediaEntity->getRowKey()}.{$mediaEntity->getPropertyValue('Extension')}";
        return __DIR__ . "/../../../uploads/{$fileName}";
    }
}