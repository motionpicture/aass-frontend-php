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
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Session\Adapter\Files as Session;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Events\Manager as EventsManager;

try {
    // Register an autoloader
//     $loader = new Loader();
//     $loader->registerDirs(array(
//         '../apps/Frontend/Controllers/',
//         '../apps/Frontend/Models/'
//     ))->register();

    // Create a DI
    $di = new FactoryDefault();

    $di->set('mode', function()
    {
        $modeFile = __DIR__ . '/../mode.php';
        if (false === is_file($modeFile)) {
            exit('The application "mode file" does not exist.');
        }
        include $modeFile;
        if (empty($mode)) {
            exit('The application "mode" does not exist.');
        }

        return $mode;
    });

    // Load the configuration file
    $di->set('config', function() use ($di)
    {
        $configs = new Phalcon\Config\Adapter\Ini(__DIR__ . '/../config/config.ini');
        return $configs[$di->get('mode')];
    });

    // Start the session the first time a component requests the session service
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

    // mailer
    $di->set('mailer', function() use ($di)
    {
        \Swift_Preferences::getInstance()->setCharset('UTF-8');
        if ($di->get('mode') == 'dev') {
            $transport = \Swift_SmtpTransport::newInstance()
                ->setHost('smtp.gmail.com')
                ->setPort(465)
                ->setEncryption('ssl')
                ->setUsername('noreply@motionpicture.jp')
                ->setPassword('b7Jb7%Cl');
        } else {
            $transport = \Swift_SmtpTransport::newInstance()
                ->setHost('smtp.gmail.com')
                ->setPort(465)
                ->setEncryption('ssl')
                ->setUsername('noreply@motionpicture.jp')
                ->setPassword('b7Jb7%Cl');
        }
        $mailer = \Swift_Mailer::newInstance($transport);

        return $mailer;
    });

    // db
    $di->set('azureTable', function() use ($di)
    {
        $connectionString =  sprintf(
            'DefaultEndpointsProtocol=%s;AccountName=%s;AccountKey=%s',
            'https',
            $di->get('config')['storage_account_name'],
            $di->get('config')['storage_account_key']
        );

        return \WindowsAzure\Common\ServicesBuilder::getInstance()->createTableService($connectionString);
    });

    // mediaService
    $di->set('mediaService', function() use ($di)
    {
        $settings = new \WindowsAzure\Common\Internal\MediaServicesSettings(
            $di->get('config')['media_service_account_name'],
            $di->get('config')['media_service_account_key'],
            \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_URL,
            \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_OAUTH_URL
        );
        return \WindowsAzure\Common\ServicesBuilder::getInstance()->createMediaServicesService($settings);
    });

    // mediaService
    $di->set('blobService', function() use ($di)
    {
        $connectionString =  sprintf(
            'DefaultEndpointsProtocol=%s;AccountName=%s;AccountKey=%s',
            'https',
            $di->get('config')['storage_account_name'],
            $di->get('config')['storage_account_key']
        );
        return \WindowsAzure\Common\ServicesBuilder::getInstance()->createBlobService($connectionString);
    });

    $di->set('router', function()
    {
        $router = new \Phalcon\Mvc\Router();

        $router->add('/medias', [
            'controller' => 'media',
            'action' => 'index'
        ]);

        $router->add('/media/new/progress/{name}', [
            'controller' => 'media',
            'action' => 'newProgress'
        ]);

        $router->add('/media/{rowKey}/edit', [
            'controller' => 'media',
            'action' => 'edit'
        ]);

        $router->add('/media/{rowKey}/download', [
            'controller' => 'media',
            'action' => 'download'
        ]);

        $router->add('/media/{rowKey}/delete', [
            'controller' => 'media',
            'action' => 'delete'
        ]);

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

} catch (\Exception $e) {
    $di->get('logger')->addError("application->handle()->getContent() has thrown an exception. message:$e");
}