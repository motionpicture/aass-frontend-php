<?php
namespace Aass\Frontend\Models;

use \WindowsAzure\Table\Models\EdmType;
use \WindowsAzure\Table\Models\Entity;
use \WindowsAzure\Table\Models\Filters\Filter;

class Media extends \Aass\Common\Models\Media
{
    public function getListByUserId($userId)
    {
        $entities = [];

        // ページングに対応
        $nextPartitionKey = null;
        $nextRowKey = null;
        $i = 0;

        while ($i == 0 || !is_null($nextPartitionKey) || !is_null($nextRowKey)) {
            $i++;

            $qopts = new \WindowsAzure\Table\Models\QueryEntitiesOptions();
            $filter =  Filter::applyAnd(
                Filter::applyEq(Filter::applyPropertyName('PartitionKey'), Filter::applyConstant($userId, EdmType::STRING)),
                Filter::applyNe(Filter::applyPropertyName('Status'), Filter::applyConstant(self::STATUS_DELETED, EdmType::STRING))
            );
            $qopts->setFilter($filter);
            $qopts->setTop(10);

            if ($nextPartitionKey) {
                $qopts->setNextPartitionKey($nextPartitionKey);
            }
            if ($nextRowKey) {
                $qopts->setNextRowKey($nextRowKey);
            }

            $result = $this->azureTable->queryEntities('Media', $qopts);

            $entities = array_merge($result->getEntities(), $entities);
            $nextPartitionKey = $result->getNextPartitionKey();
            $nextRowKey = $result->getNextRowKey();
        }

        return $entities;
    }

    public function getByRowKey($partitionKey, $rowKey)
    {
        $entities = [];

        $qopts = new \WindowsAzure\Table\Models\QueryEntitiesOptions();
        $filter =  Filter::applyAnd(
            Filter::applyEq(Filter::applyPropertyName('PartitionKey'), Filter::applyConstant($partitionKey, EdmType::STRING)),
            Filter::applyEq(Filter::applyPropertyName('RowKey'), Filter::applyConstant($rowKey, EdmType::STRING))
        );
        $qopts->setFilter($filter);
        $qopts->setTop(1);
        $result = $this->azureTable->queryEntities('Media', $qopts);
        $entities = $result->getEntities();

        return (!empty($entities)) ? $entities[0] : null;
    }

    public function deleteByRowKey($partitionKey, $rowKey)
    {
        $entities = [];

        $qopts = new \WindowsAzure\Table\Models\QueryEntitiesOptions();
        $filter =  Filter::applyAnd(
                Filter::applyEq(Filter::applyPropertyName('PartitionKey'), Filter::applyConstant($partitionKey, EdmType::STRING)),
                Filter::applyEq(Filter::applyPropertyName('RowKey'), Filter::applyConstant($rowKey, EdmType::STRING))
                );
        $qopts->setFilter($filter);
        $qopts->setTop(1);
        $result = $this->azureTable->queryEntities('Media', $qopts);
        $entities = $result->getEntities();

        $entity = (!empty($entities)) ? $entities[0] : null;

        if (!is_null($entity)) {
            $entity->addProperty('Status', EdmType::STRING, self::STATUS_DELETED);
            $result = $this->azureTable->mergeEntity('Media', $entity);
        }
    }

    /**
     * メディアエンティティを作成する
     *
     * @return boolean
     */
    public function create($partitionKey, $rowKey, $extension, $title, $description, $uploadedBy)
    {
        $entity = new Entity();
        $entity->setPartitionKey($partitionKey);
        $entity->setRowKey($rowKey);
        $entity->addProperty('Extension', EdmType::STRING, $extension);
        $entity->addProperty('Title', EdmType::STRING, $title);
        $entity->addProperty('Description', EdmType::STRING, $description);
        $entity->addProperty('UploadedBy', EdmType::STRING, $uploadedBy);
        $entity->addProperty('Status', EdmType::STRING, self::STATUS_UPLOADED);

        $result = $this->azureTable->insertEntity('Media', $entity);
        $this->logger->addDebug(var_export($result, true));
        return ($result->getEntity()->isValid());
    }

    public function update($partitionKey, $rowKey, $title, $description, $uploadedBy)
    {
        $entities = [];

        $qopts = new \WindowsAzure\Table\Models\QueryEntitiesOptions();
        $filter =  Filter::applyAnd(
                Filter::applyEq(Filter::applyPropertyName('PartitionKey'), Filter::applyConstant($partitionKey, EdmType::STRING)),
                Filter::applyEq(Filter::applyPropertyName('RowKey'), Filter::applyConstant($rowKey, EdmType::STRING))
                );
        $qopts->setFilter($filter);
        $qopts->setTop(1);
        $result = $this->azureTable->queryEntities('Media', $qopts);
        $entities = $result->getEntities();

        $entity = (!empty($entities)) ? $entities[0] : null;

        if (!is_null($entity)) {
            $entity->addProperty('Title', EdmType::STRING, "{$title}");
            $entity->addProperty('Description', EdmType::STRING, "{$description}");
            $entity->addProperty('UploadedBy', EdmType::STRING, "{$uploadedBy}");
            $result = $this->azureTable->mergeEntity('Media', $entity);
        }
    }
}