<?php

namespace Nordeus\CrowdUserBundle\Security\Authentication;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Psr\Log\LoggerInterface;

class CrowdSSOAuthenticationListener implements ListenerInterface {

	/**
	 * This attribute name is used as a indication for CrowdResponseListener to cancel sso cookie,
	 * If SSO Listener tries to authenticate user with cookie token, in case of any error, it should delete cookie
	 *
	 * @var string
	 */
	const CANCEL_SSO_COOKIE_ATTR_NAME = '_security_cancel_sso_cookie';

	protected $tokenStorage;
	protected $authenticationManager;
	protected $ssoCookieName;
	protected $ssoCookieDomain;
	protected $ssoCookieNameForReading;

	/**
	 * Constructor
	 * 
	 * @param TokenStorageInterface $tokenStorage
	 * @param AuthenticationManagerInterface $authenticationManager
	 * @param string $ssoCookieName
	 * @param string $ssoCookieDomain
	 * @param LoggerInterface $logger
	 */
	public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, $ssoCookieName, $ssoCookieDomain, LoggerInterface $logger) {
		$this->tokenStorage = $tokenStorage;
		$this->authenticationManager = $authenticationManager;
		$this->ssoCookieDomain = $ssoCookieDomain;
		$this->ssoCookieName = $ssoCookieName;

		// Even though cookie is set with name which has '.',
		// we can fetch it only with all '_' in its name.
		// e.g. cookie set as 'crowd.token_key' could be read as 'crowd_token_key'
		$this->ssoCookieNameForReading = str_replace('.', '_', $ssoCookieName);
	}

	/**
	 * Handles SSO cookie based authentication.
	 * 
	 * @param GetResponseEvent $event
	 */
	public function handle(GetResponseEvent $event) {
		$request = $event->getRequest();

		if (!$request->cookies->has($this->ssoCookieNameForReading)) {
			$token = $this->tokenStorage->getToken();
			if ($token instanceof CrowdAuthenticationToken) {
				$this->tokenStorage->setToken(null);
			}
			return;
		}

		$token = $this->tokenStorage->getToken();
		$crowdCookieSessionToken = $request->cookies->get($this->ssoCookieNameForReading);

		if (
			$token && ($token instanceof CrowdAuthenticationToken)
			&& $token->getUser()->getCrowdUser()->getCrowdSessionToken() == $crowdCookieSessionToken
		) {
				$token->setAuthenticated(true);
				return;
		}


		$newToken = new CrowdAuthenticationToken();
		$newToken->setCrowdCookieToken($crowdCookieSessionToken);

		try {
			$returnValue = $this->authenticationManager->authenticate($newToken);

			if ($returnValue instanceof CrowdAuthenticationToken) {
				$returnValue->setAuthenticated(true);
				$this->tokenStorage->setToken($returnValue);
				return;
			} else if ($returnValue instanceof Response) {
				$event->setResponse($returnValue);
				return;
			}
		} catch (AuthenticationException $e) {
			if ($e->getCode() == 403) {
				/*
				 * A code of 403 means that authentication failed because user does not have access to the Crowd application. In this case, the SSO cookie should not be deleted.
				 * Instead, the error should be stored to be displayed, and the security token should be cleared so that the user becomes anonymously authenticated.
				 */
				$request->attributes->set(Security::AUTHENTICATION_ERROR, $e);
				$this->tokenStorage->setToken(null);
				return;
			}
			// In case of any error - delete cookie. CrowdResponseListener will delete the cookie
			$ssoCookie = new Cookie($this->ssoCookieName, null, 0, '/', '.' . $this->ssoCookieDomain);
			$request->attributes->set(self::CANCEL_SSO_COOKIE_ATTR_NAME, $ssoCookie);
			throw $e;
		}

		$response = new Response();
		$response->setStatusCode(403);
		$event->setResponse($response);
	}
}
