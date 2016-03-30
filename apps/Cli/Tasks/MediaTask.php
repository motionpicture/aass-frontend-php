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
     * エンコード処理を施す
     */
    public function encodeAction()
    {
        $mediaModel = new MediaModel;
        $medias = $mediaModel->getListByStatus(MediaModel::STATUS_ASSET_CREATED, 10);
        $this->logger->addInfo("medias:" . var_export($medias, true));

        if (!empty($medias)) {
            foreach ($medias as $media) {
                try {
                    $job = $this->createJob($media);
                    $this->logger->addInfo('job created. job:' . var_export($job, true));
                    $mediaModel->addJob($media['id'], $job->getId(), $job->getState());
                    $this->logger->addInfo("media added job. id:{$media['id']}");
                } catch (\Exception $e) {
                    $this->logger->addError("createJob failed. message:{$e}");
                }
            }
        }
    }

    /**
     * jobを作成する
     *
     * @return \WindowsAzure\MediaServices\Models\Job
     */
    private function createJob($media)
    {
        $tasks = $this->getTasks($media['filename']);
        $this->logger->addInfo('tasks prepared. tasks count:' . count($tasks));

        $inputAsset = $this->mediaService->getAsset($media['asset_id']);

        $job = new Job();
        $job->setName("AassJob[{$media['filename']}]");
        $job = $this->mediaService->createJob($job, [$inputAsset], $tasks);

        $this->logger->addInfo("job created. job:" . var_export($job, true));

        return $job;
    }

    private function getTasks($filename)
    {
        $tasks = [];

        $mediaProcessor = $this->mediaService->getLatestMediaProcessor('Media Encoder Standard');

        // thumbnail task
        $taskBody = $this->getMediaServicesTaskBody(
            'JobInputAsset(0)',
            'JobOutputAsset(0)',
            Asset::OPTIONS_NONE,
            "AassMediaAsset[{$filename}][thumbnails]"
        );
        $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
        $configurationFile  = __DIR__ . '/../../../config/thumbnailConfig.json';
        $task->setConfiguration(file_get_contents($configurationFile));
        $tasks[] = $task;

        // adaptive bitrate mp4 task
        $taskBody = $this->getMediaServicesTaskBody(
            'JobInputAsset(0)',
            'JobOutputAsset(1)',
            Asset::OPTIONS_NONE,
            "AassMediaAsset[{$filename}][H264SingleBitrate1080p]"
        );
        $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
        $task->setConfiguration('H264 Single Bitrate 1080p');
        $tasks[] = $task;

        // adaptive bitrate mp4 task
        $taskBody = $this->getMediaServicesTaskBody(
            'JobInputAsset(0)',
            'JobOutputAsset(2)',
            Asset::OPTIONS_NONE,
            "AassMediaAsset[{$filename}][H264MultipleBitrate1080p]"
        );
        $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
        $task->setConfiguration('H264 Multiple Bitrate 1080p');
        $tasks[] = $task;

        return $tasks;
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
        $mediaModel = new MediaModel;
        $medias = $mediaModel->getListByStatus(MediaModel::STATUS_JOB_CREATED, 10);
        $this->logger->addInfo("medias:" . var_export($medias, true));

        if (!empty($medias)) {
            foreach ($medias as $media) {
                $job = $this->mediaService->getJob($media['job_id']);

                // ジョブのステータスを更新
                if (!is_null($job) && $media['job_state'] != $job->getState()) {
                    $state = $job->getState();
                    $this->logger->addInfo("job state change. new state:{$state}");
                    try {
                        // ジョブが完了の場合、URL発行プロセス
                        if ($state == Job::STATE_FINISHED) {
                            // ジョブのアウトプットアセットを取得
                            $assets = $this->mediaService->getJobOutputMediaAssets($job->getId());

                            $urls = [];
                            $asset = $assets[0];
                            $urls['thumbnail'] = $this->createUrlThumbnail($asset->getId(), $media['filename']);
                            $asset = $assets[1];
                            $urls['mp4'] = $this->createUrlMp4($asset->getId(), $media['filename']);
                            $asset = $assets[2];
                            $urls['streaming'] = $this->createUrl($asset->getId(), $media['filename']);

                            // ジョブに関する情報更新と、URL更新
                            $this->logger->addInfo("changing status to STATUS_JOB_FINISHED... id:{$media['id']}");
                            $mediaModel->updateJobState($media['id'], $state, MediaModel::STATUS_JOB_FINISHED, $urls);

                            // TODO URL通知
//                             if (!is_null($url)) {
//                                 $this->sendEmail($media);
//                             }
                        } else if ($state == Job::STATE_ERROR || $state == Job::STATE_CANCELED) {
                            $this->logger->addInfo("changing status to STATUS_ERROR... id:{$media['id']}");
                            $mediaModel->updateJobState($media['id'], $state, MediaModel::STATUS_ERROR);
                        } else {
                            $this->logger->addInfo("changing state_job to {$state}... id:{$media['id']}");
                            $mediaModel->updateJobState($media['id'], $state, MediaModel::STATUS_JOB_CREATED);
                        }
                    } catch (\Exception $e) {
                        $this->logger->addError("delivering url for streaming throw exception. message:{$e}");
                    }
                }
            }
        }
    }

    /**
     * アセットに対してサムネイルURLを生成する
     *
     * @param string $assetId
     * @param string $filename
     * @return string
     */
    private function createUrlThumbnail($assetId, $filename)
    {
        // 特定のAssetに対して、同時に5つを超える一意のLocatorを関連付けることはできない
        // 万が一OnDemandOriginロケーターがあれば削除
        $locators = $this->mediaService->getAssetLocators($assetId);
        foreach ($locators as $locator) {
            if ($locator->getType() == Locator::TYPE_SAS) {
                $this->mediaService->deleteLocator($locator);
                $this->logger->addInfo("OnDemandOrigin locator has been deleted. locator:". var_export($locator, true));
            }
        }

        // 読み取りアクセス許可を持つAccessPolicyの作成
        $accessPolicy = new AccessPolicy('ThumbnailPolicy');
        $accessPolicy->setDurationInMinutes(25920000);
        $accessPolicy->setPermissions(AccessPolicy::PERMISSIONS_READ);
        $accessPolicy = $this->mediaService->createAccessPolicy($accessPolicy);

        // サムネイル用のURL作成
        $locator = new Locator($assetId, $accessPolicy, Locator::TYPE_SAS);
        $locator->setName('ThumbnailLocator_' . $assetId);
        $locator->setStartTime(new \DateTime('now -5 minutes'));
        $locator = $this->mediaService->createLocator($locator);

        // URLを生成
        $url = "{$locator->getBaseUri()}/{$filename}_000001.jpg{$locator->getContentAccessComponent()}";
        $this->logger->addInfo("thumbnail url created. url:{$url}");

        return $url;
    }

    /**
     * アセットに対してシングルmp4URLを生成する
     *
     * @param string $assetId
     * @param string $filename
     * @return string
     */
    private function createUrlMp4($assetId, $filename)
    {
        // 特定のAssetに対して、同時に5つを超える一意のLocatorを関連付けることはできない
        // 万が一OnDemandOriginロケーターがあれば削除
        $locators = $this->mediaService->getAssetLocators($assetId);
        foreach ($locators as $locator) {
            if ($locator->getType() == Locator::TYPE_SAS) {
                $this->mediaService->deleteLocator($locator);
                $this->logger->addInfo("OnDemandOrigin locator has been deleted. locator:". var_export($locator, true));
            }
        }

        // 読み取りアクセス許可を持つAccessPolicyの作成
        $accessPolicy = new AccessPolicy('MP4Policy');
        $accessPolicy->setDurationInMinutes(25920000);
        $accessPolicy->setPermissions(AccessPolicy::PERMISSIONS_READ);
        $accessPolicy = $this->mediaService->createAccessPolicy($accessPolicy);

        // サムネイル用のURL作成
        $locator = new Locator($assetId, $accessPolicy, Locator::TYPE_SAS);
        $locator->setName('MP4Locator_' . $assetId);
        $locator->setStartTime(new \DateTime('now -5 minutes'));
        $locator = $this->mediaService->createLocator($locator);

        // URLを生成
        $url = "{$locator->getBaseUri()}/{$filename}_1920x1080_6750.mp4{$locator->getContentAccessComponent()}";
        $this->logger->addInfo("mp4 url created. url:{$url}");

        return $url;
    }

    /**
     * アセットに対してストリーミングURLを生成する
     * @see http://msdn.microsoft.com/ja-jp/library/jj889436.aspx
     *
     * @param string $assetId
     * @param string $filename
     * @return string
     */
    private function createUrl($assetId, $filename)
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
        $url = "{$locator->getPath()}{$filename}.ism/Manifest";
        $this->logger->addInfo("streaming url created. url:{$url}");

        return $url;
    }

    /**
     * @see https://msdn.microsoft.com/ja-jp/library/azure/mt427372.aspx
     */
    public function copyFileAction()
    {
        $mediaModel = new MediaModel;
        $medias = $mediaModel->getListByStatus(MediaModel::STATUS_JOB_FINISHED, 10);
        $this->logger->addInfo("medias:" . var_export($medias, true));

        if (!empty($medias)) {
            foreach ($medias as $media) {
                $result = false;

                try {
                    $to = MediaModel::getFilePath4Jpeg2000Ready($media['filename']);
                    $sourceUrl = $media['url_mp4'];

                    $this->fileService->copyFile($sourceUrl, $to);

                    $this->logger->addInfo("changing status to copied... id:{$media['id']}");
                    $result = $mediaModel->updateStatus($media['id'], MediaModel::STATUS_JPEG2000_READY);
                } catch (\Exception $e) {
                    $this->logger->addError("copy failed. message:{$e}");
                }

                if (!$result) {
                    try {
                        $this->logger->addInfo("changing status to error... id:{$media['id']}");
                        $mediaModel->updateStatus($media['id'], MediaModel::STATUS_ERROR);
                    } catch (Exception $e) {
                        $this->logger->addError("updateStatus to error failed. message:{$e}");
                    }
                }
            }
        }
    }

    /**
     * jpeg2000エンコード進捗を確認する
     */
    public function checkJpeg2000EncodeAction()
    {
        $mediaModel = new MediaModel;
        $medias = $mediaModel->getListByStatus(MediaModel::STATUS_JPEG2000_READY, 10);
        $this->logger->addInfo("medias:" . var_export($medias, true));

        if (!empty($medias)) {
            foreach ($medias as $media) {
                try {
                    $properties = $this->fileService->getFileProperties(MediaModel::getFilePath4Jpeg2000Encoded($media['filename']));
                    $this->logger->addDebug(var_export($properties, true));

                    // TODO
//                     if (!is_null($properties)) {
//                         $this->logger->addInfo("changing status to encoded... id:{$media['id']}");
//                     } else {
//                         $this->logger->addInfo("not encoded yet. id:{$media['id']}");
//                     }
                } catch (\Exception $e) {
                    $this->logger->addError("getFile failed. message:{$e}");
                }
            }
        }
    }

    public function deleteAction()
    {
        // 削除済みのメディアを取得
        $mediaModel = new MediaModel;
        $medias = $mediaModel->getListByStatus(MediaModel::STATUS_DELETED, 10);
        $this->logger->addInfo("medias:" . var_export($medias, true));

        if (!empty($medias)) {
            foreach ($medias as $media) {
                try {
                    $this->logger->addInfo("deleting asset... asset_id:{$media['asset_id']}");
                    $this->mediaService->deleteAsset($media['asset_id']);
                } catch (\Exception $e) {
                    $this->logger->addError("deleteAsset failed. message:{$e}");
                }

                try {
                    $this->logger->addInfo("deleting media... id:{$media['id']}");
                    $mediaModel->delete($media['id']);
                } catch (\Exception $e) {
                    $this->logger->addError("delete failed. message:{$e}");
                }
            }
        }
    }
}