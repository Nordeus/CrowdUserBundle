<?php
namespace Nordeus\CrowdUserBundle\CrowdService\Exceptions;

/**
 * CrowdServerConnectionException is thrown if connection with Crowd server failed
 */
class CrowdServerConnectionException extends CrowdException {

	public function getMessageForUser() {
		return 'The remote authentication server is not available. Please try again later.';
	}
}
