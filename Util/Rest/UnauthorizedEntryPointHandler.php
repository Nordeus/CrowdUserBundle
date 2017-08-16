<?php

namespace Nordeus\CrowdUserBundle\Util\Rest;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UnauthorizedEntryPointHandler implements AuthenticationEntryPointInterface {

	public function start(Request $request, AuthenticationException $authException = null) {
		return new JsonResponse([
			'message' => 'Not authenticated request',
			'code' => Response::HTTP_UNAUTHORIZED,
		], Response::HTTP_UNAUTHORIZED);
	}
}
