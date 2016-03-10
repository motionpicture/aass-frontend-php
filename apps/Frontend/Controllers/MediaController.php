<?php
namespace Aass\Frontend\Controllers;

use \Aass\Frontend\Models\Media as MediaModel;
use \WindowsAzure\MediaServices\Models\Asset;
use \WindowsAzure\MediaServices\Models\AccessPolicy;
use \WindowsAzure\MediaServices\Models\Locator;
use \WindowsAzure\MediaServices\Models\Job;
use \WindowsAzure\MediaServices\Models\Task;
use \WindowsAzure\MediaServices\Models\TaskOptions;

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
            'event_id' => $this->auth->getId(),
            'title' => '',
            'description' => '',
            'uploaded_by' => '',
            'filename' => '',
            'extension' => '',
            'size' => '',
            'playtime_string' => '',
            'playtime_seconds' => '',
            'asse_id' => '',
        ];

        if ($this->dispatcher->getParam('id')) {
            try {
                $mediaModel = new MediaModel;
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
     * ファイルをアップロードする
     * 
     * @throws \Exception
     */
    public function createAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSaved = false;
        $messages = [];
        $defaults = [
            'id' => '',
            'event_id' => $this->auth->getId(),
            'title' => '',
            'description' => '',
            'uploaded_by' => '',
            'filename' => '',
            'extension' => '',
            'size' => '',
            'playtime_string' => '',
            'playtime_seconds' => '',
            'asse_id' => '',
        ];

        $this->logger->addDebug(print_r($_POST, true));
        $this->logger->addDebug(print_r($_FILES, true));
        $defaults = array_merge($defaults, $_POST);

        $isNew = (!$defaults['id']);

        if (!$defaults['title']) {
            $messages[] = '動画名を入力してください';
        }
        if (!$defaults['description']) {
            $messages[] = '動画概要を選択してください';
        }
        if (!$defaults['uploaded_by']) {
            $messages[] = '動画登録者名を選択してください';
        }
        if ($isNew) {
            if ($_FILES['file']['size'] <= 0) {
                $messages[] = 'ファイルを選択してください';
            }
        }

        if (empty($messages)) {
            try {
                if ($isNew) {
                    $defaults = array_merge($defaults, $this->createFromUpload());
                }

                $mediaModel = new MediaModel;
                if ($mediaModel->update($defaults)) {
                    $isSaved = true;
                    $this->logger->addInfo("media created.");
                }
            } catch (\Exception $e) {
                $this->logger->addError("mediaModel->create throw exception. message:{$e}");
                $messages[] = '更新に失敗しました';
            }
        }

        echo json_encode([
            'isSuccess' => $isSaved,
            'messages' => $messages,
        ]);

        return false;
    }

    private function createFromUpload()
    {
        $params = [
            'filename' => uniqid($this->auth->getUserId())
        ];

        try {
            // メディアサービスへ資産としてアップロードする
            $extension = pathinfo(basename($_FILES['file']['name']), PATHINFO_EXTENSION);
//             $size = $_FILES['file']['size'];
            $params['extension'] = $extension;
            $params['asset_id'] = $this->ingestAsset($params['filename'], $_FILES['file']['tmp_name'], $extension);
            $this->logger->addInfo("ingestAsset Success. assetId:{$params['asset_id']}");
        } catch (\Exception $e) {
            $this->logger->addError("fail in ingestAsset {$e}");
            throw $e;
        }

        if (isset($params['asset_id']) && $params['asset_id']) {
            try {
                // 再生時間を取得
                $getID3 = new \getID3;
                $fileInfo = $getID3->analyze($_FILES['file']['tmp_name']);
                if (isset($fileInfo['playtime_string'])) {
                    $params['playtime_string'] = $fileInfo['playtime_string'];
                }
                if (isset($fileInfo['playtime_seconds'])) {
                    $params['playtime_seconds'] = $fileInfo['playtime_seconds'];
                }
            } catch (\Exception $e) {
                $this->logger->addError("getID3->analyze throw exception. message:{$e}");
            }
        }

        return $params;
    }

    /**
     * 資産をインジェストする
     *
     * @see http://msdn.microsoft.com/ja-jp/library/jj129593.aspx
     * @return string
     */
    private function ingestAsset($filename, $path, $extension)
    {
        $asset = null;
        $assetId = null;
        $isCompleted = false;

        try {
            // 資産を作成する
            $asset = new Asset(Asset::OPTIONS_NONE);
            $asset->setName($filename);
            $asset = $this->mediaService->createAsset($asset);
            $assetId = $asset->getId();
            $this->logger->addInfo("asset has been created. asset:{$assetId}");
        } catch (\Exception $e) {
            $this->logger->addError('createAsset throw exception. message:' . $e->getMessage());
        }

        if (!is_null($assetId)) {
            $isUploaded = false;
            try {
                // ファイルのアップロードを実行する
                $file = "{$filename}.{$extension}";
                $this->logger->addInfo("creating BlockBlob... path:{$path}");
                $content = fopen($path, 'rb');
                $result = $this->blobService->createBlockBlob(basename($asset->getUri()), $file, $content);
                $this->logger->addDebug('BlockBlob has been created. result:' . var_export($result, true));
                fclose($content);

                $isUploaded = true;
            } catch (\Exception $e) {
                $this->logger->addError("upload2storage throw exception. message:{$e}");
            }
            $this->logger->addInfo('isUploaded:' . var_export($isUploaded, true));

            if ($isUploaded) {
                try {
                    // ファイル メタデータの生成
                    $this->mediaService->createFileInfos($asset);

                    // ここまできて初めて、アセットの準備が完了したことになる
                    $isCompleted = true;
                    $this->logger->addInfo("inputAsset has been prepared completely. asset:{$assetId}");
                } catch (\Exception $e) {
                    $this->logger->addError("createFileInfos throw exception. message:{$e}");
                }
            }
        }

        if (!$isCompleted) {
            if (!is_null($assetId)) {
                // TODO アセット削除
                $assetId = null;
            }
        }

        return $assetId;
    }

    /**
     * 動画アップロード進捗を取得する
     */
    public function newProgressAction()
    {
        echo json_encode(null);
        return;
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