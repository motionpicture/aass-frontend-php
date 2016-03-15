<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$_SERVER['PHP_SELF'] = '/index.php';

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../apps/' . strtr(str_replace('Aass\\', '', $class), '\\', DIRECTORY_SEPARATOR) . '.php';
    if (is_readable($file)) {
        require_once $file;
        return;
    }
});

use Phalcon\Loader;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Application;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Url as UrlProvider;
use Phalcon\Session\Adapter\Files as Session;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Events\Manager as EventsManager;

// try {
    // Register an autoloader
//     $loader = new Loader();
//     $loader->registerDirs(array(
//         '../apps/Frontend/Controllers/',
//         '../apps/Frontend/Models/'
//     ))->register();

    // Create a DI
    $di = new FactoryDefault();
    include __DIR__ . '/../apps/Common/dependencies.php';

    // Start the session the first time a component requests the session service
    $di->set('session', function ()
    {
        $session = new Session();
        $session->start();

        return $session;
    });

    $di->set('auth', function()
    {
        return new \Aass\Frontend\Plugins\SecurityPlugin;
    });

    $di->set('dispatcher', function()
    {
        // Create an events manager
        $eventsManager = new EventsManager();
        $eventsManager->attach('dispatch:beforeExecuteRoute', new \Aass\Frontend\Plugins\SecurityPlugin);
        // Handle exceptions and not-found exceptions using NotFoundPlugin
//         $eventsManager->attach('dispatch:beforeException', new NotFoundPlugin);

        $dispatcher = new Dispatcher();
        $dispatcher->setEventsManager($eventsManager);
        $dispatcher->setDefaultNamespace('Aass\Frontend\Controllers');

        return $dispatcher;
    });

    // logger
    $di->set('logger', function() use ($di)
    {
        // プロセスID
        $pid = getmypid();
        $pid = (!$pid) ? 'false' : $pid;

        $formatter = new \Monolog\Formatter\LineFormatter(null, null, true); // 第３引数で改行を有効に
        $stream = new \Monolog\Handler\StreamHandler(
            __DIR__ . "/../logs/{$di->get('mode')}/Frontend/" . date('Ymd') . ".log",
            ($di->get('mode') == 'dev') ? \Monolog\Logger::DEBUG : \Monolog\Logger::INFO
        );
        $stream->setFormatter($formatter);
        $logger = new \Monolog\Logger("AassFrontend[{$di->get('mode')}] [PID:{$pid}]");
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

        $router->add('/medias', [
            'controller' => 'media',
            'action' => 'index'
        ])->setName('medias');

        $router->add('/media/new', [
            'controller' => 'media',
            'action' => 'new'
        ])->setName('mediaNew');

        $router->add('/media/create', [
            'controller' => 'media',
            'action' => 'create'
        ])->setName('mediaCreate');

        $router->add('/media/createAsset', [
            'controller' => 'media',
            'action' => 'createAsset'
        ])->setName('mediaCreateAsset');

        $router->add('/media/appendFile', [
            'controller' => 'media',
            'action' => 'appendFile'
        ])->setName('mediaAppendFile');

        $router->add('/media/commitFile', [
            'controller' => 'media',
            'action' => 'commitFile'
        ])->setName('mediaCommitFile');

        $router->add('/media/{id}/edit', [
            'controller' => 'media',
            'action' => 'edit'
        ])->setName('mediaEdit');

        $router->add('/media/{id}/download', [
            'controller' => 'media',
            'action' => 'download'
        ])->setName('mediaDownload');

        $router->add('/media/{id}/delete', [
            'controller' => 'media',
            'action' => 'delete'
        ])->setName('mediaDelete');

        $router->add('/media/{id}/apply', [
            'controller' => 'media',
            'action' => 'apply'
        ])->setName('mediaApply');

        return $router;
    });

    // Setup the view component
    $di->set('view', function () {
        $view = new View();
        $view->setViewsDir(__DIR__ . '/../apps/Frontend/Views/');
        return $view;
    });

    // Setup a base URI so that all generated URIs include the "tutorial" folder
    $di->set('url', function () {
        $url = new UrlProvider();
//         $url->setBaseUri('/tutorial/');
        return $url;
    });

    // Handle the request
    $application = new Application($di);

    echo $application->handle()->getContent();

// } catch (\Exception $e) {
//     $di->get('logger')->addError("application->handle()->getContent() has thrown an exception. message:$e");
// }