<?php

namespace Absolute\Module\User\Presenter;

use Nette\Http\Response;
use Nette\Application\Responses\JsonResponse;

class DefaultPresenter extends UserBasePresenter
{

    /** @var \Absolute\Module\User\Manager\UserManager @inject */
    public $userManager;

    /** @var \Absolute\Module\User\Manager\UserCRUDManager @inject */
    public $userCRUDManager;

    public function startup()
    {
        parent::startup();
    }

    public function renderDefault($userId)
    {
        switch ($this->httpRequest->getMethod()) {
            case 'GET':
                if (isset($userId)) {                    
                    $this->_getRequest($userId);
                } else {
                    $this->_getListRequest();
                }
                break;
            case 'POST':
                $this->httpResponse->setCode(Response::S201_CREATED);
                break;
            case 'OPTIONS':
                $this->httpResponse->setCode(Response::S200_OK);
            default:
                break;
        }
        $this->sendResponse(new JsonResponse(
            $this->jsonResponse->toJson(), "application/json;charset=utf-8"
        ));
    }

    private function _getRequest($id)
    {
        $user = $this->userManager->getById($id);
        if (!$user) {
            $this->httpResponse->setCode(Response::S404_NOT_FOUND);
            return;
        }
        $this->jsonResponse->payload = $user->toJson();
        $this->httpResponse->setCode(Response::S200_OK);
    }

    private function _getListRequest()
    {
        $users = $this->userManager->getList($this->user->id);
        $this->jsonResponse->payload = array_map(function($n) {
            return $n->toJson();
        }, $users);
        $this->httpResponse->setCode(Response::S200_OK);
    }
    
}
