<?php
namespace Aass\Backend\Controllers;

use \Aass\Backend\Models\Event as EventModel;

class EventController extends BaseController
{
    public function indexAction()
    {
        try {
            $eventModel = new EventModel;
            $this->view->events = $eventModel->getAll();
        } catch (\Exception $e) {
            $this->logger->addDebug(print_r($e, true));
            throw $e;
        }
    }

    /**
     * アカウント追加
     * 
     * @throws \Exception
     */
    public function newAction()
    {
        return $this->dispatcher->forward([
            'contorller' => 'event',
            'action' => 'edit',
        ]);
    }

    /**
     * アカウント編集
     * 
     * @throws \Exception
     */
    public function editAction()
    {
        $messages = [];
        $defaults = [
            'id' => '',
            'email' => '',
            'user_id' => '',
            'password' => '',
            'held_at' => '',
            'place' => '',
            'remarks' => '',
        ];

        if ($this->dispatcher->getParam('id')) {
            try {
                $eventModel = new EventModel;
                $defaults = array_merge($defaults, $eventModel->getById($this->dispatcher->getParam('id')));
            } catch (\Exception $e) {
                $this->logger->addError("getById throw exception. message:{$e}");
                throw $e;
            }
        }

        $this->view->messages = $messages;
        $this->view->defaults = $defaults;
    }

    public function updateAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSaved = false;
        $messages = [];
        $defaults = [
            'id' => '',
            'email' => '',
            'user_id' => '',
            'password' => '',
            'held_at' => '',
            'place' => '',
            'remarks' => '',
        ];

        $eventModel = new EventModel;
        $this->logger->addDebug(print_r($_POST, true));
        $defaults = array_merge($defaults, $_POST);

        // 重複チェック
        if (!$defaults['email']) {
            $messages[] = 'メールアドレスを入力してください';
        } else {
            if ($eventModel->isDuplicateByEmail($defaults['id'], $defaults['email'])) {
                $messages[] = 'メールアドレスが重複しています';
            }
        }
        if (!$defaults['user_id']) {
            $messages[] = 'ユーザIDを入力してください';
        } else {
            if ($eventModel->isDuplicateByUserId($defaults['id'], $defaults['user_id'])) {
                $messages[] = 'ユーザIDが重複しています';
            }
        }
        if (!$defaults['password']) {
            $messages[] = 'パスワードを入力してください';
        }
        if (!$defaults['held_at']) {
            $messages[] = '上映日時を入力してください';
        }
        if (!$defaults['place']) {
            $messages[] = '上映場所を入力してください';
        }

        if (empty($messages)) {
            try {
                $eventModel = new EventModel;
                if ($eventModel->updateFromArray($defaults)) {
                    $isSaved = true;
                }
            } catch (\Exception $e) {
                $this->logger->addDebug(print_r($e, true));
                $messages[] = "エラーが発生しました {$e->getMessage()}";
            }
        }

        echo json_encode([
            'isSuccess' => $isSaved,
            'messages' => $messages,
        ]);

        return false;
    }
}