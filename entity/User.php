<?php

namespace Absolute\Module\User\Entity;

use Absolute\Core\Entity\BaseEntity;

class User extends BaseEntity
{
  private $id;
  private $created;
  private $username;
  private $role;
  private $firstName;
  private $lastName;
  private $email;
  private $phone;

  private $chatStatus = 'offline';
  private $image = null;
  private $roomId = null;
  private $starred = false;

  private $groups = [];
  private $categories = [];
  private $teams = [];

	public function __construct($id, $username, $role, $firstName, $lastName, $email, $phone, $created)
  {
    $this->id = $id;
    $this->created = $created;
    $this->username = $username;
    $this->role = $role;
    $this->firstName = $firstName;
    $this->lastName = $lastName;
    $this->email = $email;
    $this->phone = $phone;
	}

  public function getId()
  {
    return $this->id;
  }

  public function getCreated()
  {
    return $this->created->format("d.m.Y H:i");
  }

  public function getUsername()
  {
    return $this->username;
  }

  public function getPhone()
  {
    return $this->phone;
  }

  public function getRole()
  {
    return $this->role;
  }

  public function getFirstName()
  {
    return $this->firstName;
  }

  public function getLastName()
  {
    return $this->lastName;
  }

  public function getEmail()
  {
    return $this->email;
  }

  public function getImage()
  {
    return $this->image;
  }

  public function getStarred()
  {
    return $this->starred;
  }

  public function getGroups()
  {
    return $this->groups;
  }

  public function getCategories()
  {
    return $this->categories;
  }

  public function getTeams()
  {
    return $this->teams;
  }

  public function getRoomId()
  {
    return $this->roomId;
  }

  public function getChatStatus()
  {
    return $this->chatStatus;
  }

  // IS?

  public function isInGroup($id)
  {
    return array_key_exists($id, $this->groups);
  }

  // SETTERS

  public function setImage($image)
  {
    $this->image = $image;
  }

  public function setStarred($starred)
  {
    $this->starred = ($starred) ? true : false;
  }

  public function setRoomId($roomId)
  {
    $this->roomId = $roomId;
  }

  public function setChatStatus($status)
  {
    $this->chatStatus = $status;
  }

  // ADDERS

  public function addGroup($group)
  {
    $this->groups[$group->getId()] = $group;
  }

  public function addCategory($category)
  {
    $this->categories[$category->getId()] = $category;
  }

  public function addTeam($team)
  {
    $this->teams[$team->getId()] = $team;
  }

  // OTHER METHODS

  public function toSelectJson()
  {
    return array(
      "id" => $this->id,
      "text" => $this->firstName . (($this->lastName) ? " " . $this->lastName : ""),
    );
  }

  public function toCalendarJson()
  {
    return array(
      "id" => $this->id,
      "firstName" => $this->firstName,
      "lastName" => $this->lastName,
      //"imageUrl" => ($this->image) ? $this->image->url : "",
    );
  }

  public function toJson()
  {          
      return array(
      "uuid" => $this->id,
      "role" => $this->role,
      "data" => array(
          "displayName" => $this->firstName." ".$this->lastName,
          "email" => $this->email,
          "photoURL" => $this->image->getPath()
      ),
    );
  }

  public function toJsonString()
  {
    return json_encode(array(
      "id" => $this->id,
      "firstName" => $this->firstName,
      "lastName" => $this->lastName,
      //"imageUrl" => ($this->image) ? $this->image->url : "",
      "starred" => $this->starred,
      "categories" => array_map(function($category) { return $category->toJson(); }, $this->categories),
      "teams" => array_map(function($team) { return $team->toJson(); }, $this->teams),
      "groups" => array_map(function($group) { return $group->toJson(); }, $this->groups),
    ));
  }
}

