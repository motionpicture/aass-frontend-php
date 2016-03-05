<?php
namespace Aass\Frontend\Plugins;

use Phalcon\Events\Event;
use Phalcon\Mvc\User\Plugin;
use Phalcon\Mvc\Dispatcher;

class SecurityPlugin extends Plugin
{
    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher)
    {
        $auth = $this->session->get('auth');
//         if (!$auth) {
//             $role = 'Guests';
//         } else {
//             $role = 'Users';
//         }

        $controller = $dispatcher->getControllerName();
        $action = $dispatcher->getActionName();

        if ($controller != 'auth' || $action != 'login') {
// Obtain the ACL list
//         $acl = $this->getAcl();
//         $allowed = $acl->isAllowed($role, $controller, $action);
//         if ($allowed != Acl::ALLOW) {
            if (is_null($auth)) {
                // If he doesn't have access forward him to the index controller
                $this->flash->error("You don't have access to this module");
                $dispatcher->forward([
                    'controller' => 'auth',
                    'action'     => 'login'
                ]);

                return false;
            }
        }
    }
}