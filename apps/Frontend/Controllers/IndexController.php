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
        if ($this->request->has('self')) {
            echo $_SERVER['REMOTE_ADDR'];
            exit;
        }

        $url = (empty($_SERVER["HTTPS"]) ? "http://" : "https://") . "{$_SERVER["HTTP_HOST"]}{$_SERVER["REQUEST_URI"]}?self";
        echo 'IP address is ' . file_get_contents($url);
        return false;
    }}