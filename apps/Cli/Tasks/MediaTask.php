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
        $tasks = [];

        $mediaProcessor = $this->mediaService->getLatestMediaProcessor('Media Encoder Standard');

        // thumbnail task
        $taskBody = $this->getMediaServicesTaskBody(
            'JobInputAsset(0)',
            'JobOutputAsset(0)',
            Asset::OPTIONS_NONE,
            "{$media['id']}[thumbnails]"
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
            "{$media['id']}[MultipleBitrate1080p]"
        );
        $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
        $task->setConfiguration('H264 Multiple Bitrate 1080p');
//         $task->setConfiguration('H264 Single Bitrate 1080p');
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
        $xml = <<<EOF
<?xml version="1.0" encoding="utf-8"?>
<taskBody>
    <inputAsset>{$inputAsset}</inputAsset>
    <outputAsset assetCreationOptions="{$outputAssetOptions}" assetName="{$outputAssetName}">{$outputAsset}</outputAsset>
</taskBody>';
EOF;
        return $xml;
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

    /**
     * @see https://msdn.microsoft.com/ja-jp/library/azure/mt427372.aspx
     */
    public function copyFileAction()
    {
        // エンコード済みのメディアをひとつ取得
        $media = [];

        $filename = date('YmdHis') . '.mp4';
        $sourceUrl = 'https://mediasvcdtgv96fwgm0zz.blob.core.windows.net/asset-3b45a965-4f90-4274-b84e-948b5b6ca8a8/motionpicture56e7986a78de9.mp4?sv=2012-02-12&sr=c&si=7cdcd131-fc24-4887-8c80-abb6c542b5d1&sig=%2Fn69d0Zo80QvUjXTB5wEYX4EWpex%2FgS%2B8ZOaJ1Pj1Rk%3D&st=2016-03-23T02%3A42%3A27Z&se=2116-02-28T02%3A42%3A27Z';
        $url = "https://{$this->config->get('storage_account_name')}.file.core.windows.net/test/test/{$filename}";
        $httpMethod = 'PUT';
        $dateTime = new \DateTime('now', new \DateTimeZone('GMT'));
        $dateTimeString = $dateTime->format('D, d M Y H:i:s T');
        $headers = [
            'x-ms-version' => '2015-02-21',
            'x-ms-date' => $dateTimeString,
            'x-ms-copy-source' => $sourceUrl,
        ];
        $queryParams = [
//             'restype' => 'directory'
        ];
        $authorizationHeader = $this->getAuthorizationHeader($headers, $url, $queryParams, $httpMethod);

        $headers = array_merge($headers, [
            'Authorization' => $authorizationHeader,
            'Content-Length' => 0,
            'Date' => $dateTimeString,
        ]);

        $httpHeaders = [];
        foreach ($headers as $header => $value) {
            $httpHeaders[] = "{$header}: {$value}";
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $httpMethod,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false
        ];

        $curl = curl_init($url);
        curl_setopt_array($curl, $options);
        $result = curl_exec($curl);
        $info   = curl_getinfo($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if (CURLE_OK !== $errno) {
            $this->logger->addInfo('error:' . var_export($error, true));
            $this->logger->addInfo('errno:' . var_export($errno, true));
        }

        $this->logger->addInfo('result:' . var_export($result, true));
        $this->logger->addInfo('info:' . var_export($info, true));
        exit;
    }

    private function getAuthorizationHeader($headers, $url, $queryParams, $httpMethod)
    {
        $stringToSign = [
            strtoupper($httpMethod), // VERB
            '', // Content-Encoding
            '', // Content-Language
//             0, // Content-Length
            '', // Content-Length
            '', // Content-MD5
            '', // Content-Type
            '', // Date
            '', // If-Modified-Since
            '', // If-Match
            '', // If-None-Match
            '', // If-Unmodified-Since
            '', // Range
        ];

        $canonicalizedHeaders = $this->computeCanonicalizedHeaders($headers);
        $canonicalizedResource = $this->computeCanonicalizedResource($url, $queryParams);

        $stringToSign[] = implode("\n", $canonicalizedHeaders);
        $stringToSign[] = $canonicalizedResource;
        $stringToSign = implode("\n", $stringToSign);

        return 'SharedKey ' . $this->config->get('storage_account_name') . ':' . base64_encode(
            hash_hmac('sha256', $stringToSign, base64_decode($this->config->get('storage_account_key')), true)
        );
    }

    private function computeCanonicalizedHeaders($headers)
    {
        $canonicalizedHeaders = [];
        $normalizedHeaders    = [];

        foreach ($headers as $header => $value) {
            // Convert header to lower case.
            $header = strtolower($header);

            // Unfold the string by replacing any breaking white space
            // (meaning what splits the headers, which is \r\n) with a single
            // space.
            $value = str_replace("\r\n", ' ', $value);

            // Trim any white space around the colon in the header.
            $value  = ltrim($value);
            $header = rtrim($header);

            $normalizedHeaders[$header] = $value;
        }

        // Sort the headers lexicographically by header name, in ascending order.
        // Note that each header may appear only once in the string.
        ksort($normalizedHeaders);

        foreach ($normalizedHeaders as $key => $value) {
            $canonicalizedHeaders[] = $key . ':' . $value;
        }

        return $canonicalizedHeaders;
    }

    private function computeCanonicalizedResource($url, $queryParams)
    {
        $queryParams = array_change_key_case($queryParams);

        $canonicalizedResource = '/' . $this->config->get('storage_account_name');

        $canonicalizedResource .= parse_url($url, PHP_URL_PATH);

        if (count($queryParams) > 0) {
            ksort($queryParams);
        }

        foreach ($queryParams as $key => $value) {
            // Grouping query parameters
            $values = explode(',', $value);
            sort($values);
            $separated = implode(',', $values);
            $canonicalizedResource .= "\n" . $key . ':' . $separated;
        }

        return $canonicalizedResource;
    }
}
?>