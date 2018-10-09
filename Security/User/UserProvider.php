<?php

namespace Nordeus\CrowdUserBundle\Security\User;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\CrowdException;
use Nordeus\CrowdUserBundle\CrowdService\CrowdService;
use Psr\Log\LoggerInterface;

class UserProvider implements UserProviderInterface {

	protected $crowdService;
	protected $rolesToCrowdGroupsMap;
	protected $userRefreshTime;
	protected $userClass;
	protected $logger;

	/**
	 * Constructor
	 *
	 * @param CrowdService		$crowdService
	 * @param array				$rolesToCrowdGroupsMap
	 * @param integer			$userRefreshTime
	 * @param string			$userClass
	 * @param LoggerInterface	$logger
	 */
	public function __construct(CrowdService $crowdService, $rolesToCrowdGroupsMap, $userRefreshTime, $userClass, LoggerInterface $logger = null) {
		$this->crowdService = $crowdService;
		$this->rolesToCrowdGroupsMap = $rolesToCrowdGroupsMap;
		$this->userRefreshTime = $userRefreshTime;
		$this->userClass = $userClass;
		$this->logger = $logger;
	}

	/**
	 * Always throws UsernameNotFoundException - invoking this method is unexpected behaviour.
	 *
	 * @param string $username
	 * @return void
	 * @throws UsernameNotFoundException
	 */
	public function loadUserByUsername($username) {
		throw new UsernameNotFoundException('Username not found');
	}

	/**
	 * ContextListener invokes this method on every request,
	 * but it fetches user from Crowd (refresh) if user is not refreshed more than $this->userRefreshTime seconds
	 *
	 * If any kind of CrowdException occures it throws an UsernameNotFoundException,
	 * which ContextListener catches and sets null to token in tokenStorage - afterwards User will have to log in again.
	 *
	 * @param CrowdUser $user
	 * @throws UsernameNotFoundException
	 * @return CrowdAppUserInterface
	 */
	public function refreshUser(UserInterface $user) {
		if (!$this->supportsClass(get_class($user))) {
			return $user;
		}

		$crowdUser = $user->getCrowdUser();
		$lastTimeRefreshed = $crowdUser->getLastTimeRefreshed();
		if (empty($lastTimeRefreshed) || (time() - $lastTimeRefreshed > $this->userRefreshTime)) {
			try {
				$userRawData = $this->crowdService->getUserFromToken($crowdUser->getCrowdSessionToken());
				$crowdUser->initWithCrowdData($userRawData);

				$userRoles = $this->getUserRolesByUsername($crowdUser->getUsername());
				$crowdUser->setRoles($userRoles);

				$crowdUser->setLastTimeRefreshed(time());
				return $this->getAppUser($crowdUser);
			} catch (CrowdException $e) {
				throw new UsernameNotFoundException('User can not be refreshed.', 0, $e);
			}
		}
		return $user;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supportsClass($class) {
		return in_array(CrowdAppUserInterface::class, class_implements($class));
	}

	/**
	 * Creates Crowd session token with password (used for login).
	 *
	 * @param string $username
	 * @param string $password
	 * @return string
	 */
	public function createCrowdSessionToken($username, $password) {
		return $crowdSessionToken = $this->crowdService->createSessionToken($username, $password);
	}

	/**
	 * Gets User from Crowd by Crowd session token.
	 *
	 * @param string $crowdSessionToken
	 * @throws CrowdException
	 * @return CrowdAppUserInterface
	 */
	public function getUserByToken($crowdSessionToken) {
		$userRawData = $this->crowdService->getUserFromToken($crowdSessionToken);
		$user = $this->createUser();
		$user->initWithCrowdData($userRawData);

		$userRoles = $this->getUserRolesByUsername($user->getUsername());
		$user->setRoles($userRoles);

		return $this->getAppUser($user);
	}

	/**
	 * Gets User by username.
	 * Fetching Crowd attributes is optional.
	 *
	 * @param string $username
	 * @param boolean $expandUserWithCrowdAttributes
	 * @throws CrowdException
	 * @return CrowdAppUserInterface
	 */
	public function getUserByUsername($username, $expandUserWithCrowdAttributes = false) {
		$userRawData = $this->crowdService->getUserDataFromName($username, $expandUserWithCrowdAttributes);
		$user = $this->createUser();
		$user->initWithCrowdData($userRawData);

		$userRoles = $this->getUserRolesByUsername($user->getUsername());
		$user->setRoles($userRoles);

		return $this->getAppUser($user);
	}

	/**
	 * Gets User's roles.
	 * Fetches all user Crowd groups, then it checks for each defined ROLE if some Crowd group belogns to the ROLE
	 *
	 * @param string $username
	 * @throws CrowdException
	 * @return array
	 */
	public function getUserRolesByUsername($username) {
		$userRoles = array();
		if (!empty($this->rolesToCrowdGroupsMap)) {
			$crowdGroups = $this->crowdService->getUserGroups($username);
			foreach ($this->rolesToCrowdGroupsMap as $role => $groups) {
				$allowedGroupsForRole = array_intersect($crowdGroups, $groups);
				if (!empty($allowedGroupsForRole)) {
					$userRoles[] = $role;
				}
			}
		}
		return $userRoles;
	}
	
	/**
	 * Get all usernames for given role
	 * 
	 * @param string $role
	 * @throws CrowdException
	 * @return array
	 */
	public function getUsernamesByRole($role) {
		$usernames = array();
		if (!empty($this->rolesToCrowdGroupsMap[$role])) {
			foreach ($this->rolesToCrowdGroupsMap[$role] as $crowdGroupName) {
				$crowdUsernames = $this->crowdService->getUsersByGroupName($crowdGroupName);
				$usernames = array_merge($usernames, $crowdUsernames);
			}
		}
		return array_unique($usernames);
	}
	
	protected function createUser() {
		$user = new $this->userClass;
		return $user;
	}

	/**
	 * @param CrowdUser $crowdUser
	 * @return CrowdAppUserInterface
	 */
	protected function getAppUser(CrowdUser $crowdUser) {
		return $crowdUser;
	}

}
