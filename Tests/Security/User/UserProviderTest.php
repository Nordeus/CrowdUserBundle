<?php

namespace Nordeus\CrowdUserBundle\Tests\Security\User;

use Nordeus\CrowdUserBundle\CrowdService\CrowdService;
use Nordeus\CrowdUserBundle\Security\User\UserProvider;
use Nordeus\CrowdUserBundle\Security\User\CrowdUser;
use Nordeus\CrowdUserBundle\Tests\util\ExtCrowdUser;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\CrowdUnexpectedException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\InvalidUserAuthenticationException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\InactiveAccountException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\UserNotFoundException;
use PHPUnit\Framework\TestCase;

class UserProviderTest extends TestCase {

	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject|CrowdService
	 */
	private $crowdService;

	/**
	 * @var UserProvider
	 */
	private $userProvider;

	const USERNAME = 'testuser';
	const PASSWORD = 'testuser';
	const CROWD_SESSION_TOKEN = 'crowdsessiontoken';
	const USER_REFRESH_TIME = 10;
	const USER_CLASS = 'Nordeus\CrowdUserBundle\Tests\util\ExtCrowdUser';

	private $rolesToCrowdGroupsMap = array(
		'ROLE_USER' => array('jira-users', 'dummy-group', 'testers-group',),
	);

	private $crowdUserRawData = array(
		'name'			=> 'testuser',
		'first-name'	=> 'Tester',
		'last-name'		=> 'User',
		'display-name'	=> 'Tester User',
		'email'			=> 'testuser@mail.com',
		'active'		=> true,
		'token'			=> self::CROWD_SESSION_TOKEN,
	);

	private $crowdUserGroups = array(
		'jira-users', 'dashboard-users', 'admins-group',
	);

	protected function setUp() {
		$this->crowdService = $this->getMockBuilder('Nordeus\CrowdUserBundle\CrowdService\CrowdService')->disableOriginalConstructor()->getMock();

		$this->userProvider = new UserProvider($this->crowdService, $this->rolesToCrowdGroupsMap, self::USER_REFRESH_TIME, self::USER_CLASS);
	}

	/**
	 * This function shouldn't be invoked, it always throw UsernameNotFoundException
	 * 
	 * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
	 */
	public function testLoadUserByUsername() {
		$this->userProvider->loadUserByUsername(self::USERNAME);
	}

	public function testRefreshUser() {
		$user = new CrowdUser();
		$lastRefreshedTime = time();
		$user->setUsername(self::USERNAME);
		$user->setLastTimeRefreshed($lastRefreshedTime);
		$user->setCrowdSessionToken(self::CROWD_SESSION_TOKEN);
		$user->setFirstName($this->crowdUserRawData['first-name'] . '123');


		/** @var CrowdUser $newUser */
		$newUser = $this->userProvider->refreshUser($user);
		$this->assertSame($user, $newUser);
		$this->assertEquals($lastRefreshedTime, $newUser->getLastTimeRefreshed());

		$user->setLastTimeRefreshed(time() - 5 * self::USER_REFRESH_TIME);

		$this->crowdService
			->expects($this->never())
			->method('createSessionToken');

		$this->crowdService
			->expects($this->once())
			->method('getUserFromToken')
			->with(self::CROWD_SESSION_TOKEN)
			->will($this->returnValue($this->crowdUserRawData));

		$this->crowdService
			->expects($this->once())
			->method('getUserGroups')
			->with(self::USERNAME)
			->will($this->returnValue($this->crowdUserGroups));

		$expectedUser = new CrowdUser($this->crowdUserRawData);
		$expectedUser->addRole('ROLE_USER');

		$timeBefore = time();
		$newUser = $this->userProvider->refreshUser($user);
		$timeAfter = time();

		$this->assertSame($user, $newUser);
		$this->assertEquals($newUser->getFirstName(), $this->crowdUserRawData['first-name']);
		$this->assertGreaterThanOrEqual($newUser->getLastTimeRefreshed(), $timeBefore);
		$this->assertGreaterThanOrEqual($timeAfter, $newUser->getLastTimeRefreshed());
	}

	public function testRefreshExtUser() {
		$extUser = new ExtCrowdUser();
		$extUser->setUsername(self::USERNAME);
		$extUser->setCrowdSessionToken(self::CROWD_SESSION_TOKEN);
		$extUser->forceToRefreshOnNextRequest();
		$customFieldValue = 7;
		$extUser->customField = $customFieldValue;

		$this->crowdService
			->expects($this->once())
			->method('getUserFromToken')
			->with(self::CROWD_SESSION_TOKEN)
			->will($this->returnValue($this->crowdUserRawData));

		$this->crowdService
			->expects($this->once())
			->method('getUserGroups')
			->with(self::USERNAME)
			->will($this->returnValue($this->crowdUserGroups));

		$expectedUser = new ExtCrowdUser();
		$expectedUser->initWithCrowdData($this->crowdUserRawData);
		$expectedUser->addRole('ROLE_USER');

		$refreshedUser = $this->userProvider->refreshUser($extUser);

		$this->assertSame($refreshedUser, $extUser);
		$this->assertEquals($extUser->customField, $customFieldValue);
	}

	/**
	 * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
	 */
	public function testRefreshUserFail() {
		$user = new CrowdUser();
		$user->setUsername(self::USERNAME);
		$user->forceToRefreshOnNextRequest();
		$user->setCrowdSessionToken(self::CROWD_SESSION_TOKEN);

		$this->crowdService
			->expects($this->never())
			->method('createSessionToken');

		$this->crowdService
			->expects($this->once())
			->method('getUserFromToken')
			->with(self::CROWD_SESSION_TOKEN)
			->will($this->throwException(new InactiveAccountException()));

		$this->userProvider->refreshUser($user);
	}

	public function testCreateCrowdSessionToken() {
		$this->crowdService
			->expects($this->once())
			->method('createSessionToken')
			->with(self::USERNAME, self::PASSWORD)
			->will($this->returnValue(self::CROWD_SESSION_TOKEN));

		$this->crowdService
			->expects($this->once())
			->method('createSessionTokenWithoutPassword')
			->with(self::USERNAME)
			->will($this->returnValue(self::CROWD_SESSION_TOKEN));

		$this->assertEquals(self::CROWD_SESSION_TOKEN, $this->userProvider->createCrowdSessionToken(self::USERNAME, self::PASSWORD));
		$this->assertEquals(self::CROWD_SESSION_TOKEN, $this->userProvider->createCrowdSessionToken(self::USERNAME));
	}


	public function testGetUserByToken() {
		$this->crowdService
			->expects($this->never())
			->method('createSessionToken');

		$this->crowdService
			->expects($this->once())
			->method('getUserFromToken')
			->with(self::CROWD_SESSION_TOKEN)
			->will($this->returnValue($this->crowdUserRawData));

		$this->crowdService
			->expects($this->once())
			->method('getUserGroups')
			->with(self::USERNAME)
			->will($this->returnValue($this->crowdUserGroups));

		$expectedUser = new ExtCrowdUser();
		$expectedUser->initWithCrowdData($this->crowdUserRawData);
		$expectedUser->addRole('ROLE_USER');

		$this->assertEquals($expectedUser, $this->userProvider->getUserByToken(self::CROWD_SESSION_TOKEN));
	}

	public function testSupportsClass() {
		$this->assertTrue($this->userProvider->supportsClass(get_class(new CrowdUser())));
		$this->assertTrue($this->userProvider->supportsClass(get_class(new ExtCrowdUser())));
	}

	/**
	 * @expectedException \Nordeus\CrowdUserBundle\CrowdService\Exceptions\CrowdUnexpectedException
	 */
	public function testCreateCrowdSessionTokenFail() {
		$this->crowdService
			->expects($this->once())
			->method('createSessionToken')
			->with(self::USERNAME, self::PASSWORD)
			->will($this->throwException(new CrowdUnexpectedException('msg', 'createSessionToken', 'sth')));

		$this->userProvider->createCrowdSessionToken(self::USERNAME, self::PASSWORD);
	}

	/**
	 * @expectedException \Nordeus\CrowdUserBundle\CrowdService\Exceptions\InvalidUserAuthenticationException
	 */
	public function testCreateSessionTokenWithInvalidPassword() {
		$this->crowdService
			->expects($this->once())
			->method('createSessionToken')
			->with(self::USERNAME, self::PASSWORD)
			->will($this->throwException(new InvalidUserAuthenticationException()));

		$this->userProvider->createCrowdSessionToken(self::USERNAME, self::PASSWORD);
	}

	public function testGetUserByUsername() {
		$crowdRawData = $this->crowdUserRawData;
		unset($crowdRawData['token']);
		$expectedUser = new ExtCrowdUser();
		$expectedUser->initWithCrowdData($crowdRawData);
		$expectedUser->addRole('ROLE_USER');

		$this->crowdService
			->expects($this->once())
			->method('getUserDataFromName')
			->with(self::USERNAME)
			->will($this->returnValue($crowdRawData));

		$this->crowdService
			->expects($this->once())
			->method('getUserGroups')
			->with(self::USERNAME)
			->will($this->returnValue($this->crowdUserGroups));

		$this->assertEquals($expectedUser, $this->userProvider->getUserByUsername(self::USERNAME));
	}

	/**
	 * @expectedException \Nordeus\CrowdUserBundle\CrowdService\Exceptions\UserNotFoundException
	 */
	public function testGetUserByUsernameFail() {
		$this->crowdService
			->expects($this->once())
			->method('getUserDataFromName')
			->with(self::USERNAME)
			->will($this->throwException(new UserNotFoundException()));

		$this->userProvider->getUserByUsername(self::USERNAME);
	}

	public function testGetUsernamesByRole() {
		$usernames = array(
			'jira-users'	=> array('user1', 'admin', 'john', 'jack'),
			'dummy-group'	=> array(),
			'testers-group'	=> array('admin', 'mike'),
		);
		$expectedUsernames = array(
			'user1', 'admin', 'john', 'jack', 'mike',
		);

		$this->crowdService
			->expects($this->any())
			->method('getUsersByGroupName')
			->will($this->onConsecutiveCalls(
					$usernames['jira-users'],
					$usernames['dummy-group'],
					$usernames['testers-group']
			));

		$actualUsernames = $this->userProvider->getUsernamesByRole('ROLE_USER');

		sort($expectedUsernames);
		sort($actualUsernames);
		$this->assertEquals($expectedUsernames, $actualUsernames);
	}
}
