<?php
namespace Aass\Backend\Controllers;

use Aass\Backend\Models\Admin as adminModel;

class AuthController extends BaseController
{
    public function loginAction()
    {
        if ($this->request->isPost()) {
            $this->logger->addDebug("try to login as {$this->request->getPost('user_id')}/{$this->request->getPost('password')}...");
            $adminModel = new adminModel;
            $admin = $adminModel->getLoginUser($this->request->getPost('user_id'), $this->request->getPost('password'));

            if ($admin) {
                $this->logger->addDebug(var_export($admin, true));
                $this->auth->login($admin);
                $this->flash->success("Welcome {$admin['user_id']}");

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