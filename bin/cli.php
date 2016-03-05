<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$startTime = microtime(true);
$startMem = memory_get_usage();

/**
 * Process the console arguments
 */
$arguments = [
    'task' => 'main',
    'action' => 'main',
    'params' => []
];
foreach ($argv as $k => $arg) {
    if ($k == 1) {
        $arguments['task'] = $arg;
    } elseif ($k == 2) {
        $arguments['action'] = $arg;
    } elseif ($k >= 3) {
        $arguments['params'][] = $arg;
    }
}

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../apps/' . strtr(str_replace('Aass\\', '', $class), '\\', DIRECTORY_SEPARATOR) . '.php';
    if (is_readable($file)) {
        require_once $file;
        return;
    }
});




// Using the CLI factory default services container
$di = new Phalcon\Di\FactoryDefault\Cli();

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

$di->set('dispatcher', function()
{
    $dispatcher = new \Phalcon\CLI\Dispatcher();
    $dispatcher->setDefaultNamespace('Aass\Cli\Tasks');

    return $dispatcher;
});

// logger
$di->set('logger', function() use ($di, $arguments)
{
    // プロセスID
    $pid = getmypid();
    $pid = (!$pid) ? 'false' : $pid;

    $formatter = new \Monolog\Formatter\LineFormatter(null, null, true); // 第３引数で改行を有効に
    $stream = new \Monolog\Handler\StreamHandler(
        __DIR__ . "/../logs/{$di->get('mode')}/Cli/" . ucfirst($arguments['task']) . ucfirst($arguments['action']) . "/" . date('Ymd') . ".log",
        ($di->get('mode') == 'dev') ? \Monolog\Logger::DEBUG : \Monolog\Logger::INFO
    );
    $stream->setFormatter($formatter);
    $logger = new \Monolog\Logger("AassCli[{$di->get('mode')}] [PID:{$pid}]");
    $logger->pushHandler($stream);

    // 開発時は標準出力にも
    if ($di->get('mode') == 'dev') {
        $stream = new \Monolog\Handler\StreamHandler(
            'php://stdout',
            \Monolog\Logger::DEBUG
        );
        $stream->setFormatter($formatter);
        $logger->pushHandler($stream);
    }

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



// Create a console application
$console = new Phalcon\Cli\Console();
$console->setDI($di);

$di->get('logger')->addInfo('--------------------------------------------------------------------------------');
$di->get('logger')->addInfo('task is starting...');

set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext) use ($di)
{
    $di->get('logger')->addError("has got some errors. {$errno} {$errstr} {$errfile} {$errline}");
});

register_shutdown_function(function() use ($di)
{
    $di->get('logger')->addInfo('shutdown.');
});

try {
    $console->handle($arguments);
} catch (\Exception $e) {
    $di->get('logger')->addError("Called task has thrown an exception. message:$e");
}

$endMem = memory_get_usage();
$di->get('logger')->addInfo("MEM:" . ($endMem - $startMem) . "({$startMem}-{$endMem}) / peak:" . memory_get_peak_usage());
$di->get('logger')->addInfo("Total time:" . (microtime(true) - $startTime));
