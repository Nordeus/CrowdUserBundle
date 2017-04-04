<?php
namespace Nordeus\CrowdUserBundle\CrowdService\Exceptions;

/**
 * InvalidSsoTokenException is thrown if a provided token is invalid.
 * It could be thrown during validating SSO session (POST/session/{token}, 404),
 * or during fetching User from the cookie token (GET/session/{token}, 404)
 */
class InvalidSsoTokenException extends CrowdException {

	public function __construct($message = '', $code = 0, $previous = null) {
		if (empty($message)) {
			$message = 'Given SSO token is invalid';
		}
		parent::__construct($message, $code, $previous);
	}

	public function getMessageForUser() {
		return 'Your authentication token is not valid anymore. Please try to log in again.';
	}
}
