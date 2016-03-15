<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$_SERVER['PHP_SELF'] = __DIR__ . '/index.php';

require_once __DIR__ . '/../../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../../apps/' . strtr(str_replace('Aass\\', '', $class), '\\', DIRECTORY_SEPARATOR) . '.php';
    if (is_readable($file)) {
        require_once $file;
        return;
    }
});

use Phalcon\Mvc\View;
use Phalcon\Mvc\Application;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Url as UrlProvider;
use Phalcon\Session\Adapter\Files as Session;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Events\Manager as EventsManager;

// try {
    $di = new FactoryDefault();
    include __DIR__ . '/../../apps/Common/dependencies.php';

    $di->set('session', function ()
    {
        $session = new Session();
        $session->start();

        return $session;
    });

    $di->set('dispatcher', function()
    {
        // Create an events manager
        $eventsManager = new EventsManager();
        $eventsManager->attach('dispatch:beforeExecuteRoute', new \Aass\Backend\Plugins\SecurityPlugin);
        // Handle exceptions and not-found exceptions using NotFoundPlugin
//         $eventsManager->attach('dispatch:beforeException', new NotFoundPlugin);

        $dispatcher = new Dispatcher();
        $dispatcher->setEventsManager($eventsManager);
        $dispatcher->setDefaultNamespace('Aass\Backend\Controllers');

        return $dispatcher;
    });

    $di->set('auth', function()
    {
        return new \Aass\Backend\Plugins\SecurityPlugin;
    });

    $di->set('logger', function() use ($di)
    {
        // プロセスID
        $pid = getmypid();
        $pid = (!$pid) ? 'false' : $pid;

        $formatter = new \Monolog\Formatter\LineFormatter(null, null, true); // 第３引数で改行を有効に
        $stream = new \Monolog\Handler\StreamHandler(
            __DIR__ . "/../../logs/{$di->get('mode')}/Backend/" . date('Ymd') . ".log",
            ($di->get('mode') == 'dev') ? \Monolog\Logger::DEBUG : \Monolog\Logger::INFO
        );
        $stream->setFormatter($formatter);
        $logger = new \Monolog\Logger("AassBackend[{$di->get('mode')}] [PID:{$pid}]");
        $logger->pushHandler($stream);

        return $logger;
    });

    $di->set('router', function()
    {
        $router = new \Phalcon\Mvc\Router();

        $router->add('/login', [
            'controller' => 'auth',
            'action' => 'login'
        ])->setName('login');

        $router->add('/logout', [
            'controller' => 'auth',
            'action' => 'logout'
        ])->setName('logout');

        $router->add('/event/new', [
            'controller' => 'event',
            'action' => 'new'
        ])->setName('eventNew');

        $router->add('/event/{id}/edit', [
            'controller' => 'event',
            'action' => 'edit'
        ])->setName('eventEdit');

        $router->add('/events', [
            'controller' => 'event',
            'action' => 'index'
        ])->setName('events');

        $router->add('/application/{id}/accept', [
            'controller' => 'application',
            'action' => 'accept'
        ])->setName('applicationAccept');

        $router->add('/application/{id}/reject', [
            'controller' => 'application',
            'action' => 'reject'
        ])->setName('applicationReject');

        $router->add('/application/{id}/delete', [
            'controller' => 'application',
            'action' => 'delete'
        ])->setName('applicationDelete');

        return $router;
    });

    $di->set('view', function () {
        $view = new View();
        $view->setViewsDir(__DIR__ . '/../../apps/Backend/Views/');

        return $view;
    });

    $di->set('url', function () {
        $url = new UrlProvider();
        $url->setBaseUri('/admin/');

        return $url;
    });

    // Handle the request
    $application = new Application($di);

    echo $application->handle()->getContent();

// } catch (\Exception $e) {
//     $di->get('logger')->addError("application->handle()->getContent() has thrown an exception. message:$e");
// }