<?php
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;

$di->set('mode', function()
{
    $modeFile = __DIR__ . '/../../mode.php';
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
    $configs = new Phalcon\Config\Adapter\Ini(__DIR__ . '/../../config/config.ini');
    return $configs[$di->get('mode')];
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

    return \Swift_Mailer::newInstance($transport);
});

$di->set('db', function() use ($di)
{
    return new DbAdapter([
        'host' => $di->get('config')->get('db_host'),
        'username' => $di->get('config')->get('db_username'),
        'password' => $di->get('config')->get('db_password'),
        'dbname' => $di->get('config')->get('db_dbname'),
        'charset' => 'utf8',
        'persistent' => false,
        'options' => [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]
    ]);
});

// $di->set('azureTable', function() use ($di)
// {
//     $connectionString =  sprintf(
//         'DefaultEndpointsProtocol=%s;AccountName=%s;AccountKey=%s',
//         'https',
//         $di->get('config')->get('storage_account_name'),
//         $di->get('config')->get('storage_account_key')
//     );
//     return \WindowsAzure\Common\ServicesBuilder::getInstance()->createTableService($connectionString);
// });

$di->set('mediaService', function() use ($di)
{
    $settings = new \WindowsAzure\Common\Internal\MediaServicesSettings(
        $di->get('config')->get('media_service_account_name'),
        $di->get('config')->get('media_service_account_key'),
        \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_URL,
        \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_OAUTH_URL
    );
    return \WindowsAzure\Common\ServicesBuilder::getInstance()->createMediaServicesService($settings);
});

$di->set('blobService', function() use ($di)
{
    $connectionString =  sprintf(
        'DefaultEndpointsProtocol=%s;AccountName=%s;AccountKey=%s',
        'https',
        $di->get('config')->get('storage_account_name'),
        $di->get('config')->get('storage_account_key')
    );

    return \WindowsAzure\Common\ServicesBuilder::getInstance()->createBlobService($connectionString);
});

$di->set('fileService', function() use ($di)
{
    return new \Aass\Common\AzureFileService(
        $di->get('config')->get('storage_account_name'),
        $di->get('config')->get('storage_account_key')
    );
});
?>