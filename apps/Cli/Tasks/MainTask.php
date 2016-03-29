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

    public function listContainersAction()
    {
        $containers = $this->blobService->listContainers();
        var_dump($containers->Containers->Container[0]);
    }

    public function listBlobsAction()
    {
        $blobs = $this->blobService->listBlobs('test');
        var_dump($blobs);
    }

    public function putBlobAction()
    {
        $container = 'mycontainer';
        $blob = 'test.txt';
        $body = 'test';
        $this->blobService->putBlob($container, $blob, $body);
//         $this->blobService2->createBlockBlob($container, $blob, $body);
    }

    public function copyFileAction()
    {
        $source = 'https://mediasvcdtgv96fwgm0zz.blob.core.windows.net/asset-eb701573-cc18-4a7c-b9ec-3dbeb54ba45a/motionpicture56f606cab3b3e.mp4?sv=2012-02-12&sr=c&si=266d6b99-53c3-4d53-b80c-8e1cac824f7e&sig=UuI02deniapBdMFjeqtj0z%2Fw2YuJkFdaj52Jlj3HAXY%3D&st=2016-03-28T05%3A08%3A19Z&se=2116-03-04T05%3A08%3A19Z';
        $this->fileService->copyFile($source, 'test/test' . date('YmdHis') . '.mp4');
    }

    public function getFilePropertiesAction()
    {
        $properties = $this->fileService->getFileProperties('jpeg2000/test.jpeg2000');
//         $properties = $this->fileService->getFileProperties('jpeg2000/test');
        var_dump($properties);
    }
}