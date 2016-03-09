<?php
namespace Aass\Frontend\Controllers;

use \Aass\Frontend\Models\Media as MediaModel;

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
            ini_get('session.upload_progress.name') => '',
            'title' => '',
            'description' => '',
            'uploaded_by' => '',
        ];

        $this->logger->addDebug(print_r($_POST, true));
        $defaults = array_merge($defaults, $_POST);

        if (!$defaults['title']) {
            $messages[] = '動画名を入力してください';
        }
        if (!$defaults['description']) {
            $messages[] = '動画概要を選択してください';
        }
        if (!$defaults['uploaded_by']) {
            $messages[] = '動画登録者名を選択してください';
        }
        if ($_FILES['file']['size'] <= 0) {
            $messages[] = 'ファイルを選択してください';
        }

        if (empty($messages)) {
            try {
                $userId = $this->session->get('auth')['UserId'];
                $id = uniqid($userId);
                $uploaddir = __DIR__ . "/../../../uploads/";
                $fileName = basename($_FILES['file']['name']);
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                $to = "{$uploaddir}{$id}.{$extension}";
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $to)) {
                    $egl = error_get_last();
                    $e = new \Exception("ファイルのアップロードでエラーが発生しました{$egl['message']}");
                    throw $e;
                }
                chmod($to, 0644);

                // メディアエンティティ作成
                $mediaModel = new \Aass\Frontend\Models\Media;
                if ($mediaModel->create($userId, $id, $extension, $defaults['title'], $defaults['description'], $defaults['uploadedBy'])) {
                    $isSaved = true;
                }
            } catch (\Exception $e) {
                $this->logger->addDebug(print_r($e, true));
                $messages[] = '予期せぬエラーが発生しました';
            }
        }

        echo json_encode([
            'isSuccess' => $isSaved,
            'messages' => $messages,
        ]);

        return false;
    }

    /**
     * 動画登録
     *
     * @throws \Exception
     */
    public function editAction()
    {
        $messages = [];
        $defaults = [
            ini_get('session.upload_progress.name') => uniqid('newMedia'),
            'title' => '',
            'description' => '',
            'uploaded_by' => '',
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
     * メディアを更新する
     *
     * @throws \Exception
     */
    public function updateAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $rowKey = $this->dispatcher->getParam('rowKey');

        $isSaved = false;
        $messages = [];
        $defaults = [
            'title' => '',
            'description' => '',
            'uploadedBy' => '',
        ];

        $this->logger->addDebug(print_r($_POST, true));
        $defaults = array_merge($defaults, $_POST);

        if (!$defaults['title']) {
            $messages[] = '動画名を入力してください';
        }
        if (!$defaults['description']) {
            $messages[] = '動画概要を選択してください';
        }
        if (!$defaults['uploadedBy']) {
            $messages[] = '動画登録者名を選択してください';
        }

        if (empty($messages)) {
            try {
                $mediaModel = new MediaModel;
                $mediaModel->update($this->session->get('auth')['UserId'], $rowKey, $defaults['title'], $defaults['description'], $defaults['uploadedBy']);
                $isSaved = true;
            } catch (\Exception $e) {
                $this->logger->addDebug(print_r($e, true));
                $messages[] = '予期せぬエラーが発生しました';
            }
        }
    
        echo json_encode([
            'isSuccess' => $isSaved,
            'messages' => $messages,
        ]);
    
        return false;
    }

    /**
     * 動画アップロード進捗を取得する
     */
    public function newProgressAction()
    {
        $name = $this->dispatcher->getParam("name");
        $key = ini_get('session.upload_progress.prefix') . $name;
        echo isset($_SESSION[$key]) ? json_encode($_SESSION[$key]) : json_encode(null);
        return;
    }

    public function updateByCodeAction($code)
    {
        header('Content-Type: application/json; charset=UTF-8');

        $isSuccess = false;
        $message = '予期せぬエラー';
        $count4update = 0;

        try {
            $values = [];
            $values['movie_name'] = $this->pdo->quote($_POST['movie_name']);
            $values['start_at'] = $this->pdo->quote($_POST['start_at']);
            $values['end_at'] = $this->pdo->quote($_POST['end_at']);

            $query = "UPDATE media SET"
                   . " movie_name = {$values['movie_name']}"
                   . ", start_at = {$values['start_at']}"
                   . ", end_at = {$values['end_at']}"
                   . ", updated_at = datetime('now', 'localtime')"
                   . " WHERE code = '{$code}' AND deleted_at = ''";
            $this->app->log->debug('$query:' . $query);
            $count4update = $this->pdo->exec($query);
            $isSuccess = true;
            $message = '';
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->app->log->debug('fail in updating media by code/ code:'. $code . ' / message:' . $message);
        }

        $this->app->log->debug('$count4update: ' . $count4update);

        echo json_encode([
            'success'      => $isSuccess,
            'message'      => $message,
            'update_count' => $count4update
        ]);
        return;
    }


    /**
     * 削除する
     */
    public function deleteAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $rowKey = $this->dispatcher->getParam('rowKey');

        $isSuccess = false;
        $message = '';

        try {
            $mediaModel = new MediaModel;
            $mediaEntity = $mediaModel->deleteByRowKey($this->session->get('auth')['UserId'], $rowKey);
            $isSuccess = true;
        } catch (\Exception $e) {
            $this->logger->addError('fail in deleteByRowKey. message:{$e}');
            $message = '予期せぬエラーが発生しました';
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
        $rowKey = $this->dispatcher->getParam('rowKey');

        try {
            $mediaModel = new MediaModel;
            $mediaEntity = $mediaModel->getByRowKey($this->session->get('auth')['UserId'], $rowKey);

            // 特定のAssetに対して、同時に5つを超える一意のLocatorを関連付けることはできない
            // 万が一SASロケーターがあれば削除
            $oldLocators = $this->mediaService->getAssetLocators($mediaEntity->getPropertyValue('AssetId'));
            foreach ($oldLocators as $oldLocator) {
                if ($oldLocator->getType() == WindowsAzure\MediaServices\Models\Locator::TYPE_SAS) {
                    // 期限切れであれば削除
                    $expiration = strtotime('+9 hours', $oldLocator->getExpirationDateTime()->getTimestamp());
                    if ($expiration < strtotime('now')) {
                        $this->mediaService->deleteLocator($oldLocator);
                        $this->logger->addDebug('SAS locator has been deleted. $locator: '. print_r($oldLocator, true));
                    }
                }
            }

            // 読み取りアクセス許可を持つAccessPolicyの作成
            $accessPolicy = new \WindowsAzure\MediaServices\Models\AccessPolicy('DownloadPolicy');
            $accessPolicy->setDurationInMinutes(10); // 10分間有効
            $accessPolicy->setPermissions(\WindowsAzure\MediaServices\Models\AccessPolicy::PERMISSIONS_READ);
            $accessPolicy = $this->mediaService->createAccessPolicy($accessPolicy);

            // アセットを取得
            $asset = $this->mediaService->getAsset($mediaEntity->getPropertyValue('AssetId'));
            $this->logger->addDebug('$asset:' . print_r($asset, true));

            // ダウンロードURLの作成
            $locator = new \WindowsAzure\MediaServices\Models\Locator(
                $asset,
                $accessPolicy,
                \WindowsAzure\MediaServices\Models\Locator::TYPE_SAS
            );
            $locator->setName('DownloadLocator_' . $asset->getId());
            $locator->setStartTime(new \DateTime('now -5 minutes'));
            $locator = $this->mediaService->createLocator($locator);
            $this->logger->addDebug(print_r($locator, true));

            $fileName = sprintf('%s.%s', $mediaEntity->getRowKey(), $mediaEntity->getPropertyValue('Extension'));
            $path = sprintf('%s/%s%s',
                $locator->getBaseUri(),
                $fileName,
                $locator->getContentAccessComponent());
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Type: application/octet-stream');
            if (!readfile($path)) {
                throw(new \Exception("Cannot read the file({$path})"));
            }

            // ロケーター削除
            $this->mediaService->deleteLocator($locator);

            exit;
        } catch (\Exception $e) {
            throw $e;
        }

        throw new \Exception('予期せぬエラー');
    }
}
