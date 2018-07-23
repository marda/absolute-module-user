<?php

namespace Absolute\Module\User\Presenter;

use Nette\Http\Response;
use Nette\Application\Responses\JsonResponse;
use Absolute\Core\Presenter\BaseRestPresenter;

class UserBasePresenter extends BaseRestPresenter
{

    public function startup()
    {
        parent::startup();
        if (!$this->user->isAllowed('backend')) {
            $this->jsonResponse->payload = (['message' => 'Unauthorized!']);
            $this->httpResponse->setCode(Response::S401_UNAUTHORIZED);
        }
    }

    // CONTROL
    public function actionCreate()
    {
       // $this->_isAllowedAdministration();
    }
    
    public function actionUpdate($id, $email, $firstName, $lastName, $phone, $role, $image)
	{
        switch ($this->httpRequest->getMethod()) {
            case 'POST':
                $this->userCRUDManager->update($id, $email, $firstName, $lastName, $phone, $role, $image);
                break;
            default:
                break;
        }
        $this->sendResponse(new JsonResponse(
            $this->jsonResponse->toJson(), "application/json;charset=utf-8"
        ));
	}

    // HANDLERS
    // SUBMITS
    // VALIDATION
    // COMPONENTS
}
