<?php

namespace Nordeus\CrowdUserBundle\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;

class CrowdUser implements UserInterface {
	
	/**
	 * @var string
	 */
	protected $username;
	
	/**
	 * @var string
	 */
	protected $firstName = null;
	
	/**
	 * @var string
	 */
	protected $lastName = null;
	
	/**
	 * @var string
	 */
	protected $displayName = null;
	
	/**
	 * @var string
	 */
	protected $email = null;
	
	/**
	 * @var boolean
	 */
	protected $active = null;
	
	/**
	 * @var array
	 */
	protected $attributes = array();
	
	/**
	 * @var string
	 */
	protected $roles = array();
	
	/**
	 * @var string
	 */
	protected $crowdSessionToken = null;
	
	/**
	 * @var int
	 */
	protected $lastTimeRefreshed;
	
	
	public function __construct() {
		$this->lastTimeRefreshed = time();
	}
	
	public function initWithCrowdData($crowdData) {
		$this->username = $crowdData['name'];
		
		if (!empty($crowdData['first-name'])) {
			$this->firstName = $crowdData['first-name'];
		}
		
		if (!empty($crowdData['last-name'])) {
			$this->lastName =  $crowdData['last-name'];
		}
		
		if (!empty($crowdData['display-name'])) {
			$this->displayName =  $crowdData['display-name'];
		}
		
		if (!empty($crowdData['email'])) {
			$this->email = $crowdData['email'];
		}
		
		$this->active = (isset($crowdData['active']) && $crowdData['active']);
		
		if (!empty($crowdData['token'])) {
			$this->crowdSessionToken = $crowdData['token'];
		}
		
		if (!empty($crowdData['attributes']['attributes'])) {
			$this->addAttributes($crowdData['attributes']['attributes']);
		}
	}
	
	public function getUsername() {
		return $this->username;
	}
	
	public function setUsername($username) {
		$this->username = $username;
		return $this;
	}

	public function getFirstName() {
		return $this->firstName;
	}
	
	public function setFirstName($firstName) {
		$this->firstName = $firstName;
		return $this;
	}
	
	public function getLastName() {
		return $this->lastName;
	}
	
	public function setLastName($lastName) {
		$this->lastName = $lastName;
		return $this;
	}
	
	public function getDisplayName() {
		return $this->displayName;
	}
	
	public function setDisplayName($displayName) {
		$this->displayName = $displayName;
		return $this;
	}
	
	public function getEmail() {
		return $this->email;
	}
	
	public function setEmail($email) {
		$this->email = $email;
		return $this;
	}
	
	public function getActive() {
		return $this->active;
	}
	
	public function setActive($active) {
		$this->active = $active;
		return $this;
	}
	
	public function getAttributes() {
		return $this->attributes;
	}
	
	public function setAttributes($attributes) {
		$this->attributes = $attributes;
	}
	
	public function addAttributes($crowdAtributes) {
		foreach ($crowdAtributes as $attData) {
			if (isset($attData['name']) && isset($attData['values'])) {
				$this->attributes[$attData['name']] = $attData['values'];
			}
		}
	}

	public function getRoles() {
		return $this->roles;
	}
	
	public function setRoles($roles) {
		$this->roles = $roles;
		return $this;
	}
	
	public function addRole($role) {
		$this->roles[] = $role;
		return $this;
	}
	
	public function getCrowdSessionToken() {
		return $this->crowdSessionToken;
	}
	
	public function setCrowdSessionToken($crowdSessionToken) {
		$this->crowdSessionToken = $crowdSessionToken;
		return $this;
	}

	public function getLastTimeRefreshed() {
		return $this->lastTimeRefreshed;
	}
	
	public function setLastTimeRefreshed($lastTimeRefreshed = null) {
		$this->lastTimeRefreshed = $lastTimeRefreshed ? $lastTimeRefreshed : time();
		return $this;
	}
	
	public function forceToRefreshOnNextRequest() {
		$this->lastTimeRefreshed = 0;
		return $this;
	}
	
	public function getPassword() {
		return null;
	}
	
	public function getSalt() {
		return null;
	}

	public function eraseCredentials() {
	}
}