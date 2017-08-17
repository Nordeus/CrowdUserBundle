<?php

namespace Nordeus\CrowdUserBundle\Security\Authentication;

use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CrowdSSOLogoutHandler implements LogoutHandlerInterface {

	protected $ssoCookieName;
	protected $ssoCookieDomain;

	/**
	 * Constructor
	 * 
	 * @param string $ssoCookieName
	 * @param string $ssoCookieDomain
	 */
	public function __construct($ssoCookieName, $ssoCookieDomain) {
		$this->ssoCookieName = $ssoCookieName;
		$this->ssoCookieDomain = $ssoCookieDomain;
	}

	/**
	 * {@inheritdoc}
	 */
	public function logout(Request $request, Response $response, TokenInterface $token) {
		$response->headers->clearCookie($this->ssoCookieName, '/', $this->ssoCookieDomain);
	}
}
