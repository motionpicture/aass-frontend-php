<?php
namespace Aass\Frontend\Controllers;

use Aass\Frontend\Models\Event as EventModel;
use Aass\Frontend\Forms\LoginForm as LoginForm;

class AuthController extends BaseController
{
    public function loginAction()
    {
        $form = new LoginForm();
        $messages = [];

        if ($this->request->isPost()) {
            if ($form->isValid($this->request->getPost())) {
                $this->logger->addDebug("try to login as {$this->request->getPost('user_id')}/{$this->request->getPost('password')}...");
                $eventModel = new EventModel;
                $event = $eventModel->getLoginUser($this->request->getPost('user_id'), $this->request->getPost('password'));

                if ($event) {
                    $this->logger->addDebug(var_export($event, true));
                    $this->auth->login($event);
                    $this->flash->success("Welcome {$event['user_id']}");

                    return $this->response->redirect('');
                } else {
                    $messages[] = 'ユーザIDとパスワードが間違っています';
                }
            } else {
                $messages = $form->getMessages();
            }
        }

        $this->view->messages = $messages;
        $this->view->form = $form;
    }

    public function logoutAction()
    {
        $this->auth->logout();
        return $this->response->redirect('');
    }
}