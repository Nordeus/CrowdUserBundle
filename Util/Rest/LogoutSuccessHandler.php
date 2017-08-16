<?php

namespace Nordeus\CrowdUserBundle\Util\Rest;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Logout\DefaultLogoutSuccessHandler;

class LogoutSuccessHandler extends DefaultLogoutSuccessHandler {

	public function onLogoutSuccess(Request $request) {
		return new JsonResponse(null, Response::HTTP_OK);
	}
}
