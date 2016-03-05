<?php
namespace Aass\Common\Models;

// class Base extends \Phalcon\Mvc\Model
class Base
{
    protected $mode;
    protected $config;
    protected $logger;
    protected $azureTable;

    public function __construct()
    {
        $this->mode = \Phalcon\DI::getDefault()->get('mode');
        $this->config = \Phalcon\DI::getDefault()->get('config');
        $this->logger = \Phalcon\DI::getDefault()->get('logger');
        $this->azureTable = \Phalcon\DI::getDefault()->get('azureTable');
    }
}