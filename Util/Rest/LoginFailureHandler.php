<?php

namespace Nordeus\CrowdUserBundle\Util\Rest;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;

class LoginFailureHandler extends DefaultAuthenticationFailureHandler {

	public function onAuthenticationFailure(Request $request, AuthenticationException $exception) {
		return new JsonResponse([
			'message'=> $exception->getMessage(),
			'code' => Response::HTTP_UNAUTHORIZED,
		], Response::HTTP_UNAUTHORIZED);
	}
}
