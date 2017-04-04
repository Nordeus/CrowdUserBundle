<?php

namespace Nordeus\CrowdUserBundle\Tests\CrowdService;

use Curl\Curl;
use Nordeus\CrowdUserBundle\CrowdService\CrowdService;
use PHPUnit\Framework\TestCase;

class CrowdServiceTest extends TestCase {

	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject|Curl
	 */
	private $curl;
	
	/**
	 * @var CrowdService
	 */
	private $crowdService;
	
	const APP_NAME = 'crowdtest';
	const APP_PASSWORD = 'crowdtest';
	const URL = 'http://crowd.test.com:8095';
	const BASE_URI = '/crowd/rest/usermanagement/1/';
	const CURL_TIMEOUT = 2;
	const RETRIES = 2;
	
	private $crowdUrl;
	
	const USERNAME = 'usertest';
	const PASSWORD = 'usertest';
	const CROWD_SESSION_TOKEN = 'crowdsessiontoken';
	
	protected function setUp() {
		$this->crowdUrl = self::URL . self::BASE_URI;
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		
		$this->curl = $this->createMock('Curl\Curl');
		
		$this->crowdService = new CrowdService(self::APP_NAME, self::APP_PASSWORD, self::URL, self::BASE_URI, $this->curl, self::CURL_TIMEOUT, self::RETRIES);
	}
	
	public function testConstructorInit() {
		/** @var \PHPUnit_Framework_MockObject_MockObject|Curl $curl */
		$curl = $this->createMock('Curl\Curl');
		$curl
			->expects($this->once())
			->method('setOpt')
			->with(CURLOPT_CONNECTTIMEOUT, self::CURL_TIMEOUT);
		
		$curl
			->expects($this->exactly(3))
			->method('setHeader')
			->withConsecutive(
				['Authorization: Basic ', base64_encode(self::APP_NAME . ':' . self::APP_PASSWORD)],
				['Content-Type', 'application/json'],
				['Accept', 'application/json']
			);

		new CrowdService(self::APP_NAME, self::APP_PASSWORD, self::URL, self::BASE_URI, $curl, self::CURL_TIMEOUT, self::RETRIES);
	}
	
	public function testCreateSessionToken() {
		$data = array(
			'username' => self::USERNAME,
			'password' => self::PASSWORD,
			'validation-factors' => array(
				'validationFactors' => array(
					array(
						'name' => 'remote_address',
						'value' => '127.0.0.1',
					)
				)
			)
		);
		$data = json_encode($data);
		$url = $this->crowdUrl . 'session';
		
		$this->curl->rawResponse = json_encode(array('token' => self::CROWD_SESSION_TOKEN));
		$this->curl->httpStatusCode = 201;
		
		$this->curl
			->expects($this->once())
			->method('post')
			->with($url, $data);
		
		$this->assertEquals(self::CROWD_SESSION_TOKEN, $this->crowdService->createSessionToken(self::USERNAME, self::PASSWORD));
	}
	
	/**
	 * @expectedException \Nordeus\CrowdUserBundle\CrowdService\Exceptions\CrowdUnexpectedException
	 */
	public function testCreateSessionTokenFail() {
		$this->curl->httpStatusCode = 400;
		$this->curl
			->expects($this->once())
			->method('post');
		
		$this->crowdService->createSessionToken(self::USERNAME, self::PASSWORD);
	}
	
	/**
	 * @expectedException \Nordeus\CrowdUserBundle\CrowdService\Exceptions\InvalidUserAuthenticationException
	 */
	public function testCreateSessionTokenInvalidAuth() {
		$this->curl->rawResponse = json_encode(array('reason' => 'INVALID_USER_AUTHENTICATION'));
		$this->curl->httpStatusCode = 400;
		
		$this->curl
			->expects($this->once())
			->method('post');
	
		$this->crowdService->createSessionToken(self::USERNAME, self::PASSWORD);
	}
	
	/**
	 * @expectedException \Nordeus\CrowdUserBundle\CrowdService\Exceptions\ApplicationAccessDeniedException
	 */
	public function testCreateSessionTokenAccessDenied() {
		$this->curl->rawResponse = json_encode(array('reason' => 'APPLICATION_ACCESS_DENIED'));
		$this->curl->httpStatusCode = 400;
		
		$this->curl
			->expects($this->once())
			->method('post');
		
		$this->crowdService->createSessionToken(self::USERNAME, self::PASSWORD);
	}
}