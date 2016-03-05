<?php
namespace Aass\Frontend\Controllers;

class IndexController extends BaseController
{
    public function indexAction()
    {
        $this->logger->addDebug($this->mode);
        $this->response->redirect('medias');
    }
}