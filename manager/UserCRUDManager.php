<?php

namespace Absolute\Module\User\Manager;

use Nette\Database\Context;
use Absolute\Core\Manager\BaseCRUDManager;

class UserCRUDManager extends BaseCRUDManager 
{

	private $ldapProtocol;

	private $rocketChatProtocol;

	private $fileCRUDManager;

	public function __construct(
		Context $database/*, 
		\App\Classes\LdapProtocol $ldapProtocol,
		\App\Model\FileCRUDManager $fileCRUDManager*/) {
  	parent::__construct($database);/*
  	$this->ldapProtocol = $ldapProtocol;
  	$this->fileCRUDManager = $fileCRUDManager;*/
	}

	// OTHER METHODS

	public function check($username) {
		// TODO presunout
		if (strtolower(trim($username)) == "admin")
		{
			return false;
		}
		if ($this->database->table('user')->where('username', $username)->select('id')->count('id') == 0) 
		{
			return true;
		}
		return false;
	}

	public function checkEmail($email) {
		if ($this->database->table('user')->where('email', $email)->select('id')->count('id') == 0) 
		{
			return true;
		}
		return false;
	} 

	public function checkCharacters($username)
	{
		if (preg_match('/^([^\W_]|\.)+$/', $username))
		{
		 	return true;
		}
		return false;
	}

	public function checkLostPassword($username, $email) 
	{
		if ($this->database->table('user')->where('username', $username)->where('email', $email)->select('id')->count('id') == 0) 
		{
			return false;
		}
		return true;
	}

	public function checkPassword($id, $password) 
	{
		if ($this->database->table('user')->where('id', $id)->select('password')->fetch()->password === sha1($password)) 
		{
			return true;
		}
		return false;
	}

	public function changePassword($password, $id) 
	{
		$db = $this->database->table('user')->get($id);
		// Rocket chat
		try
		{
			$user = $this->rocketChatProtocol->infoUser($db->username);
			$this->rocketChatProtocol->updatePasswordUser($user->_id, $password);	
		}
		catch (\Exception $e)
		{
			// Do nothing RocketChat is unaavaiable
		}
		// LDAP
    $entry["userpassword"] = $this->hashPasswordLDAP($password);	
    $this->ldapProtocol->modifyEntry("uid=" . $db->username . ",cn=users," . $this->ldapProtocol->getDn(), $entry);
    $this->ldapProtocol->disconnect();
		// Internal
		return $this->database->table('user')->where('id', $id)->update(array(
			'password' => sha1($password),
			'password_generated' => "",
		));
	}

	public function lostPassword($username, $email, $password) 
	{
		$result = $this->database->table('user')->where('username', $username)->update(array(
			'password_generated' => sha1($password),
		));
		return $result;
	}

	// SSHA with random 4-character salt
	private function hashPasswordLDAP($password) 
	{
		$salt = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',4)),0,4);
	 	return '{SSHA}' . base64_encode(sha1( $password . $salt, TRUE ). $salt);
	}

  // CONNECT METHODS

  public function toggleUserGroup($userId, $groupId) 
  {
  	$result = $this->database->table("group_user")->where('user_id', $userId)->where('group_id', $groupId)->fetch();
  	if ($result === false) 
  	{
  		return $this->database->table("group_user")->insert(array(
  			'user_id' => $userId,
  			'group_id' => $groupId,
  		));
  	}
  	return $this->database->table("group_user")->where('user_id', $userId)->where('group_id', $groupId)->delete();
  }

	public function addStar($contactId, $userId) 
	{
		return $this->database->table('user_contact')->where('user_id', $userId)->where('contact_id', $contactId)->update(array('starred' => true));
	}

	public function removeStar($contactId, $userId) 
	{
		return $this->database->table('user_contact')->where('user_id', $userId)->where('contact_id', $contactId)->update(array('starred' => false));
	}

	public function connectCategories($categories, $userId) 
	{
		$categories = array_filter($categories);
		$this->database->table('category_user')->where('user_id', $userId)->delete();
		$data = [];
		foreach ($categories as $category) 
		{
			$data[] = [
				"user_id" => $userId,
				"category_id" => $category
			];
		}
		$this->database->table("category_user")->insert($data);		
		return true;
	}

	public function connectTeams($teams, $userId) 
	{
		$teams = array_filter($teams);
		$this->database->table('team_user')->where('user_id', $userId)->delete();
		$data = [];
		foreach ($teams as $team) 
		{
			$data[] = [
				"user_id" => $userId,
				"team_id" => $team
			];
		}
		$this->database->table("team_user")->insert($data);		
		return true;
	}

    // CUD METHODS

	public function create($username, $email, $firstName, $lastName, $phone, $role, $image, $password = null) 
	{
		if ($image instanceof \Nette\Http\FileUpload && $image->getName())
		{
			$fileId = $this->fileCRUDManager->createFromUpload($image, $image->getSanitizedName(), "/images/users/");
			$fileId = (!$fileId) ? null : $fileId;
		}
		else
		{
			$fileId = null;
		}
		if ($password == null) 
		{
			$password = Nette\Utils\Strings::random(8, '0-9a-zA-Z');
		}
		$result = $this->database->table('user')->insert(array(
			'username' => $username,
			'email' => $email,
			'first_name' => $firstName,
			'last_name' => $lastName,
			'phone' => $phone,
			'created' => new \DateTime(),
			'password' => sha1($password),
			'role' => $role,
			'file_id' => $fileId,
		));
		if ($result !== false)
		{
			// Create on LDAP
			$entry["objectclass"] = ["top", "person", "posixAccount", "shadowAccount", "organizationalPerson"];
	    $entry["uid"] = $username;
	    $entry["uidnumber"] = 1000000 + $result->id; // posun UID
	    $entry["gidnumber"] = 1000001; // common users
	    $entry["homedirectory"] = "/home/" . $username;
	    $entry["cn"] = $username;
	    $entry["sn"] = $username;
	    $entry["loginshell"] = "/bin/sh";
	    $entry["shadowmin"] = "0";
	    $entry["shadowlastchange"] = "1";
	    $entry["shadowmax"] = "99999";
	    $entry["shadowwarning"] = "0";
	    $entry["shadowexpire"] = "-1";
	    $entry["shadowinactive"] = "0";
	    $entry["shadowflag"] = "0";	
	    $entry["description"] = "Praga System user";
	    $entry["userpassword"] = $this->hashPasswordLDAP($password);	
	    $this->ldapProtocol->addEntry("uid=" . $username . ",cn=users," . $this->ldapProtocol->getDn(), $entry);
	    $this->ldapProtocol->disconnect();
			// Create on Rocket Chat
			$name = (!$firstName && !$lastName) ? $username : ($firstName . (($lastName) ? " " . $lastName : ""));
			try
			{
				$this->rocketChatProtocol->createUser($email, $name, $password, $username);
			}
			catch (\Exception $e)
			{
				// Do nothing RocketChat is unavaible
			}
		}

    return $result;
	}

	public function delete($id) 
	{
		$db = $this->database->table('user')->get($id);
		if (!$db)
		{
			return false;
		}
		if ($db->file_id)
		{
			$this->fileCRUDManager->delete($db->file_id);
		}
		// Rocket Chat
		try {
			$user = $this->rocketChatProtocol->infoUser($db->username);
			$this->rocketChatProtocol->deleteUser($user->_id);
		}
		catch (\Exception $e)
		{
			// Do nothing rocket chat is unavaible
		}	
		// LDAP
		try 
		{
			$this->ldapProtocol->deleteEntry("uid=" . $db->username . ",cn=users," . $this->ldapProtocol->getDn());
		} 
		catch (\Exception $e)
		{
			// No such object
			if ($e->getCode() != 32)
			{
				throw $e;
			}
		}
		$this->database->table('user_contact')->where('user_id', $id)->delete();
		$this->database->table('team_user')->where('user_id', $id)->delete();
		$this->database->table('page_user')->where('user_id', $id)->delete();
		$this->database->table('menu_user')->where('user_id', $id)->delete();
		$this->database->table('note_user')->where('user_id', $id)->delete();
		$this->database->table('todo_user')->where('user_id', $id)->delete();
		$this->database->table('project_user')->where('user_id', $id)->delete();
		$this->database->table('group_user')->where('user_id', $id)->delete();
		$this->database->table('event_user')->where('user_id', $id)->delete();
		$this->database->table('category_user')->where('user_id', $id)->delete();
		return $this->database->table('user')->where('id', $id)->delete();
	}

	public function update($id, $email, $firstName, $lastName, $phone, $role, $image) 
	{
		$db = $this->database->table('user')->get($id);
		if (!$db)
		{
			return false;
		}
		if ($image instanceof \Nette\Http\FileUpload && $image->getName())
		{
			$fileId = $this->fileCRUDManager->createFromUpload($image, $image->getSanitizedName(), "/images/users/");
			$fileId = (!$fileId) ? null : $fileId;
			if ($db->file_id)
			{
				$this->fileCRUDManager->delete($db->file_id);
			}
		}
		else
		{
			$fileId = $db->file_id;
		} 
 		$res = $this->database->table('user')->where('id', $id)->update(array(
			'email' => $email,
			'first_name' => $firstName,
			'last_name' => $lastName,
			'phone' => $phone,
			'role' => $role,
			'file_id' => $fileId,
		));
		if ($res !== false)
		{
			// Rocket Chat
			try {
				$user = $this->rocketChatProtocol->infoUser($db->username);
				$name = (!$firstName && !$lastName) ? $db->username : ($firstName . (($lastName) ? " " . $lastName : ""));
				$this->rocketChatProtocol->updateUser($user->_id, $email, $name);				
			}
			catch (\Exception $e)
			{
				// Do nothing Rocket Chat is unavaible
			}
		}
		return $res;
	}


	public function updateProfile($id, $email, $firstName, $lastName, $phone) 
	{
		$db = $this->database->table('user')->get($id);
		if (!$db)
		{
			return false;
		} 
 		$res = $this->database->table('user')->where('id', $id)->update(array(
			'email' => $email,
			'first_name' => $firstName,
			'last_name' => $lastName,
			'phone' => $phone,
		));
		if ($res !== false)
		{
			// Rocket Chat
			$user = $this->rocketChatProtocol->infoUser($db->username);
			$name = (!$firstName && !$lastName) ? $username : ($firstName . (($lastName) ? " " . $lastName : ""));
			$this->rocketChatProtocol->updateUser($user->_id, $email, $name);
		}
		return $res;
	}

	public function updateImage($id, $imageId) 
	{
 		return $this->database->table('user')->where('id', $id)->update(array(
			'file_id' => $imageId,
		));
	}

	public function createContact($contacts, $groups, $userId) 
	{
		$contacts = array_filter($contacts);
		$groups = array_filter($groups);
		$data = [];
		foreach ($contacts as $contact) 
		{
			try 
			{
				$this->database->table("user_contact")->insert(array(
					"contact_id" => $contact,
					"user_id" => $userId
				));
			} 
			catch (\Nette\Database\DriverException $e) 
			{
				// Do nothing
			}
			foreach ($groups as $group) 
			{
				try 
				{
					$this->database->table("group_user")->insert(array(
						"group_id" => $group,
						"user_id" => $contact
					));
				} 
				catch (\Nette\Database\DriverException $e) 
				{
					// Do nothing
				}
			}			
		}
		return true;
	}

	public function createProjectContact($contacts, $groups, $projectId) 
	{
		$contacts = array_filter($contacts);
		$groups = array_filter($groups);
		$data = [];
		foreach ($contacts as $contact) 
		{
			try 
			{
				$this->database->table("project_user")->insert(array(
					"user_id" => $contact,
					"project_id" => $projectId,
					"role" => "user"
				));
			} 
			catch (\Nette\Database\DriverException $e) 
			{
				// Do nothing
			}
			foreach ($groups as $group) 
			{
				try 
				{
					$this->database->table("group_user")->insert(array(
						"group_id" => $group,
						"user_id" => $contact
					));
				} 
				catch (\Nette\Database\DriverException $e) 
				{
					// Do nothing
				}
			}			
		}
		return true;
	}

	public function deleteContact($contactId, $userId) 
	{
		$groups = $this->database->table('group')->where('user_id', $userId)->fetchPairs('id', 'id');
		$this->database->table("group_user")->where("group_id", $groups)->where('user_id', $contactId)->delete();
		return $this->database->table('user_contact')->where('contact_id', $contactId)->where('user_id', $userId)->delete();
	}

	public function deleteProjectContact($contactId, $projectId) 
	{
		$groups = $this->database->table('project_group')->where('project_id', $projectId)->fetchPairs('group_id', 'group_id');
		$this->database->table("group_user")->where("group_id", $groups)->where('user_id', $contactId)->delete();
		return $this->database->table('project_user')->where('user_id', $contactId)->where('project_id', $projectId)->delete();
	}

	public function deleteContacts($contacts, $userId) 
	{
		if (!is_array($contacts)) 
		{
			return false;
		}
		$contacts = array_filter($contacts);
		foreach ($contacts as $contactId) 
		{
			$this->deleteContact($contactId, $userId);
		}
		return true;
	}

	public function deleteProjectContacts($contacts, $projectId) 
	{
		if (!is_array($contacts)) 
		{
			return false;
		}
		$contacts = array_filter($contacts);
		foreach ($contacts as $contactId) 
		{
			$this->deleteProjectContact($contactId, $projectId);
		}
		return true;
	}
}

