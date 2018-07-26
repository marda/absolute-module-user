<?php

namespace Absolute\Module\User\Presenter;

use Nette\Http\Response;
use Absolute\Core\Presenter\BaseRestPresenter;
use Latte\Engine;

class UserBasePresenter extends BaseRestPresenter
{

    public function startup()
    {
        parent::startup();
        if (!$this->user->isAllowed('backend'))
        {
            $this->jsonResponse->payload = ['message' => 'Unauthorized!'];
            $this->httpResponse->setCode(Response::S401_UNAUTHORIZED);
        }else{
             $this->latte = new Engine();
        }
    }

    // CONTROL
    // HANDLERS
    // SUBMITS

    public function postRequestSave($data)
    {
        $ret = $this->userCRUDManager->create($data);
        if (!$ret)
        {
            $this->jsonResponse->payload = ['message' => 'No save user.'];
            $this->httpResponse->setCode(Response::S500_INTERNAL_SERVER_ERROR);
        }
        else
        {
            if (isset($data->categories))
            {
                $this->userCRUDManager->connectCategories($data->categories, $ret->id);
            }

            if (isset($data->team))
            {
                $this->userCRUDManager->connectTeams($data->team, $ret->id);
            }
       
            $this->jsonResponse->payload = ['message' => 'Save user.'];
            $this->httpResponse->setCode(Response::S201_CREATED);
        }
    }

    // VALIDATION

    public function userValidate($data)
    {
        $error = false;
        if ($this->userCRUDManager->check($data->username) == false)
        {
            $error = 'This username already exists!';
        }
        if ($this->userCRUDManager->checkEmail($data->email) == false)
        {
            $error = 'This email already exists!';
        }
        if ($this->userCRUDManager->checkCharacters($data->username) == false)
        {
            $error = 'In username you can use only letters or digits!';
        }

        return $error;
    }

    public function userUpdateValidate($id, $data)
    {

        $error = false;
        if (empty($id))
        {
            $error = 'Not userId parameter!';
        }
        else
        {
            $user = $this->userManager->getById($id);
            if ($data->email !== $user->getEmail() && $this->userCRUDManager->checkEmail($data->email) == false)
            {
                $error = 'This email already exists!';
            }
        }

        return $error;
    }

    public function userDeleteValidate($id)
    {

        $error = false;
        if (empty($id))
        {
            $error = 'Not userId parameter!';
        }
        return $error;
    }

    // COMPONENTS
}
