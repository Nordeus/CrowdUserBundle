<?php
namespace Nordeus\CrowdUserBundle\CrowdService\Exceptions;

/**
 * ApplicationAccessDeniedException is thrown if user does not have access to the Crowd application.
 */
class ApplicationAccessDeniedException extends CrowdException {
	
	public function __construct($message = '', $code = 403, $previous = null) {
		if (empty($message)) {
			$message = $this->getMessageForUser();
		}
		parent::__construct($message, $code, $previous);
	}
	
	public function getMessageForUser() {
		return 'Your account does not have access to this application';
	}
}
