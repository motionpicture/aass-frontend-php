<?php
namespace Aass\Backend\Controllers;

class IndexController extends BaseController
{
    public function indexAction()
    {
        $this->logger->addDebug($this->mode);
        $this->response->redirect('events');
    }
}