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
        switch ($this->httpRequest->getMethod())
        {
            case 'GET':
                if (isset($userId))
                {
                    $this->_getRequest($userId);
                }
                else
                {
                    $this->_getListRequest($this->getParameter('offset'), $this->getParameter('limit'));
                }
                break;
            case 'POST':
                $this->_postRequest();
                break;
            case 'PUT':
                $this->_putRequest($userId);
                break;
            case 'DELETE':
                $this->_deleteRequest($userId);
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
        if (!$user)
        {
            $this->httpResponse->setCode(Response::S404_NOT_FOUND);
            return;
        }
        $this->jsonResponse->payload = $user->toJson();
        $this->httpResponse->setCode(Response::S200_OK);
    }

    private function _getListRequest($offset, $limit)
    {
        $users = $this->userManager->getList($offset, $limit);
        $this->jsonResponse->payload = array_map(function($n)
        {
            return $n->toJson();
        }, $users);
        $this->httpResponse->setCode(Response::S200_OK);
    }

    private function _postRequest()
    {
        $data = json_decode($this->httpRequest->getRawBody());
        $validate = $this->userValidate($data);

        if (!$validate)
        {
            $this->postRequestSave($data);
        }
        else
        {
            $this->jsonResponse->payload = ['message' => $validate];
            $this->httpResponse->setCode(Response::S500_INTERNAL_SERVER_ERROR);
        }
    }

    private function _putRequest($id)
    {
        $data = json_decode($this->httpRequest->getRawBody());
        $validate = $this->userUpdateValidate($id, $data);

        if (!$validate)
        {
            $ret = $this->userCRUDManager->update($id, $data);
            if (!$ret)
            {
                $this->jsonResponse->payload = ['message' => 'no update user'];
                $this->httpResponse->setCode(Response::S500_INTERNAL_SERVER_ERROR);
            }
            else
            {
                $this->jsonResponse->payload = ['message' => 'update user saved'];
                $this->httpResponse->setCode(Response::S201_CREATED);
            }
        }
        else
        {
            $this->jsonResponse->payload = ['message' => $validate];
            $this->httpResponse->setCode(Response::S500_INTERNAL_SERVER_ERROR);
        }
    }

    private function _deleteRequest($id)
    {

        $validate = $this->userDeleteValidate($id);

        if (!$validate)
        {
            $ret = $this->userCRUDManager->delete($id);

            if (!$ret)
            {
                $this->jsonResponse->payload = ['message' => 'error delete user'];
                $this->httpResponse->setCode(Response::S500_INTERNAL_SERVER_ERROR);
            }
            else
            {
                $this->jsonResponse->payload = ['message' => 'delete user ok'];
                $this->httpResponse->setCode(Response::S200_OK);
            }
        }
        else
        {
            $this->jsonResponse->payload = ['message' => $validate];
            $this->httpResponse->setCode(Response::S404_NOT_FOUND);
        }
    }

}
