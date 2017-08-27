<?php

namespace Nordeus\CrowdUserBundle\Security\Authentication;

use Nordeus\CrowdUserBundle\Security\User\UserProvider;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\User\ChainUserProvider;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\ApplicationAccessDeniedException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\CrowdException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\CrowdUnexpectedException;
use Psr\Log\LoggerInterface;

class CrowdAuthenticationProvider implements AuthenticationProviderInterface {

	/** @var UserProvider */
	private $userProvider;
	private $logger;

	/**
	 * Constructor
	 * 
	 * @param UserProviderInterface $userProvider
	 * @param LoggerInterface $logger
	 */
	public function __construct(UserProviderInterface $userProvider, LoggerInterface $logger = null) {
		if ($userProvider instanceof UserProvider) {
			$this->userProvider = $userProvider;
		} else if ($userProvider instanceof ChainUserProvider) {
			$chainedProvidersCollection = $userProvider->getProviders();
			foreach ($chainedProvidersCollection as $chainedProvider) {
				if ($chainedProvider instanceof UserProvider) {
					if (empty($this->userProvider)) {
						$this->userProvider = $chainedProvider;
					} else {
						throw new \LogicException('Only single CrowdUserProvider is supported to be chained');
					}
				}
			}
		} else {
			throw new \LogicException('Only CrowdUserProvider and ChainedUserProviders are supported');
		}
		$this->logger = $logger;
	}

	/**
	 * Authenticates token given by Firewall listener.
	 *
	 * Firewall listeners could be:
	 *  - SSO listener - provides crowdCookieSessionToken
	 *  - Login listener - provides username and password
	 *  - Remember-me listener - provides only username
	 *
	 * @param CrowdAuthenticationToken $token
	 * @throws AuthenticationException
	 * @return CrowdAuthenticationToken
	 * @see \Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface::authenticate()
	 */
	public function authenticate(TokenInterface $token) {
		try {
			$crowdCookieSessionToken = $token->getCrowdCookieToken();
			$plainPassword = $token->getPlainPassword();
			$username = $token->getUser();

			if (empty($crowdCookieSessionToken)) {
				if (!empty($plainPassword)) {
					// Login Listener
					$crowdCookieSessionToken = $this->userProvider->createCrowdSessionToken($username, $plainPassword);
				} elseif (!empty($username) && is_string($username)) {
					// Remember-me listener
					$crowdCookieSessionToken = $this->userProvider->createCrowdSessionToken($username);
				} else {
					return null;
				}
			}

			$user = $this->userProvider->getUserByToken($crowdCookieSessionToken);
			return new CrowdAuthenticationToken($user, $user->getRoles());

		} catch (ApplicationAccessDeniedException $e) {
			/*
			 * This exception occurs if authentication failed because user does not have access to the Crowd application.
			 * In that case, a new AuthenticationException is thrown with the code 403, which signalizes an "access denied" error.
			 */
			throw new AuthenticationException($e->getMessageForUser(), 403, $e);
		} catch (CrowdUnexpectedException $e) {
			if ($this->logger) {
				$this->logger->warning($e->getMessage(), $e->getLogData());
			}
			throw new AuthenticationException($e->getMessageForUser(), 0, $e);
		} catch (CrowdException $e) {
			throw new AuthenticationException($e->getMessageForUser(), 0, $e);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports(TokenInterface $token) {
		return $token instanceof CrowdAuthenticationToken;
	}
}
