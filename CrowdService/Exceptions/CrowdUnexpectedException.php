<?php
namespace Nordeus\CrowdUserBundle\CrowdService\Exceptions;

/**
 * CrowdUnexpectedException could be thrown in many cases, but it's likely because some unexpected Crowd's response.
 * E.g. create session token Crowd call should return data which has field 'token', if the field is missing this exception will be thrown.
 * It is possible to store received data in order to log that afterwards.
 */
class CrowdUnexpectedException extends CrowdException {
	
	protected $logData;
	
	public function __construct($message, $action, $data, $code = 0, $previous = null) {
		parent::__construct($message, $code, $previous);
		$this->logData = array(
			'action' => $action,
			'receivedData' => $data,
		);
	}
	
	public function getLogData() {
		return $this->logData;
	}
	
	public function getMessageForUser() {
		return 'Unexpected problem has occured in communication with Crowd server. Please try again later.';
	}
}