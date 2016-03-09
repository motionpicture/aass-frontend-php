<?php
namespace Aass\Common\Models;

class Base
{
    protected $mode;
    protected $config;
    protected $logger;
    protected $db;

    public function __construct()
    {
        $this->mode = \Phalcon\DI::getDefault()->get('mode');
        $this->config = \Phalcon\DI::getDefault()->get('config');
        $this->logger = \Phalcon\DI::getDefault()->get('logger');
        $this->db = \Phalcon\DI::getDefault()->get('db');
    }
}