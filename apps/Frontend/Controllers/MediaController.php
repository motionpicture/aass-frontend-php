<?php
namespace Aass\Frontend\Controllers;

use Aass\Frontend\Models\Media as MediaModel;
use Aass\Frontend\Forms\MediaForm as MediaForm;
use Aass\Frontend\Models\Application as ApplicationModel;
use WindowsAzure\MediaServices\Models\Asset;
use WindowsAzure\MediaServices\Models\AccessPolicy;
use WindowsAzure\MediaServices\Models\Locator;
use WindowsAzure\Blob\Models\Block;

class MediaController extends BaseController
{
    const BLOCK_ID_PREFIX = 'block-';
    const MAX_BLOCK_SIZE = 4194304; // 最大で4MB
    const BLOCK_ID_PADDING = 6;

    public function indexAction()
    {
        $mediaModel = new MediaModel;
        $this->view->medias = $mediaModel->getListByEventId($this->auth->getId());

        $applicationModel = new ApplicationModel;
        $this->view->application = $applicationModel->getByEventId($this->auth->getId());
    }

    /**
     * 動画登録
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
     */
    public function editAction()
    {
        $form = new MediaForm(null, [
            'blockMaxSize' => self::MAX_BLOCK_SIZE
        ]);

        if ($this->mode == 'dev') {
            $form->setDefaults([
                'title' => 'dev',
                'description' => 'dev',
                'uploaded_by' => 'tetsu'
            ]);
        }

        if ($this->dispatcher->getParam('id')) {
            $mediaModel = new MediaModel;
            $media = $mediaModel->getById($this->dispatcher->getParam('id'));
            $form->setDefaults($media);
        }

        $this->view->form = $form;
    }

    /**
     * アセットを作成する
     */
    public function createAssetAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $messages = [];

        $filename = uniqid($this->auth->getUserId());

        try {
            $asset = new Asset(Asset::OPTIONS_NONE);
            $asset->setName($filename);
            $asset = $this->mediaService->createAsset($asset);
            $this->logger->addInfo("asset has been created. asset:" . var_export($asset, true));

            $isSuccess = true;
        } catch (\Exception $e) {
            $this->logger->addError("createAsset throw exception. message:{$e}");
            $messages[] = 'ファイルのアップロードに失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'messages' => $messages,
            'params' => [
                'assetId' => $asset->getId(),
                'container' => basename($asset->getUri()),
                'filename' => $filename,
            ],
        ]);

        return false;
    }

    /**
     * ファイルを追加アップロードする
     */
    public function appendFileAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $messages = [];

        $this->logger->addDebug('POST:' . var_export($_POST, true));
        $this->logger->addDebug('FILE:' . var_export($_FILES, true));
        try {
            $params = $this->request->getPost();

            // This is for large or failsafe upload
            $end       = 0;
            $counter   = 0;
            $body      = '';
            // if threshold is lower than 4mb, honor threshold, else use 4mb
            $blockSize = self::MAX_BLOCK_SIZE;
            $blob = "{$params['filename']}.{$params['extension']}";
            $container = $params['container'];
            $content = file_get_contents($_FILES['file']['tmp_name']);
            while(!$end) {
                if (is_resource($content)) {
                    $body = fread($content, $blockSize);
                    if (feof($content)) {
                        $end = 1;
                    }
                } else {
                    if (strlen($content) <= $blockSize) {
                        $body = $content;
                        $end = 1;
                    } else {
                        $body = substr($content, 0, $blockSize);
                        $content = substr_replace($content, '', 0, $blockSize);
                    }
                }
                $blockId = $this->generateBlockId($params['index'] + $counter);
                $this->logger->addDebug("creating BlobBlock... blockId:{$blockId}");
                $this->blobService->putBlock($container, $blob, $blockId, $body);
//                 $this->blobService2->createBlobBlock($container, $blob, $blockId, $body);
                $this->logger->addDebug("BlobBlock created. blockId:{$blockId}");
                $counter++;
            }

            $isSuccess = true;
        } catch (\Exception $e) {
            $this->logger->addError("appendFile throw exception. message:{$e}");
            $messages[] = 'ファイルのアップロードに失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'messages' => $messages,
        ]);

        return false;
    }

    /**
     * ブロブブロックをコミットする
     */
    public function commitFileAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $messages = [];

        try {
            $params = $this->request->getPost();
            $this->logger->addInfo("comitting file... {$params['asset_id']}/{$params['filename']}");
            $blob = "{$params['filename']}.{$params['extension']}";

            $blockIds  = [];
            for ($i=0; $i<$params['blockCount']; $i++) {
                $blockIds[] = $this->generateBlockId($i);
            }
            $this->blobService->putBlockList($params['container'], $blob, $blockIds);
            $this->logger->addInfo("BlobBlocks commited. assetId:{$params['asset_id']}");

            // ファイル メタデータの生成
            $this->mediaService->createFileInfos($params['asset_id']);
            // ここまできて初めて、アセットの準備が完了したことになる
            $this->logger->addInfo("inputAsset prepared completely. asset:{$params['asset_id']}");

            $isSuccess = true;
        } catch (\Exception $e) {
            $this->logger->addError("commitFile throw exception. message:{$e}");
            $messages[] = 'ファイルのアップロードに失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'messages' => $messages
        ]);

        return false;
    }

    /**
     * ファイルをアップロードする
     */
    public function createAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSaved = false;
        $messages = [];

        $form = new MediaForm();
        $this->logger->addDebug(print_r($this->request->getPost(), true));

        if ($form->isValid($this->request->getPost())) {
            try {
                $params = $this->request->getPost();
                $params['event_id'] = $this->auth->getId();
                $mediaModel = new MediaModel;
                if ($mediaModel->update($params)) {
                    $isSaved = true;
                    $this->logger->addInfo("media updated. media:" . var_export($params, true));
                }
            } catch (\Exception $e) {
                $this->logger->addError("mediaModel->update throw exception. message:{$e}");
                $messages[] = '動画の登録に失敗しました';
            }
        } else {
            foreach ($form->getMessages() as $message) {
                $messages[] = "{$message}";
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

    /**
     * ブロブブロックIDを生成する
     * 
     * @param int $blockCount
     */
    private function generateBlockId($blockCount)
    {
        return base64_encode(self::BLOCK_ID_PREFIX . str_pad($blockCount, self::BLOCK_ID_PADDING, '0', STR_PAD_LEFT));
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
            $media = $mediaModel->getById($this->dispatcher->getParam('id'));
            $isSuccess = $mediaModel->deleteById($this->dispatcher->getParam('id'));
        } catch (\Exception $e) {
            $this->logger->addError("mediaModel->deleteById throw exception. message:{$e}");
            $message = '削除に失敗しました';
        }

        // TODO アセットはバッチ処理で削除
//         try {
//             $this->mediaService->deleteAsset($media['asset_id']);
//         } catch (\Exception $e) {
//             $this->logger->addError("mediaModel->deleteAsset throw exception. message:{$e}");
//         }

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

    /**
     * 申請する
     */
    public function applyAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $message = '';

        try {
            $applicationModel = new ApplicationModel;
            $isSuccess = $applicationModel->create($this->dispatcher->getParam('id'));
        } catch (\Exception $e) {
            $this->logger->addError("applicationModel->create throw exception. message:{$e}");
            $message = '申請に失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'message' => $message
        ]);

        return false;
    }
}