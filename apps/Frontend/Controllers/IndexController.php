<?php
namespace Aass\Frontend\Controllers;

class IndexController extends BaseController
{
    public function indexAction()
    {
        $this->logger->addDebug($this->mode);
        $this->response->redirect('medias');
    }

    public function scaleOutTestAction()
    {
        $this->logger->addDebug("Server IP address:{$_SERVER['LOCAL_ADDR']}");
        echo "Server IP address:{$_SERVER['LOCAL_ADDR']}";
        exit;
    }}