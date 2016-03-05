<?php
namespace Aass\Frontend\Models;

use \WindowsAzure\Table\Models\EdmType;
use \WindowsAzure\Table\Models\Entity;
use \WindowsAzure\Table\Models\Filters\Filter;

class Show extends \Aass\Common\Models\Show
{
    public function getByUserId($userId)
    {
        $entities = [];

        $qopts = new \WindowsAzure\Table\Models\QueryEntitiesOptions();
        $filter = Filter::applyEq(Filter::applyPropertyName('RowKey'), Filter::applyConstant($userId, EdmType::STRING));
        $qopts->setFilter($filter);
        $qopts->setTop(1);
        $result = $this->azureTable->queryEntities('Show', $qopts);
        $entities = $result->getEntities();

        return (!empty($entities)) ? $entities[0] : null;
    }
}