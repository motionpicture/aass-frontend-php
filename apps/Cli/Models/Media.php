<?php
namespace Aass\Cli\Models;

use \WindowsAzure\Table\Models\EdmType;
use \WindowsAzure\Table\Models\Entity;
use \WindowsAzure\Table\Models\Filters\Filter;

class Media extends \Aass\Common\Models\Media
{
    public function getByStatus($status)
    {
        $entities = [];

        $qopts = new \WindowsAzure\Table\Models\QueryEntitiesOptions();
        $filter = Filter::applyEq(Filter::applyPropertyName('Status'), Filter::applyConstant("{$status}", EdmType::STRING));
        $qopts->setFilter($filter);
        $qopts->setTop(1);
        $result = $this->azureTable->queryEntities('Media', $qopts);
        $entities = $result->getEntities();

        return (!empty($entities)) ? $entities[0] : null;
    }

    public function updateAsset($entity, $assetId)
    {
        $entity = $this->addExtraInfo($entity);
        $entity->addProperty('AssetId', EdmType::STRING, $assetId);
        $entity->addProperty('Status', EdmType::STRING, self::STATUS_ASSET_CREATED);

        $this->azureTable->mergeEntity('Media', $entity);
    }

    public function addExtraInfo($mediaEntity)
    {
        $path = self::getUploadedFilePath($mediaEntity);

        $size = (filesize($path)) ? filesize($path) : '';
        $mediaEntity->addProperty('Size', EdmType::STRING, "{$size}");

        // 再生時間を取得
        $getID3 = new \getID3;
        $fileInfo = $getID3->analyze($path);
        $this->logger->addInfo('getID3->analyze $fileInfo:' . var_export($fileInfo, true));
        if (isset($fileInfo['playtime_string'])) {
            $mediaEntity->addProperty('PlaytimeString', EdmType::STRING, "{$fileInfo['playtime_string']}");
        }
        if (isset($fileInfo['playtime_seconds'])) {
            $mediaEntity->addProperty('PlaytimeSeconds', EdmType::STRING, "{$fileInfo['playtime_seconds']}");
        }

        return $mediaEntity;
    }

    public function updateJob($entity, $jobId, $jobState)
    {
        $entity->addProperty('JobId', EdmType::STRING, "{$jobId}");
        $entity->addProperty('JobState', EdmType::STRING, "{$jobState}");
        $entity->addProperty('Status', EdmType::STRING, self::STATUS_JOB_CREATED);

        $this->azureTable->mergeEntity('Media', $entity);
    }

    public function updateJobState($entity, $state, $url, $status)
    {
        $entity->addProperty('JobState', EdmType::STRING, "{$state}");
        $entity->addProperty('Url', EdmType::STRING, "{$url}");
        $entity->addProperty('Status', EdmType::STRING, "{$status}");
        $this->azureTable->mergeEntity('Media', $entity);
    }

    public function updateStatus($entity, $status)
    {
        $entity->addProperty('Status', EdmType::STRING, "{$status}");
        $this->azureTable->mergeEntity('Media', $entity);
    }
}