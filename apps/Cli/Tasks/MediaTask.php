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
        $media = null;
        $job = null;
        $isUpdated = false;

        try {
            // アセット作成済みのメディアエンティティを取得
            $mediaModel = new MediaModel;
            $media = $mediaModel->getByStatus(MediaModel::STATUS_ASSET_CREATED);
            $this->logger->addInfo("media:" . var_export($media, true));
        } catch (\Exception $e) {
            $this->logger->addError("mediaModel->getByStatus throw exception. message:{$e}");
        }

        if ($media) {
            try {
                $job = $this->createJob($media);

                if (!is_null($job)) {
                    $mediaModel->updateJob($media['id'], $job->getId(), $job->getState());
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
    private function createJob($media)
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
            "{$media['id']}[adaptive_bitrate_mp4]"
        );
        $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
//         $task->setConfiguration('H264 Adaptive Bitrate MP4 Set 1080p');
        $task->setConfiguration('H264 Multiple Bitrate 1080p');
//         $configurationFile  = __DIR__ . '/../../../config/taskConfig.xml';
//         $task->setConfiguration(file_get_contents($configurationFile));

        $tasks[] = $task;
        $this->logger->addInfo('tasks has been prepared. tasks count:' . count($tasks));

        $inputAsset = $this->mediaService->getAsset($media['asset_id']);

        $job = new Job();
        $job->setName("Aass_job_for_{$media['id']}");
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
        $media = null;
        $job = null;

        try {
            // ジョブ作成済みのメディアエンティティを取得
            $mediaModel = new MediaModel;
            $media = $mediaModel->getByStatus(MediaModel::STATUS_JOB_CREATED);
            $this->logger->addInfo("media:" . var_export($media, true));
        } catch (\Exception $e) {
            $this->logger->addError("fail in getByStatus {$e}");
        }

        if ($media) {
            try {
                // メディアサービスよりジョブを取得
                $job = $this->mediaService->getJob($media['job_id']);
            } catch (\Exception $e) {
                $this->logger->addError("mediaServicesWrapper->getJob() throw exception. message:{$e}");
            }

            // ジョブのステータスを更新
            if (!is_null($job) && $media['job_state'] != $job->getState()) {
                $state = $job->getState();
                $this->logger->addInfo("job state change. new state:{$state}");
                try {
                    // ジョブが完了の場合、URL発行プロセス
                    if ($state == Job::STATE_FINISHED) {
                        // ジョブのアウトプットアセットを取得
                        $assets = $this->mediaService->getJobOutputMediaAssets($job->getId());
                        $asset = $assets[0];
                        $url = $this->createUrl($asset->getId(), $media['filename']);

                        // ジョブに関する情報更新と、URL更新
                        $mediaModel->updateJobState($media['id'], $state, $url, MediaModel::STATUS_ENCODED);

                        // TODO URL通知
//                         if (!is_null($url)) {
//                             $this->sendEmail($media);
//                         }
                    } else if ($state == Job::STATE_ERROR || $state == Job::STATE_CANCELED) {
                        $mediaModel->updateJobState($media['id'], $state, '', MediaModel::STATUS_ERROR);
                    } else {
                        $mediaModel->updateJobState($media['id'], $state, '', MediaModel::STATUS_JOB_CREATED);
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
        $this->logger->addInfo("urls have been created. url:{$url}");

        return $url;
    }
}
?>