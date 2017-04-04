<?php

namespace Nordeus\CrowdUserBundle\CrowdService;

use Curl\Curl;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\CrowdException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\CrowdUnexpectedException;
use Nordeus\CrowdUserBundle\CrowdService\Exceptions\CrowdServerConnectionException;

class CrowdService {
	
	private $appPassword;
	protected $appName;
	protected $url;
	protected $curl;
	protected $crowdConnectionFailureRetries;
	
	public function __construct($appName, $appPassword, $url, $baseUri, Curl $curl, $curlTimeout, $crowdConnectionFailureRetries, $logger = null) {
		$this->appName = $appName;
		$this->appPassword = $appPassword;
		$this->url = $url . $baseUri;
		$this->crowdConnectionFailureRetries = $crowdConnectionFailureRetries;
		$this->curl = $curl;
		$this->curl->setOpt(CURLOPT_CONNECTTIMEOUT, $curlTimeout);
		$this->curl->setHeader('Authorization: Basic ', base64_encode($this->appName . ':' . $this->appPassword));
		$this->curl->setHeader('Content-Type', 'application/json');
		$this->curl->setHeader('Accept', 'application/json');
	}

	protected function getCurlResponse($action, $usePost = false, $params = array(), $successStatus = 200) {

		$retries = $this->crowdConnectionFailureRetries;
		$url = $this->url . $action;

		while (true) {
			if ($usePost) {
				$this->curl->post($url, $params);
			} else {
				$this->curl->get($url, $params);
			}

			if (!$this->curl->curlError) break;

			if ($retries <= 0) {
				throw new CrowdServerConnectionException($this->curl->curlErrorMessage, 0);
			}
			// try again
			$retries--;
		}

		if ($this->curl->errorCode == 401) {
			throw new CrowdUnexpectedException('Application failed to authenticate', $action, $this->curl->rawResponse);
		}

		$data = json_decode($this->curl->rawResponse, true);

		if ($data == false || $data == null) {
			throw new CrowdUnexpectedException('Content from server is not a valid', $action, $this->curl->rawResponse, $this->curl->httpStatusCode);
		}

		if ($this->curl->httpStatusCode != $successStatus) {
			throw self::getException($action, $data, $this->curl->httpStatusCode);
		}

		return $data;
	}

	protected static function getException($action, $data, $status) {
		if (isset($data['reason'])) {
			$errClass = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($data['reason']))));
			$errClass = 'Nordeus\\CrowdUserBundle\\CrowdService\\Exceptions\\' . $errClass . 'Exception';
			$msg = isset($data['message']) ? $data['message'] : null;
			
			if (class_exists($errClass)) {
				return new $errClass($msg);
			}
		}
		
		return new CrowdUnexpectedException('Unknown error received: ', $action, $data, $status);
	}

	/**
	 * @param string $username
	 * @param string $password
	 * @throws CrowdException if data is not valid or some other invalid response is returned from Crowd
	 * @return string Crowd session token
	 * @link https://developer.atlassian.com/display/CROWDDEV/Crowd+REST+Resources
	 */
	public function createSessionToken($username, $password) {
		$data = array(
			'username' => $username,
			'password' => $password,
			'validation-factors' => array(
				'validationFactors' => array(
					array(
						'name' => 'remote_address',
						'value' => $_SERVER['REMOTE_ADDR'],
					)
				)
			)
		);
		
		$action = 'session';
		$data = $this->getCurlResponse($action, true, json_encode($data), 201);
		
		if (!isset($data['token'])) {
			throw new CrowdUnexpectedException('No token field in Crowd response', $action, $data);
		}
				
		return $data['token'];
	}
	
	/**
	 * @param string $username
	 * @throws CrowdException if invalid response is returned from Crowd
	 * @return string token
	 * @link https://developer.atlassian.com/display/CROWDDEV/Crowd+REST+Resources
	 */
	public function createSessionTokenWithoutPassword($username) {
		$data = array(
			'username' => $username,
			'validation-factors' => array(
				'validationFactors' => array(
					array(
						'name' => 'remote_address',
						'value' => $_SERVER['REMOTE_ADDR'],
					)
				)
			)
		);
		
		$action = 'session?validate-password=false';
		$data = $this->getCurlResponse($action, true, json_encode($data), 201);
		
		if (!isset($data['token'])) {
			throw new CrowdUnexpectedException('No token field in Crowd response', $action, $data);
		}
		
		return $data['token'];
	}
	
	/**
	 * @param string $token Crowd token
	 * @throws CrowdException if token is not valid or some other invalid response is returned from Crowd
	 * @return array of raw user data array got from Crowd
	 * @link https://developer.atlassian.com/display/CROWDDEV/Crowd+REST+Resources
	 */
	public function getUserFromToken($token) {
		$action = "session/$token";
		$data = $this->getCurlResponse($action);
	
		if (!isset($data['user']['name'])) {
			throw new CrowdUnexpectedException('No user data in Crowd response', $action, $data);
		}
	
		$data['user']['token'] = $data['token'];
		return $data['user'];
	}
	
	/**
	 * @param string $username
	 * @throws CrowdException if Crowd can't find the user or returns some invalid response
	 * @return array of raw user data array got from Crowd
	 * @link https://developer.atlassian.com/display/CROWDDEV/Crowd+REST+Resources
	 */
	public function getUserDataFromName($username, $expandWithAtrributes = false) {
		$action = "user?username=$username";
		if ($expandWithAtrributes) {
			$action .= '&expand=attributes';
		}
	
		$data = $this->getCurlResponse($action);
	
		if (!isset($data['name'])) {
			throw new CrowdUnexpectedException('No user data in Crowd response', $action, $data);
		}
		
		return $data;
	}
	
	/**
	 * @param string $username name of the user whose groups you want to load
	 * @throws CrowdException if Crowd returns invalid response
	 * @return array of group names
	 * @link https://developer.atlassian.com/display/CROWDDEV/Crowd+REST+Resources
	 */
	public function getUserGroups($username) {
		$action = "user/group/nested?username=$username";
		$data = $this->getCurlResponse($action);
	
		if (!isset($data['groups'])) {
			throw new CrowdUnexpectedException('No groups data in Crowd response', $action, $data);
		}
	
		$groups = array();
		foreach ($data['groups'] as $groupData) {
			if (!isset($groupData['name'])) {
				throw new CrowdUnexpectedException('"name" field is not present', $action, $data);
			}
			$groups[] = $groupData['name'];
		}
	
		return $groups;
	}
	
	/**
	 * @param string $groupName
	 * @throws CrowdException if Crowd returns invalid response
	 * @return array of usernames
	 * @link https://developer.atlassian.com/display/CROWDDEV/Crowd+REST+Resources
	 */
	public function getUsersByGroupName($groupName) {
		$action = "group/user/nested?groupname=$groupName";
		$data = $this->getCurlResponse($action);
		
		if (!isset($data['users'])) {
			throw new CrowdUnexpectedException('No groups data in Crowd response', $action, $data);
		}
		
		$usernames = array();
		foreach ($data['users'] as $user) {
			if (!isset($user['name'])) {
				throw new CrowdUnexpectedException('"name" field is not present', $action, $data);
			}
			$usernames[] = $user['name'];
		}
		
		return $usernames;
	}
	
}