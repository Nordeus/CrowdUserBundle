<?php

namespace Nordeus\CrowdUserBundle\Security\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Firewall\AbstractAuthenticationListener;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Psr\Log\LoggerInterface;

class CrowdLoginAuthenticationListener extends AbstractAuthenticationListener {

	private $ssoCookieDomain;
	/** @var CsrfTokenManagerInterface $csrfTokenManager */
	private $csrfTokenManager;

	/**
	 *  {@inheritdoc}
	 */
	public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, SessionAuthenticationStrategyInterface $sessionStrategy, HttpUtils $httpUtils, $providerKey, AuthenticationSuccessHandlerInterface $successHandler, AuthenticationFailureHandlerInterface $failureHandler, array $options = array(), LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null, $ssoCookieDomain, CsrfTokenManagerInterface $csrfTokenManager = null) {
		$options =  array_merge(array(
			'username_parameter' => '_username',
			'password_parameter' => '_password',
			'csrf_parameter' => '_csrf_token',
			'csrf_token_id' => 'authenticate',
			'post_only' => true,
		), $options);

		parent::__construct($tokenStorage, $authenticationManager, $sessionStrategy, $httpUtils, $providerKey, $successHandler, $failureHandler, $options, $logger, $dispatcher);
		$this->ssoCookieDomain = $ssoCookieDomain;
		$this->csrfTokenManager = $csrfTokenManager;
	}
	
	/**
	 *  {@inheritdoc}
	 */
	protected function attemptAuthentication(Request $request) {
		if ($this->options['post_only'] && 'post' !== strtolower($request->getMethod())) {
			if (null !== $this->logger) {
				$this->logger->debug(sprintf('Authentication method not supported: %s.', $request->getMethod()));
			}
			return null;
		}

		if (null !== $this->csrfTokenManager) {
			$csrfToken = $request->get($this->options['csrf_parameter']);

			if (false === $this->csrfTokenManager->isTokenValid(new CsrfToken($this->options['csrf_token_id'], $csrfToken))) {
				throw new InvalidCsrfTokenException('Invalid CSRF token.');
			}
		}

		// If ssoCookieDomain is not a substring at the end of the application domain, it is considered invalid
		if (substr($request->getHost(), -strlen($this->ssoCookieDomain)) !== $this->ssoCookieDomain) {
			throw new AuthenticationException('SSO cookie domain does not match the application domain.');
		}

		$username = trim($request->get($this->options['username_parameter']));
		$password = $request->get($this->options['password_parameter']);

		$request->getSession()->set(Security::LAST_USERNAME, $username);

		$token = new CrowdAuthenticationToken($username);
		$token->setPlainPassword($password);
		
		return $this->authenticationManager->authenticate($token);
	}
}
