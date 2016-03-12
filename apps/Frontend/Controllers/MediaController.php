<?php
namespace Aass\Frontend\Controllers;

use \Aass\Frontend\Models\Media as MediaModel;
use \WindowsAzure\MediaServices\Models\Asset;
use \WindowsAzure\MediaServices\Models\AccessPolicy;
use \WindowsAzure\MediaServices\Models\Locator;
use \WindowsAzure\Blob\Models\Block;

class MediaController extends BaseController
{
    public function indexAction()
    {
        try {
            $mediaModel = new MediaModel;
            $medias = $mediaModel->getListByEventId($this->auth->getId());
            $this->view->medias = $medias;
        } catch (\Exception $e) {
            $this->logger->addError("fail in getListByUserId. message:{$e}");
            throw $e;
        }
    }

    /**
     * 動画登録
     * 
     * @throws \Exception
     */
    public function newAction()
    {
        return $this->dispatcher->forward([
            'contorller' => 'media',
            'action' => 'edit',
        ]);
    }

    /**
     * 動画編集
     *
     * @throws \Exception
     */
    public function editAction()
    {
        $messages = [];
        $defaults = [
            'id' => '',
            'title' => '',
            'description' => '',
            'uploadedBy' => '',
        ];

        if ($this->dispatcher->getParam('id')) {
            try {
                $mediaModel = new MediaModel;
                $media = $mediaModel->getById($this->dispatcher->getParam('id'));
                $defaults = [
                    'id' => $media['id'],
                    'title' => $media['title'],
                    'description' => $media['description'],
                    'uploadedBy' => $media['uploaded_by'],
                ];
                $defaults = array_merge($defaults, $mediaModel->getById($this->dispatcher->getParam('id')));
            } catch (\Exception $e) {
                $this->logger->addError("getById throw exception. message:{$e}");
                throw $e;
            }
        }

        $this->view->messages = $messages;
        $this->view->defaults = $defaults;
    }

    /**
     * アセットを作成する
     *
     * @throws \Exception
     */
    public function createAssetAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $messages = [];

        $filename = uniqid($this->auth->getUserId());

        try {
            $assetId = $this->createAsset($filename);
            $isSuccess = true;
        } catch (\Exception $e) {
            $this->logger->addError("createAsset throw exception. message:{$e}");
            $messages[] = 'ファイルのアップロードに失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'messages' => $messages,
            'params' => [
                'assetId' => $assetId,
                'filename' => $filename,
            ],
        ]);

        return false;
    }

    /**
     * ファイルを追加アップロードする
     *
     * @throws \Exception
     */
    public function appendFileAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $messages = [];

        try {
            $dataparts = explode(',', $_POST['file'], 2);
            $content = base64_decode($dataparts[1]);
            $counter = $this->appendFile($_POST['assetId'], $_POST['filename'], $content, $_POST['extension'], $_POST['counter'], $_POST['eof']);

            $isSuccess = true;
        } catch (\Exception $e) {
            $this->logger->addError("appendFile throw exception. message:{$e}");
            $messages[] = 'ファイルのアップロードに失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'messages' => $messages,
            'params' => [
                'counter' => $counter
            ]
        ]);

        return false;
    }

    /**
     * ファイルをアップロードする
     * 
     * @throws \Exception
     */
    public function createAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSaved = false;
        $messages = [];

        $this->logger->addDebug(print_r($_POST, true));

        $isNew = (!$_POST['id']);

        if (empty($messages)) {
            try {
                $params = $_POST;
                $params['eventId'] = $this->auth->getId();
                $mediaModel = new MediaModel;
                if ($mediaModel->update($params)) {
                    $isSaved = true;
                    $this->logger->addInfo("media updated.");
                }
            } catch (\Exception $e) {
                $this->logger->addError("mediaModel->update throw exception. message:{$e}");
                $messages[] = '更新に失敗しました';
            }
        }

        echo json_encode([
            'isSuccess' => $isSaved,
            'messages' => $messages,
        ]);

        return false;
    }

//     private function createFromUpload($extension)
//     {
//         if (isset($params['asset_id']) && $params['asset_id']) {
//             try {
//                 // 再生時間を取得
//                 $getID3 = new \getID3;
//                 $fileInfo = $getID3->analyze($_FILES['file']['tmp_name']);
//                 if (isset($fileInfo['playtime_string'])) {
//                     $params['playtime_string'] = $fileInfo['playtime_string'];
//                 }
//                 if (isset($fileInfo['playtime_seconds'])) {
//                     $params['playtime_seconds'] = $fileInfo['playtime_seconds'];
//                 }
//             } catch (\Exception $e) {
//                 $this->logger->addError("getID3->analyze throw exception. message:{$e}");
//             }
//         }

//         return $params;
//     }

    private function appendFile($assetId, $filename, $content, $extension, $counter, $eof)
    {
        $asset = null;
        $isComitted = false;

        $this->logger->addInfo("appending file... {$assetId}/{$filename}");

        $asset = $this->mediaService->getAsset($assetId);

        if ($asset) {
            $blob = "{$filename}.{$extension}";

            $end = 0;
            $body = '';
            // if threshold is lower than 4mb, honor threshold, else use 4mb
            $blockSize = 4194304;
            while(!$end) {
                $this->logger->addDebug("counter:{$counter}");

                if (is_resource($content)) {
                    $this->logger->addDebug('reaing file...');
                    $body = fread($content, $blockSize);
                    if (feof($content)) {
                        $end = 1;
                    }
                } else {
                    $this->logger->addDebug('content size:' . strlen($content));
                    if (strlen($content) <= $blockSize) {
                        $body = $content;
                        $end = 1;
                    } else {
                        $body = substr($content, 0, $blockSize);
                        $content = substr_replace($content, '', 0, $blockSize);
                    }
                }

                $block = new Block();
                $block->setBlockId(base64_encode(str_pad($counter++, '0', 6)));
                $block->setType('Uncommitted');
                $this->blobService->createBlobBlock(basename($asset->getUri()), $blob, $block->getBlockId(), $body);
                $this->logger->addInfo("BlobBlock created. blockId:{$block->getBlockId()}");
            }

            // 最後のファイル追加であればコミット
            if ($eof) {
                $blockIds  = [];
                for ($i=0; $i<$counter; $i++) {
                    $block = new Block();
                    $block->setBlockId(base64_encode(str_pad($i, '0', 6)));
                    $block->setType('Uncommitted');
                    $this->logger->addDebug("comitting... blockId:{$block->getBlockId()}");
                    array_push($blockIds, $block);
                }
                $response = $this->blobService->commitBlobBlocks(basename($asset->getUri()), $blob, $blockIds);
                $this->logger->addInfo('BlobBlocks commited.');
                $isComitted = true;
            }

            if ($isComitted) {
                // ファイル メタデータの生成
                $this->mediaService->createFileInfos($asset);
                // ここまできて初めて、アセットの準備が完了したことになる
                $this->logger->addInfo("inputAsset prepared completely. asset:{$assetId}");
            }
        }

        return $counter;
    }

    /**
     * アセットを作成する
     * 
     * @param string $filename
     * @param string $extension
     */
    private function createAsset($filename)
    {
        $asset = new Asset(Asset::OPTIONS_NONE);
        $asset->setName($filename);
        $asset = $this->mediaService->createAsset($asset);
        $assetId = $asset->getId();
        $this->logger->addInfo("asset has been created. asset:" . var_export($asset, true));

        return $assetId;
    }

    /**
     * 削除する
     */
    public function deleteAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $message = '';

        try {
            $mediaModel = new MediaModel;
            $isSuccess = $mediaModel->deleteById($this->dispatcher->getParam('id'));
        } catch (\Exception $e) {
            $this->logger->addError("mediaModel->deleteById throw exception. message:{$e}");
            $message = '削除に失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'message' => $message
        ]);

        return false;
    }

    /**
     * ダウンロード
     * 
     * @throws \Exception
     */
    public function downloadAction()
    {
        try {
            $mediaModel = new MediaModel;
            $media = $mediaModel->getById($this->dispatcher->getParam('id'));

            // 特定のAssetに対して、同時に5つを超える一意のLocatorを関連付けることはできない
            // 万が一SASロケーターがあれば削除
            $oldLocators = $this->mediaService->getAssetLocators($media['asset_id']);
            foreach ($oldLocators as $oldLocator) {
                if ($oldLocator->getType() == Locator::TYPE_SAS) {
                    // 期限切れであれば削除
                    $expiration = strtotime('+9 hours', $oldLocator->getExpirationDateTime()->getTimestamp());
                    if ($expiration < strtotime('now')) {
                        $this->mediaService->deleteLocator($oldLocator);
                        $this->logger->addDebug('SAS locator has been deleted. locator: '. print_r($oldLocator, true));
                    }
                }
            }

            // 読み取りアクセス許可を持つAccessPolicyの作成
            $accessPolicy = new AccessPolicy('DownloadPolicy');
            $accessPolicy->setDurationInMinutes(10); // 10分間有効
            $accessPolicy->setPermissions(AccessPolicy::PERMISSIONS_READ);
            $accessPolicy = $this->mediaService->createAccessPolicy($accessPolicy);

            // アセットを取得
            $asset = $this->mediaService->getAsset($media['asset_id']);
            $this->logger->addDebug('asset:' . print_r($asset, true));

            // ダウンロードURLの作成
            $locator = new Locator(
                $asset,
                $accessPolicy,
                Locator::TYPE_SAS
            );
            $locator->setName('DownloadLocator_' . $asset->getId());
            $locator->setStartTime(new \DateTime('now -5 minutes'));
            $locator = $this->mediaService->createLocator($locator);
            $this->logger->addDebug(print_r($locator, true));

            $fileName = "{$media['filename']}.{$media['extension']}";
            $path = "{$locator->getBaseUri()}/{$fileName}{$locator->getContentAccessComponent()}";
            $this->logger->addInfo("path:{$path}");
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Type: application/octet-stream');
            if (!readfile($path)) {
                throw(new \Exception("Cannot read the file({$path})"));
            }

            // ロケーター削除
            $this->mediaService->deleteLocator($locator);

            return false;
        } catch (\Exception $e) {
            $this->logger->addError("download throw exception. message:{$e}");
            throw $e;
        }

        throw new \Exception('予期せぬエラー');
    }
}