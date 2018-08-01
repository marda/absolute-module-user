<?php

namespace Absolute\Module\User\Manager;

use Nette\Database\Context;
use Absolute\Core\Manager\BaseManager;
use Absolute\Module\User\Entity\User;
use Absolute\Module\User\Entity\Category;
use Absolute\Module\Team\Manager\TeamManager;
use Absolute\Module\File\Manager\FileManager;
use Absolute\Module\Category\Manager\CategoryManager;

class UserManager extends BaseManager
{

    private $fileManager;
    private $teamManager;
    private $categoryManager;

    public function __construct(Context $database, FileManager $fileManager, TeamManager $teamManager, CategoryManager $categoryManager )
    {
        parent::__construct($database);
        $this->fileManager = $fileManager;
        $this->teamManager = $teamManager;
        $this->categoryManager = $categoryManager;
    }


    /* DB TO ENTITY */
    protected function _getUser($db)
    {
        if ($db == false) {
            return false;
        }
        $object = new User($db->id, $db->username, $db->role, $db->first_name, $db->last_name, $db->email, $db->phone, $db->created);
        if ($db->ref('file')) {
            $object->setImage($this->fileManager->_getFile($db->ref('file')));
        }
        foreach ($db->related('category_user') as $categoryDb) {
            $category = $this->categoryManager->getCategory($categoryDb->category);
            if ($category) {
                $object->addCategory($category);
            }
        }
        foreach ($db->related('team_user') as $teamDb) {
            $team = $this->teamManager->_getTeam($teamDb->team);
            if ($team) {
                $object->addTeam($team);
            }
        }
        return $object;
    }
    public function getUser($db)
    {
        return $this->_getUser($db);
    }

    /* INTERNAL/EXTERNAL INTERFACE */
    public function _getById($id)
    {
        $resultDb = $this->database->table('user')->get($id);
        return $this->_getUser($resultDb);
    }

    public function _getContactById($contactId, $userId)
    {
        $resultDb = $this->database->table('user')->get($contactId);
        $user = $this->_getUser($resultDb);
        $groups = $this->database->table('group')->where('user_id', $userId)->fetchPairs('id', 'id');
        $contactDb = $this->database->table('user_contact')->where('user_id', $userId)->where('contact_id', $contactId)->fetch();
        if ($contactDb) {
            $user->setStarred($contactDb->starred);
        }
        if ($user) {
            foreach ($this->database->table('group_user')->where('group_id', $groups)->where('user_id', $user->id) as $groupDb) {
                $group = $this->_getGroup($groupDb->group);
                if ($group) {
                    $user->addGroup($group);
                }
            }
        }
        return $user;
    }

    public function _getProjectContactById($contactId, $projectId)
    {
        $resultDb = $this->database->table('user')->get($contactId);
        $user = $this->_getUser($resultDb);
        $groups = $this->database->table('project_group')->where('project_id', $projectId)->fetchPairs('group_id', 'group_id');
        $contactDb = $this->database->table('project_user')->where('user_id', $contactId)->where('project_id', $projectId)->fetch();
        if ($contactDb) {
            $user->setStarred($contactDb->starred);
        }
        if ($user) {
            foreach ($this->database->table('group_user')->where('group_id', $groups)->where('user_id', $user->id) as $groupDb) {
                $group = $this->_getGroup($groupDb->group);
                if ($group) {
                    $user->addGroup($group);
                }
            }
        }
        return $user;
    }

    public function _getByUsername($username)
    {
        $resultDb = $this->database->table('user')->where('username', $username)->fetch();
        return $this->_getUser($resultDb);
    }

    public function _getCount()
    {
        return $this->database->table('user')->select('id')->count('id');
    }

    private function _getList($offset = -1, $limit = -1)
    {
        $ret = array();
        $resultDb = $this->database->table('user')->order('username')->limit($limit,$offset);
        foreach ($resultDb as $db) {
            $object = $this->_getUser($db);
            $ret[$db->username] = $object;
        }
        return $ret;
    }

    private function _getUserList($userId)
    {
        $ret = array();
        $users = [];
        $starred = [];
        $groups = $this->database->table('group')->where('user_id', $userId)->fetchPairs('id', 'id');
        $usersDb = $this->database->table('user_contact')->where('user_id', $userId);
        foreach ($usersDb as $db) {
            $users[] = $db->contact_id;
            $starred[$db->contact_id] = $db->starred;
        }
        $resultDb = $this->database->table('user')->where("id", $users)->order('username');
        foreach ($resultDb as $db) {
            $object = $this->_getUser($db);
            if (array_key_exists($db->id, $starred)) {
                $object->setStarred($starred[$db->id]);
            }
            foreach ($db->related('group_user')->where('group_id', $groups) as $groupDb) {
                $group = $this->_getGroup($groupDb->group);
                if ($group) {
                    $object->addGroup($group);
                }
            }
            $ret[] = $object;
        }
        return $ret;
    }

    private function _getProjectList($projectId)
    {
        $ret = array();
        $users = [];
        $starred = [];
        $groups = $this->database->table('project_group')->where('project_id', $projectId)->fetchPairs('group_id', 'group_id');
        $usersDb = $this->database->table('project_user')->where('project_id', $projectId);
        foreach ($usersDb as $db) {
            $users[] = $db->user_id;
            $starred[$db->user_id] = $db->starred;
        }
        $resultDb = $this->database->table('user')->where("id", $users)->order('username');
        foreach ($resultDb as $db) {
            $object = $this->_getUser($db);
            if (array_key_exists($db->id, $starred)) {
                $object->setStarred($starred[$db->id]);
            }
            foreach ($db->related('group_user')->where('group_id', $groups) as $groupDb) {
                $group = $this->_getGroup($groupDb->group);
                if ($group) {
                    $object->addGroup($group);
                }
            }
            $ret[] = $object;
        }
        return $ret;
    }

    private function _getTeamList($teamId)
    {
        $ret = array();
        $resultDb = $this->database->table('user')->where(':team_user.team_id', $teamId)->order('username');
        foreach ($resultDb as $db) {
            $object = $this->_getUser($db);
            $ret[] = $object;
        }
        return $ret;
    }

    private function _getSearch($search)
    {
        $ret = array();
        $resultDb = $this->database->table('user')->where('first_name REGEXP ? OR last_name REGEXP ? OR CONCAT_WS(" ", first_name, last_name) REGEXP ? OR CONCAT_WS(" ", last_name, first_name) REGEXP ?', $search, $search, $search, $search);
        foreach ($resultDb as $db) {
            $object = $this->_getUser($db);
            $ret[] = $object;
        }
        return $ret;
    }

    private function _getTodoList($todoId)
    {
        $ret = array();
        $resultDb = $this->database->table('user')->where(':todo_user.todo_id', $todoId);
        foreach ($resultDb as $db)
        {
            $object = $this->_getUser($db);
            $ret[] = $object;
        }
        return $ret;
    }

    private function _getTodoItem($todoId,$userId)
    {
       return $this->_getUser($this->database->table('user')->where(':todo_user.todo_id', $todoId)->where("user_id", $userId)->fetch());
    }

    public function _userTodoDelete($todoId,$userId)
    {
        return $this->database->table('todo_user')->where('todo_id', $todoId)->where('user_id', $userId)->delete();
    }

    public function _userTodoCreate($todoId,$userId)
    {
        return $this->database->table('todo_user')->insert(['todo_id' => $todoId, 'user_id' => $userId]);
    }

    private function _getEventList($todoId)
    {
        $ret = array();
        $resultDb = $this->database->table('user')->where(':event_user.event_id', $todoId);
        foreach ($resultDb as $db)
        {
            $object = $this->_getUser($db);
            $ret[] = $object;
        }
        return $ret;
    }

    private function _getGroupList($todoId)
    {
        $ret = array();
        $resultDb = $this->database->table('user')->where(':group_user.group_id', $todoId);
        foreach ($resultDb as $db)
        {
            $object = $this->_getUser($db);
            $ret[] = $object;
        }
        return $ret;
    }

    private function _getGroupItem($eventId,$userId)
    {
       return $this->_getUser($this->database->table('user')->where(':group_user.group_id', $eventId)->where("user_id", $userId)->fetch());
    }

    public function _userGroupDelete($eventId,$userId)
    {
        return $this->database->table('group_user')->where('group_id', $eventId)->where('user_id', $userId)->delete();
    }

    public function _userGroupCreate($eventId,$userId)
    {
        return $this->database->table('group_user')->insert(['group_id' => $eventId, 'user_id' => $userId]);
    }

    private function _getProjectItem($projectId,$userId)
    {
       return $this->_getUser($this->database->table('user')->where(':project_user.project_id', $projectId)->where("user_id", $userId)->fetch());
    }

    public function _userProjectDelete($projectId,$userId)
    {
        return $this->database->table('project_user')->where('project_id', $projectId)->where('user_id', $userId)->delete();
    }
    
    public function _userProjectUpdate($projectId,$userId,$post){
        unset($post['id']);
        return $this->database->table('project_user')->where('project_id', $projectId)->where('user_id', $userId)->update($post);
    }
    
    public function _userProjectCreate($projectId,$userId,$role,$starred)
    {
        return $this->database->table('project_user')->insert([
            'project_id' => $projectId, 
            'user_id' => $userId, 
            'role' => $role, 
            'starred' => $starred]);
    }

    private function _getMenuList($todoId)
    {
        $ret = array();
        $resultDb = $this->database->table('user')->where(':menu_user.menu_id', $todoId);
        foreach ($resultDb as $db)
        {
            $object = $this->_getUser($db);
            $ret[] = $object;
        }
        return $ret;
    }

    private function _getMenuItem($menuId,$userId)
    {
       return $this->_getUser($this->database->table('user')->where(':menu_user.menu_id', $menuId)->where("user_id", $userId)->fetch());
    }

    public function _userMenuDelete($menuId,$userId)
    {
        return $this->database->table('menu_user')->where('menu_id', $menuId)->where('user_id', $userId)->delete();
    }

    public function _userMenuCreate($menuId,$userId)
    {
        return $this->database->table('menu_user')->insert(['menu_id' => $menuId, 'user_id' => $userId]);
    }

    private function _getPageList($todoId)
    {
        $ret = array();
        $resultDb = $this->database->table('user')->where(':page_user.page_id', $todoId);
        foreach ($resultDb as $db)
        {
            $object = $this->_getUser($db);
            $ret[] = $object;
        }
        return $ret;
    }

    private function _getPageItem($pageId,$userId)
    {
       return $this->_getUser($this->database->table('user')->where(':page_user.page_id', $pageId)->where("user_id", $userId)->fetch());
    }

    public function _userPageDelete($pageId,$userId)
    {
        return $this->database->table('page_user')->where('page_id', $pageId)->where('user_id', $userId)->delete();
    }

    public function _userPageCreate($pageId,$userId)
    {
        return $this->database->table('page_user')->insert(['page_id' => $pageId, 'user_id' => $userId]);
    }
    
    private function _getEventItem($eventId,$userId)
    {
       return $this->_getUser($this->database->table('user')->where(':event_user.event_id', $eventId)->where("user_id", $userId)->fetch());
    }

    public function _userEventDelete($eventId,$userId)
    {
        return $this->database->table('event_user')->where('event_id', $eventId)->where('user_id', $userId)->delete();
    }

    public function _userEventCreate($eventId,$userId)
    {
        return $this->database->table('event_user')->insert(['event_id' => $eventId, 'user_id' => $userId]);
    }

    private function _getTeamItem($teamId,$userId)
    {
       return $this->_getUser($this->database->table('user')->where(':team_user.team_id', $teamId)->where("user_id", $userId)->fetch());
    }

    public function _userTeamDelete($teamId,$userId)
    {
        return $this->database->table('team_user')->where('team_id', $teamId)->where('user_id', $userId)->delete();
    }

    public function _userTeamCreate($teamId,$userId)
    {
        return $this->database->table('team_user')->insert(['team_id' => $teamId, 'user_id' => $userId]);
    }

    public function getTeamItem($teamId,$userId)
    {
        return $this->_getTeamItem($teamId,$userId);
    }

    public function userTeamDelete($teamId,$userId)
    {
        return $this->_userTeamDelete($teamId,$userId);
    }

    public function userTeamCreate($teamId,$userId)
    {
        return $this->_userTeamCreate($teamId,$userId);
    }

    public function getEventList($eventId)
    {
        return $this->_getEventList($eventId);
    }

    public function getEventItem($eventId,$userId)
    {
        return $this->_getEventItem($eventId,$userId);
    }

    public function userEventDelete($eventId,$userId)
    {
        return $this->_userEventDelete($eventId,$userId);
    }

    public function userEventCreate($eventId,$userId)
    {
        return $this->_userEventCreate($eventId,$userId);
    }

    public function getTodoList($todoId)
    {
        return $this->_getTodoList($todoId);
    }

    public function getTodoItem($todoId,$userId)
    {
        return $this->_getTodoItem($todoId,$userId);
    }

    public function userTodoDelete($todoId,$userId)
    {
        return $this->_userTodoDelete($todoId,$userId);
    }

    public function userTodoCreate($todoId,$userId)
    {
        return $this->_userTodoCreate($todoId,$userId);
    }

    /* EXTERNAL METHOD */

    public function getById($id)
    {
        return $this->_getById($id);
    }

    public function getContactById($contactId, $userId)
    {
        return $this->_getContactById($contactId, $userId);
    }

    public function getProjectContactById($contactId, $projectId)
    {
        return $this->_getProjectContactById($contactId, $projectId);
    }

    public function getByUsername($username)
    {
        return $this->_getByUsername($username);
    }

    public function getCount()
    {
        return $this->_getCount();
    }

    public function getList($offset, $limit)
    {
        return $this->_getList($offset, $limit);
    }

    public function getUserList($userId)
    {
        return $this->_getUserList($userId);
    }

    public function getProjectList($projectId)
    {
        return $this->_getProjectList($projectId);
    }

    public function getTeamList($userId)
    {
        return $this->_getTeamList($userId);
    }

    public function getSearch($search)
    {
        return $this->_getSearch($search);
    }

    public function getProjectItem($projectId,$userId)
    {
        return $this->_getProjectItem($projectId,$userId);
    }

    public function userProjectDelete($projectId,$userId)
    {
        return $this->_userProjectDelete($projectId,$userId);
    }

    public function userProjectCreate($projectId,$userId,$role,$starred)
    {
        return $this->_userProjectCreate($projectId,$userId,$role,$starred);
    }

    public function userProjectUpdate($projectId,$userId,$post)
    {
        return $this->_userProjectUpdate($projectId,$userId,$post);
    }

    public function getPageList($pageId)
    {
        return $this->_getPageList($pageId);
    }

    public function getPageItem($pageId,$userId)
    {
        return $this->_getPageItem($pageId,$userId);
    }

    public function userPageDelete($pageId,$userId)
    {
        return $this->_userPageDelete($pageId,$userId);
    }

    public function userPageCreate($pageId,$userId)
    {
        return $this->_userPageCreate($pageId,$userId);
    }

    public function getMenuList($menuId)
    {
        return $this->_getMenuList($menuId);
    }

    public function getMenuItem($menuId,$userId)
    {
        return $this->_getMenuItem($menuId,$userId);
    }

    public function userMenuDelete($menuId,$userId)
    {
        return $this->_userMenuDelete($menuId,$userId);
    }

    public function userMenuCreate($menuId,$userId)
    {
        return $this->_userMenuCreate($menuId,$userId);
    }

    public function getGroupList($eventId)
    {
        return $this->_getGroupList($eventId);
    }

    public function getGroupItem($eventId,$userId)
    {
        return $this->_getGroupItem($eventId,$userId);
    }

    public function userGroupDelete($eventId,$userId)
    {
        return $this->_userGroupDelete($eventId,$userId);
    }

    public function userGroupCreate($eventId,$userId)
    {
        return $this->_userGroupCreate($eventId,$userId);
    }

}
