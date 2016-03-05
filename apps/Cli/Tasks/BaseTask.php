<?php
namespace Aass\Cli\Tasks;

class BaseTask extends \Phalcon\Cli\Task
{
    /**
     * エラー通知
     *
     * @param string $message
     * @return none
     */
    protected function reportError($message)
    {
        $errorsIniArray = parse_ini_file(__DIR__ . '/../config/errors.ini', true);
        $errorsConfig = $errorsIniArray[$this->getMode()];

        $host = self::$host;
        $to = implode(',', $errorsConfig['to']);
        $subject = $errorsConfig['subject'];
        $headers = "From: webmaster@{$host}" . "\r\n"
                 . "Reply-To: webmaster@{$host}";
        if (!mail($to, $subject, $message, $headers)) {
            $this->logger->log('reportError fail. $message:' . print_r($message, true));
        }
    }
}