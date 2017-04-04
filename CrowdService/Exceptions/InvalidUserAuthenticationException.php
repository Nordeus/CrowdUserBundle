<?php
namespace Nordeus\CrowdUserBundle\CrowdService\Exceptions;

/**
 * InvalidUserAuthenticationException is thrown if a User gave either wrong username or password.
 * It could be thrown during creating session token (POST/session, 400)
 */
class InvalidUserAuthenticationException extends CrowdException {
	public function __construct($message = '', $code = 0, $previous = null) {
		if (empty($message)) {
			$message = 'Authentication details, username or/and password, are incorrect.';
		}
		parent::__construct($message, $code, $previous);
	}
	
	public function getMessageForUser() {
		return 'Invalid credentials.';
	}
}