<?php

namespace Nordeus\CrowdUserBundle\Tests\Security\Authentication;

use Nordeus\CrowdUserBundle\CrowdService\Exceptions\ApplicationAccessDeniedException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\InvalidUserAuthenticationException;
use Nordeus\CrowdUserBundle\Security\Authentication\CrowdAuthenticationProvider;
use Nordeus\CrowdUserBundle\Security\Authentication\CrowdAuthenticationToken;
use Nordeus\CrowdUserBundle\Security\User\CrowdUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class CrowdAuthenticationProviderTest extends TestCase {

	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject|UserProviderInterface
	 */
	private $userProvider;

	/**
	 * @var CrowdAuthenticationProvider
	 */
	private $authenticationProvider;

	const USERNAME = 'testuser';
	const PASSWORD = 'testuser';
	const CROWD_DIRECTORY = 'crowdDirectory';
	const CROWD_UNIQUE_KEY = 'crowdUniqueUserKey';
	const CROWD_SESSION_TOKEN = 'crowdsessiontoken';

	private $crowdUserRawData = array(
		'name'			=> 'testuser',
		'first-name'	=> 'Tester',
		'last-name'		=> 'User',
		'display-name'	=> 'Tester User',
		'email'			=> 'testuser@mail.com',
		'active'		=> true,
		'key'			=> self:: CROWD_DIRECTORY . ':' . self::CROWD_UNIQUE_KEY,
		'token'			=> self::CROWD_SESSION_TOKEN,
	);

	protected function setUp() {
		$this->userProvider = $this->getMockBuilder('Nordeus\CrowdUserBundle\Security\User\UserProvider')->disableOriginalConstructor()->getMock();
		$this->authenticationProvider = new CrowdAuthenticationProvider($this->userProvider);
	}

	public function testAuthLogin() {
		$user = new CrowdUser();
		$user->initWithCrowdData($this->crowdUserRawData);
		$user->addRole('ROLE_USER');

		$this->userProvider
			->expects($this->once())
			->method('createCrowdSessionToken')
			->with(self::USERNAME, self::PASSWORD)
			->will($this->returnValue(self::CROWD_SESSION_TOKEN));

		$this->userProvider
			->expects($this->once())
			->method('getUserByToken')
			->with(self::CROWD_SESSION_TOKEN)
			->will($this->returnValue($user));

		$authType = CrowdAuthenticationToken::AUTH_TYPE_LOGIN;

		$crowdAuthToken = new CrowdAuthenticationToken($authType, self::USERNAME);
		$crowdAuthToken->setPlainPassword(self::PASSWORD);

		$expectedToken = new CrowdAuthenticationToken($authType, $user, $user->getRoles());

		$this->assertEquals($expectedToken, $this->authenticationProvider->authenticate($crowdAuthToken));
	}

	public function testAuthSSO() {
		$user = new CrowdUser();
		$user->initWithCrowdData($this->crowdUserRawData);
		$user->addRole('ROLE_USER');

		$this->userProvider
			->expects($this->never())
			->method('createCrowdSessionToken');

		$this->userProvider
			->expects($this->once())
			->method('getUserByToken')
			->with(self::CROWD_SESSION_TOKEN)
			->will($this->returnValue($user));

		$authType = CrowdAuthenticationToken::AUTH_TYPE_SSO;

		$crowdAuthToken = new CrowdAuthenticationToken($authType);
		$crowdAuthToken->setCrowdCookieToken(self::CROWD_SESSION_TOKEN);

		$expectedToken = new CrowdAuthenticationToken($authType, $user, $user->getRoles());

		$this->assertEquals($expectedToken, $this->authenticationProvider->authenticate($crowdAuthToken));
	}

	public function testAuthRememberMe() {
		$user = new CrowdUser();
		$user->initWithCrowdData($this->crowdUserRawData);
		$user->addRole('ROLE_USER');

		$this->userProvider
			->expects($this->once())
			->method('createCrowdSessionTokenWithoutPassword')
			->with(self::USERNAME)
			->will($this->returnValue(self::CROWD_SESSION_TOKEN));

		$this->userProvider
			->expects($this->once())
			->method('getUserByToken')
			->with(self::CROWD_SESSION_TOKEN)
			->will($this->returnValue($user));

		$authType = CrowdAuthenticationToken::AUTH_TYPE_REMEMBER_ME;

		$crowdAuthToken = new CrowdAuthenticationToken($authType, self::USERNAME);
		$expectedToken = new CrowdAuthenticationToken($authType, $user, $user->getRoles());

		$this->assertEquals($expectedToken, $this->authenticationProvider->authenticate($crowdAuthToken));
	}

	// Send Auth token, but with any expected data in it.
	// provider should return null
	public function testAuthWrongData() {
		$this->userProvider
			->expects($this->never())
			->method('createCrowdSessionToken');

		$this->userProvider
			->expects($this->never())
			->method('getUserByToken');

		$crowdAuthToken = new CrowdAuthenticationToken($authType = CrowdAuthenticationToken::AUTH_TYPE_REMEMBER_ME);

		$this->assertNull($this->authenticationProvider->authenticate($crowdAuthToken));
	}

	/**
	 * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
	 */
	public function testAuthWrongCredentials() {
		$this->userProvider
			->expects($this->once())
			->method('createCrowdSessionToken')
			->with(self::USERNAME, self::PASSWORD)
			->will($this->throwException(new InvalidUserAuthenticationException()));

		$crowdAuthToken = new CrowdAuthenticationToken($authType = CrowdAuthenticationToken::AUTH_TYPE_LOGIN, self::USERNAME);
		$crowdAuthToken->setPlainPassword(self::PASSWORD);

		$this->authenticationProvider->authenticate($crowdAuthToken);
	}

	/**
	 * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
	 * @expectedExceptionCode 403
	 */
	public function testAuthAccessDenied() {
		$this->userProvider
			->expects($this->once())
			->method('createCrowdSessionToken')
			->with(self::USERNAME, self::PASSWORD)
			->will($this->throwException(new ApplicationAccessDeniedException()));

		$crowdAuthToken = new CrowdAuthenticationToken($authType = CrowdAuthenticationToken::AUTH_TYPE_LOGIN, self::USERNAME);
		$crowdAuthToken->setPlainPassword(self::PASSWORD);

		$this->authenticationProvider->authenticate($crowdAuthToken);
	}
}
