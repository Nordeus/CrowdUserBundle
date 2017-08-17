<?php
namespace Nordeus\CrowdUserBundle\CrowdService\Exceptions;

abstract class CrowdException extends \Exception {

	public function getMessageForUser() {
		return '';
	}
}
