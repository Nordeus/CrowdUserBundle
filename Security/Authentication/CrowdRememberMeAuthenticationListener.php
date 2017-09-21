<?php

namespace Nordeus\CrowdUserBundle\Security\Authentication;

use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Psr\Log\LoggerInterface;

class CrowdRememberMeAuthenticationListener implements ListenerInterface {

	/**
	 * This attribute name is used as an indication for CrowdResponseListener to set sso cookie,
	 * after remember-me listener authenticates user with remember-me cookie.
	 *
	 * @var string
	 */
	const SET_SSO_COOKIE_ATTR_NAME = '_security_set_sso_cookie';

	protected $tokenStorage;
	protected $rememberMeServices;
	protected $authenticationManager;
	protected $logger;
	protected $dispatcher;
	protected $ssoCookieName;
	protected $ssoCookieDomain;


	/**
	 * Constructor.
	 *
	 * @param TokenStorageInterface $tokenStorage
	 * @param RememberMeServicesInterface $rememberMeServices
	 * @param AuthenticationManagerInterface $authenticationManager
	 * @param string $ssoCookieName
	 * @param string $ssoCookieDomain
	 * @param LoggerInterface $logger
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function __construct(TokenStorageInterface $tokenStorage, RememberMeServicesInterface $rememberMeServices, AuthenticationManagerInterface $authenticationManager, $ssoCookieName, $ssoCookieDomain, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null) {
		$this->tokenStorage = $tokenStorage;
		$this->rememberMeServices = $rememberMeServices;
		$this->authenticationManager = $authenticationManager;
		$this->logger = $logger;
		$this->dispatcher = $dispatcher;
		$this->ssoCookieName = $ssoCookieName;
		$this->ssoCookieDomain = $ssoCookieDomain;
	}

	/**
	 * Handles remember-me cookie based authentication.
	 *
	 * @param GetResponseEvent $event A GetResponseEvent instance
	 */
	public function handle(GetResponseEvent $event) {
		if (null !== $this->tokenStorage->getToken()) {
			return;
		}

		$request = $event->getRequest();
		$token = $this->rememberMeServices->autoLogin($request);
		if (!$token) return;

		try {
			$token = $this->authenticationManager->authenticate($token);
			$token->setAuthenticated(true);
			$this->tokenStorage->setToken($token);

			// Set a cookie to the attribute, afterwards CrowdResponseListener will set this cookie to the Response object
			$crowdSessionToken = $token->getUser()->getCrowdUser()->getCrowdSessionToken();
			$ssoCookie = new Cookie($this->ssoCookieName, $crowdSessionToken, 0, '/', '.' . $this->ssoCookieDomain);
			$request->attributes->set(self::SET_SSO_COOKIE_ATTR_NAME, $ssoCookie);

			if (null !== $this->dispatcher) {
				$loginEvent = new InteractiveLoginEvent($request, $token);
				$this->dispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
			}

			if ($this->logger) {
				$this->logger->debug('TokenStorage populated with remember-me token.');
			}
		} catch (AuthenticationException $e) {
			$this->rememberMeServices->loginFail($request);
		}
	}
}
