<?php

namespace Nordeus\CrowdUserBundle\Util;

use Nordeus\CrowdUserBundle\CrowdService\Exceptions\ApplicationAccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * By adding "access_denied_handler: crowd.access_denied_handler" to the firewall configuration in security.yml,
 * this class will serve to handle "access denied" exceptions by writing the error to the security context, and forwarding to login page.
 */
class AccessDeniedHandler implements AccessDeniedHandlerInterface {

	private $httpKernel;
	private $httpUtils;
	private $router;

	/**
	 * @param HttpKernelInterface $httpKernel
	 * @param HttpUtils $httpUtils
	 * @param Router $router
	 */
	public function __construct(HttpKernelInterface $httpKernel, HttpUtils $httpUtils, Router $router) {
		$this->httpKernel = $httpKernel;
		$this->httpUtils = $httpUtils;
		$this->router = $router;
	}

	/**
	 * @see \Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface::handle()
	 */
	public function handle(Request $request, AccessDeniedException $accessDeniedException) {
		/*
		 * This must be done with a subRequest, because the alternative is setting the error in the Symfony session object and then returning a RedirectResponse,
		 * but the session object cannot be modified in this method, and if attempted it results in an unhandled error which locks up the application completely.
		 */
		$subRequest = $this->httpUtils->createRequest($request, 'nordeus_crowd_user_login');
		$subRequest->attributes->set(Security::AUTHENTICATION_ERROR, new ApplicationAccessDeniedException());

		return $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
	}
}
