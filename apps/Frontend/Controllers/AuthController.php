<?php
namespace Aass\Frontend\Controllers;

use Aass\Frontend\Models\Event as EventModel;

class AuthController extends BaseController
{
    public function loginAction()
    {
        if ($this->request->isPost()) {
            $this->logger->addDebug("try to login as {$this->request->getPost('user_id')}/{$this->request->getPost('password')}...");
            $eventModel = new EventModel;
            $event = $eventModel->getLoginUser($this->request->getPost('user_id'), $this->request->getPost('password'));

            if ($event) {
                $this->logger->addDebug(var_export($event, true));
                $this->auth->login($event);
                $this->flash->success("Welcome {$event['user_id']}");

                return $this->response->redirect('');
            }
        }
    }

    public function logoutAction()
    {
        $this->auth->logout();
        return $this->response->redirect('');
    }
}