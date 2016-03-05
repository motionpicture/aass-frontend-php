<?php
namespace Aass\Frontend\Controllers;

class AuthController extends BaseController
{
    public function loginAction()
    {
        if ($this->request->isPost()) {
            $userId    = $this->request->getPost('UserId');
            $password = $this->request->getPost('Password');
            $this->logger->addDebug("try to login as {$userId}...");

            $showModel = new \Aass\Frontend\Models\Show;
            $show = $showModel->getByUserId($userId);

            if (!is_null($show)) {
                $this->logger->addDebug($show->getRowKey());
                $this->session->set(
                    'auth',
                    [
                        'UserId'   => $show->getRowKey()
//                         'UserId'   => $show->getPropertyValue('UserId')
                    ]
                );
                $this->flash->success("Welcome {$show->getRowKey()}");

                return $this->response->redirect('/');
            }
        }
    }
}
