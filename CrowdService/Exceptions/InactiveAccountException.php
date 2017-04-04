<?php
namespace Nordeus\CrowdUserBundle\CrowdService\Exceptions;

/**
 * InactiveAccountException is thrown if a User is no longer active
 * It could be thrown during creating session (POST/session, 403)
 */
class InactiveAccountException extends CrowdException {

	public function __construct($message = '', $code = 0, $previous = null) {
		if (empty($message)) {
			$message = 'User account is inactive';
		}
		parent::__construct($message, $code, $previous);
	}

	public function getMessageForUser() {
		return 'Account is inactive';
	}
}
