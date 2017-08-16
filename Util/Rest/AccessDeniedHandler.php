<?php

namespace Nordeus\CrowdUserBundle\Util\Rest;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface {

	public function handle(Request $request, AccessDeniedException $accessDeniedException) {
		return new JsonResponse([
			'message' => $accessDeniedException->getMessage(),
			'code' => Response::HTTP_FORBIDDEN,
		], Response::HTTP_FORBIDDEN);
	}
}
