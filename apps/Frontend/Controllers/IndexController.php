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
            return false;
        }

        $url = (empty($_SERVER["HTTPS"]) ? "http://" : "https://") . "{$_SERVER["HTTP_HOST"]}{$_SERVER["REQUEST_URI"]}?self";
        $ipAddress = file_get_contents($url);
        $this->logger->addDebug("IP address is {$ipAddress}");
        echo "IP address is {$ipAddress}";
        return false;
    }
}