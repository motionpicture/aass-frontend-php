<?php
namespace Aass\Frontend\Controllers;

class ApplicationController extends BaseController
{
    public function indexAction()
    {
        $body = $this->blobService2->listContainers();
        echo '<pre>';
        var_dump($body);
        echo '</pre>';
        return false;
    }
}