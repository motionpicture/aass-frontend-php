<?php
namespace Aass\Cli\Tasks;

class MainTask extends BaseTask
{
    public function mainAction()
    {
        $this->logger->addDebug("\nThis is the default task and the default action \n");
    }

    /**
     * @param array $params
     */
    public function testAction(array $params)
    {
        echo sprintf('best regards, %s', $params[1]) . PHP_EOL;
        $this->logger->addDebug(sprintf('hello %s', $params[0]));
    }
}