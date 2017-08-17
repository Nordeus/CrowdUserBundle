<?php

namespace Nordeus\CrowdUserBundle\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;

interface CrowdAppUserInterface extends UserInterface {

	/**
	 * @return CrowdUser
	 */
	function getCrowdUser();
}
