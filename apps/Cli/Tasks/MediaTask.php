<?php
namespace Aass\Cli\Tasks;

use \Aass\Cli\Models\Media as MediaModel;
use \WindowsAzure\MediaServices\Models\Asset;
use \WindowsAzure\MediaServices\Models\AccessPolicy;
use \WindowsAzure\MediaServices\Models\Locator;
use \WindowsAzure\MediaServices\Models\Job;
use \WindowsAzure\MediaServices\Models\Task;
use \WindowsAzure\MediaServices\Models\TaskOptions;

set_time_limit(0);
ini_set('memory_limit', '1024M');

class MediaTask extends BaseTask
{
    /**
     * アップロードされたファイルに対して処理を施す
     *
     * 1.メディアサーバーへアップロード
     * 2.メディアエンティティを更新
     */
    public function local2assetAction()
    {
        $mediaEntity = null;
        $assetId = null;
        $isComleted = false;

        try {
            // アップロード済みのメディアをひとつ取得
            $mediaModel = new MediaModel;
            $mediaEntity = $mediaModel->getByStatus(MediaModel::STATUS_UPLOADED);
            $this->logger->addInfo("uploaded media:" . var_export($mediaEntity, true));
        } catch (\Exception $e) {
            $this->logger->addError("fail in getByStatus {$e}");
        }

        if (!is_null($mediaEntity)) {
            try {
                // メディアサービスへ資産としてアップロードする
                list($assetId, $isComleted) = $this->ingestAsset($mediaEntity);
                $this->logger->addInfo("ingestAsset Success. assetId:{$assetId}");
                if (!$isComleted) {
                    throw new \Exception('ingestAsset not completed.');
                }
            } catch (\Exception $e) {
                $this->logger->addError("fail in ingestAsset {$e}");
            }

            if (!is_null($assetId) && $isComleted) {
                try {
                    // メディアエンティティ更新
                    $mediaModel->updateAsset($mediaEntity, $assetId);
                    $this->logger->addInfo("assetId has been added to media entity.");
                } catch (\Exception $e) {
                    $this->logger->addError("fail in updateAsset. message:{$e}");
                }
            }

            if ($isComleted) {
                // ストレージへのアップロード完了していれば元ファイル削除
                if ($this->mode != 'dev') {
                    unlink(MediaModel::getUploadedFilePath($mediaEntity));
                }
            } else {
                if (!is_null($assetId)) {
                    // TODO アセット削除？
                }

                // エラーステータスに更新
                $mediaModel->updateStatus($mediaEntity, MediaModel::STATUS_ERROR);
            }
        }
    }

    /**
     * 資産をインジェストする
     *
     * @see http://msdn.microsoft.com/ja-jp/library/jj129593.aspx
     * @return array
     */
    private function ingestAsset($mediaEntity)
    {
        $asset = null;
        $assetId = null;
        $isCompleted = false;

        try {
            // 資産を作成する
            $asset = new Asset(Asset::OPTIONS_NONE);
            $asset->setName($mediaEntity->getRowKey());
            $asset = $this->mediaService->createAsset($asset);
            $assetId = $asset->getId();
            $this->logger->addInfo("asset has been created. asset:{$assetId}");
        } catch (\Exception $e) {
            $this->logger->addError('createAsset throw exception. message:' . $e->getMessage());
        }

        if (!is_null($assetId)) {
            $isUploaded = false;
            try {
                // ファイルのアップロードを実行する
                $fileName = "{$mediaEntity->getRowKey()}.{$mediaEntity->getPropertyValue('Extension')}";
                $path = MediaModel::getUploadedFilePath($mediaEntity);
                $this->logger->addInfo("creating BlockBlob... path:{$path}");
                $content = fopen($path, 'rb');
                $result = $this->blobService->createBlockBlob(basename($asset->getUri()), $fileName, $content);
                $this->logger->addDebug('BlockBlob has been created. result:' . var_export($result, true));
                fclose($content);

                $isUploaded = true;
            } catch (\Exception $e) {
                $this->logger->addError("upload2storage throw exception. message:{$e}");
            }
            $this->logger->addInfo('isUploaded:' . var_export($isUploaded, true));

            if ($isUploaded) {
                try {
                    // ファイル メタデータの生成
                    $this->mediaService->createFileInfos($asset);

                    // ここまできて初めて、アセットの準備が完了したことになる
                    $isCompleted = true;
                    $this->logger->addInfo("inputAsset has been prepared completely. asset:{$assetId}");
                } catch (\Exception $e) {
                   $this->logger->addError("createFileInfos throw exception. message:{$e}");
                }
            }
        }

        return [$assetId, $isCompleted];
    }

    /**
     * アップロードディレクトリに残ったゴミファイルを物理削除する
     */
    public function cleanAction()
    {
        $dir = __DIR__ . '/../../../uploads';
        $iterator = new \RecursiveDirectoryIterator($dir);
        $iterator = new \RecursiveIteratorIterator($iterator);

        $files4delete = [];
        foreach ($iterator as $fileinfo) {
            try {
                // $fileinfoはSplFiIeInfoオブジェクト
                // ドット始まりのファイルは除外
                if ($fileinfo->isFile() && substr($fileinfo->getFilename(), 0, 1) != '.') {
                    // 3日以上経過していれば追加
                    $absence = time() - $fileinfo->getCTime();
                    if ($absence > 60 * 60 * 24 * 3) {
                        $files4delete[] = [
                            'path'  => $fileinfo->getPathname(),
                            'ctime' => date('Y-m-d H:i:s', $fileinfo->getCTime()) // inode変更時刻
                        ];
                    }
                }
            } catch (Exception $e) {
                $this->logger->log('fileinfo-> throw exception. message:' . $e->getMessage());
            }
        }

        $this->logger->log('files4delete:' . count($files4delete));

        // 削除
        foreach ($files4delete as $file) {
            unlink($file['path']);
            $this->logger->log("A file has been deleted. path:{$file['path']}");
        }
    }

    /**
     * エンコード処理を施す
     */
    public function encodeAction()
    {
        $mediaEntity = null;
        $job = null;
        $isUpdated = false;

        try {
            // アセット作成済みのメディアエンティティを取得
            $mediaModel = new MediaModel;
            $mediaEntity = $mediaModel->getByStatus(MediaModel::STATUS_ASSET_CREATED);
            $this->logger->addInfo("media:" . var_export($mediaEntity, true));
        } catch (\Exception $e) {
            $this->logger->addError("fail in getByStatus {$e}");
        }

        if (!is_null($mediaEntity)) {
            try {
                $job = $this->createJob($mediaEntity);

                if (!is_null($job)) {
                    $mediaModel->updateJob($mediaEntity, $job->getId(), $job->getState());
                    $isUpdated = true;
                }
            } catch (\Exception $e) {
                $this->logger->addError("fail in creating job. message:{$e}");
            }
        }

        $this->logger->addinfo('encode result:' . var_export($isUpdated, true));
    }

    /**
     * jobを作成する
     *
     * @return \WindowsAzure\MediaServices\Models\Job
     */
    private function createJob($mediaEntity)
    {
        $job = null;

        $tasks = [];
        // adaptive bitrate mp4 task
//         $mediaProcessor = $this->mediaService->getLatestMediaProcessor('Azure Media Encoder');
        $mediaProcessor = $this->mediaService->getLatestMediaProcessor('Media Encoder Standard');
        $taskBody = $this->getMediaServicesTaskBody(
            'JobInputAsset(0)',
            'JobOutputAsset(0)',
            Asset::OPTIONS_NONE,
            "{$mediaEntity->getRowKey()}[adaptive_bitrate_mp4]"
        );
        $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
//         $task->setConfiguration('H264 Adaptive Bitrate MP4 Set 1080p');
        $task->setConfiguration('H264 Multiple Bitrate 1080p');
//         $configurationFile  = __DIR__ . '/../../../config/taskConfig.xml';
//         $task->setConfiguration(file_get_contents($configurationFile));

        $tasks[] = $task;
        $this->logger->addInfo('tasks has been prepared. tasks count:' . count($tasks));

        $inputAsset = $this->mediaService->getAsset($mediaEntity->getPropertyValue('AssetId'));

        $job = new Job();
        $job->setName("Aass_job_for_{$mediaEntity->getRowKey()}");
        $job = $this->mediaService->createJob($job, [$inputAsset], $tasks);

        $this->logger->addInfo("job has been created. job:" . var_export($job, true));

        return $job;
    }

    /**
     * タスクボディ文字列を作成する
     *
     * @param string $inputAsset
     * @param string $outputAsset
     * @param string $outputAssetOptions
     * @param string $outputAssetName
     * @return string
     */
    private function getMediaServicesTaskBody($inputAsset, $outputAsset, $outputAssetOptions, $outputAssetName) {
        return '<?xml version="1.0" encoding="utf-8"?><taskBody><inputAsset>' . $inputAsset . '</inputAsset><outputAsset assetCreationOptions="' . $outputAssetOptions . '" assetName="' . $outputAssetName . '">' . $outputAsset . '</outputAsset></taskBody>';
    }

    /**
     * ジョブ進捗を確信し、完了していればURLを発行する
     */
    public function checkJobAction()
    {
        $mediaEntity = null;
        $job = null;

        try {
            // ジョブ作成済みのメディアエンティティを取得
            $mediaModel = new MediaModel;
            $mediaEntity = $mediaModel->getByStatus(MediaModel::STATUS_JOB_CREATED);
            $this->logger->addInfo("media:" . var_export($mediaEntity, true));
        } catch (\Exception $e) {
            $this->logger->addError("fail in getByStatus {$e}");
        }

        if (!is_null($mediaEntity)) {
            try {
                // メディアサービスよりジョブを取得
                $job = $this->mediaService->getJob($mediaEntity->getPropertyValue('JobId'));
            } catch (\Exception $e) {
                $this->logger->addError("mediaServicesWrapper->getJob() throw exception. message:{$e}");
            }

            // ジョブのステータスを更新
            if (!is_null($job) && $mediaEntity->getPropertyValue('JobState') != $job->getState()) {
                $state = $job->getState();
                $this->logger->addInfo("job state change. new state:{$state}");
                try {
                    // ジョブが完了の場合、URL発行プロセス
                    if ($state == Job::STATE_FINISHED) {
                        // ジョブのアウトプットアセットを取得
                        $assets = $this->mediaService->getJobOutputMediaAssets($job->getId());
                        $asset = $assets[0];
                        $url = $this->createUrl($asset->getId(), $asset->getName(), $mediaEntity->getRowKey());

                        // ジョブに関する情報更新と、URL更新
                        $mediaModel->updateJobState($mediaEntity, $state, $url, MediaModel::STATUS_ENCODED);

                        // TODO URL通知
//                         if (!is_null($url)) {
//                             $this->sendEmail($media);
//                         }
                    } else if ($state == Job::STATE_ERROR || $state == Job::STATE_CANCELED) {
                        $mediaModel->updateJobState($mediaEntity, $state, '', MediaModel::STATUS_ERROR);
                    } else {
                        $mediaModel->updateJobState($mediaEntity, $state, '', MediaModel::STATUS_JOB_CREATED);
                    }
                } catch (\Exception $e) {
                    $this->logger->addError("delivering url for streaming throw exception. message:{$e}");
                }
            }
        }
    }

    /**
     * アセットに対してストリーミングURLを生成する
     * @see http://msdn.microsoft.com/ja-jp/library/jj889436.aspx
     *
     * @param string $assetId
     * @param string $assetName
     * @param string $rowKey
     * @return string
     */
    private function createUrl($assetId, $assetName, $rowKey)
    {
        // 特定のAssetに対して、同時に5つを超える一意のLocatorを関連付けることはできない
        // 万が一OnDemandOriginロケーターがあれば削除
        $locators = $this->mediaService->getAssetLocators($assetId);
        foreach ($locators as $locator) {
            if ($locator->getType() == Locator::TYPE_ON_DEMAND_ORIGIN) {
                $this->mediaService->deleteLocator($locator);
                $this->logger->addInfo("OnDemandOrigin locator has been deleted. locator:". var_export($locator, true));
            }
        }

        // 読み取りアクセス許可を持つAccessPolicyの作成
        $accessPolicy = new AccessPolicy('StreamingPolicy');
        $accessPolicy->setDurationInMinutes(25920000);
        $accessPolicy->setPermissions(AccessPolicy::PERMISSIONS_READ);
        $accessPolicy = $this->mediaService->createAccessPolicy($accessPolicy);

        // コンテンツストリーミング用の配信元URLの作成
        $locator = new Locator($assetId, $accessPolicy, Locator::TYPE_ON_DEMAND_ORIGIN);
        $locator->setName('StreamingLocator_' . $assetId);
        $locator->setStartTime(new \DateTime('now -5 minutes'));
        $locator = $this->mediaService->createLocator($locator);

        // URLを生成
        $url = sprintf('%s%s.ism/Manifest', $locator->getPath(), $rowKey);
        $this->logger->addInfo("urls have been created. url:{$url}");

        return $url;
    }
}
?>