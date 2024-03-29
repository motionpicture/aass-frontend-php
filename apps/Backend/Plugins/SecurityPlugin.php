<?php
namespace Aass\Backend\Plugins;

use Phalcon\Events\Event;
use Phalcon\Mvc\User\Plugin;
use Phalcon\Mvc\Dispatcher;

class SecurityPlugin extends Plugin
{
    const AUTH_SESSION_NAME = 'AassBackendAuth';

    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher)
    {
        $auth = $this->session->get(self::AUTH_SESSION_NAME);

        $controller = $dispatcher->getControllerName();
        $action = $dispatcher->getActionName();

        if ($controller != 'auth' || $action != 'login') {
            if (is_null($auth)) {
                $this->flash->error("You don't have access to this module");
                $dispatcher->forward([
                    'controller' => 'auth',
                    'action'     => 'login'
                ]);

                return false;
            }
        }
    }

    public function login($params)
    {
        $this->session->set(self::AUTH_SESSION_NAME, $params);
    }

    public function logout()
    {
        $this->session->set(self::AUTH_SESSION_NAME, null);
    }

    public function getUserId()
    {
        $admin = $this->session->get(self::AUTH_SESSION_NAME);
        return $admin['user_id'];
    }
}