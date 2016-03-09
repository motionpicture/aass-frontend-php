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

    public function initDbAction(array $params)
    {
        $query = file_get_contents(__DIR__ . '/../../../config/schema.sql');

        try {
            $this->db->begin();
            $this->db->execute($query);
            $this->db->commit();
        } catch (\Exception $e) {
            $this->logger->addInfo("{$e}");
            $this->db->rollback();
        }
        var_dump($this->db->listTables());
    }
}