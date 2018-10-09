<?php

namespace Nordeus\CrowdUserBundle\Security\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class CrowdAuthenticationToken extends AbstractToken {

	const AUTH_TYPE_SSO = 1;
	const AUTH_TYPE_LOGIN = 2;

	/**
	 * It is used by Login Listener to pass user's password to Authentication Provider.
	 * Even though Authentication Provider should generate new Authentication Token and discard this one (with plain password),
	 * this field is not included in serialization so it will be erased anyway in moment when ContextListener saves the token in session.
	 * 
	 * @var string
	 */
	protected $plainPassword;

	/**
	 * It is used by SSO Listener to pass Crowd session cookie token to Authentication Provider, so User could be fetched with it from Crowd.
	 * It is not included in serialization, it will be erased in moment when the token is saved to session by ConextListener.
	 * 
	 * @var string
	 */
	protected $crowdCookieToken;

	/**
	 * It is used by CrowdAuthenticationProvider (authentication manager) to determine type of authentication.
	 * This is required in order to determine whether or not password should be verified.
	 *
	 * @var string
	 */
	protected $authType;

	public function __construct($authType = 0, $user = '', $roles = array()) {
		parent::__construct($roles);
		$this->authType = $authType;
		$this->setUser($user);
	}

	public function getCredentials() {
		return '';
	}

	public function setCrowdCookieToken($crowdCookieToken) {
		$this->crowdCookieToken = $crowdCookieToken;
	}

	public function getCrowdCookieToken() {
		return $this->crowdCookieToken;
	}

	public function setPlainPassword($plainPassword) {
		$this->plainPassword = $plainPassword;
	}

	public function getPlainPassword() {
		return $this->plainPassword;
	}

	public function getAuthType() {
		return $this->authType;
	}
}
