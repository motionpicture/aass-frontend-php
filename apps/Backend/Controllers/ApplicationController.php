<?php
namespace Aass\Backend\Controllers;

use \Aass\Backend\Models\Application as ApplicationModel;

class ApplicationController extends BaseController
{
    public function acceptAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $message = '';

        try {
            $applicationModel = new ApplicationModel;
            $isSuccess = $applicationModel->updateStatus($this->dispatcher->getParam('id'), ApplicationModel::STATUS_ACCEPTED);
        } catch (\Exception $e) {
            $this->logger->addError("applicationModel->updateStatus throw exception. message:{$e}");
            $message = '更新に失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'message' => $message
        ]);

        return false;
    }

    public function rejectAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $message = '';

        try {
            $applicationModel = new ApplicationModel;
            $isSuccess = $applicationModel->updateStatus($this->dispatcher->getParam('id'), ApplicationModel::STATUS_REJECTED);
        } catch (\Exception $e) {
            $this->logger->addError("applicationModel->updateStatus throw exception. message:{$e}");
            $message = '更新に失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'message' => $message
        ]);

        return false;
    }

    public function deleteAction()
    {
        $this->response->setHeader('Content-type', 'application/json');

        $isSuccess = false;
        $message = '';

        try {
            $applicationModel = new ApplicationModel;
            $isSuccess = $applicationModel->deleteById($this->dispatcher->getParam('id'));
        } catch (\Exception $e) {
            $this->logger->addError("applicationModel->deleteById throw exception. message:{$e}");
            $message = '削除に失敗しました';
        }

        echo json_encode([
            'isSuccess' => $isSuccess,
            'message' => $message
        ]);

        return false;
    }
}