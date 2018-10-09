<?php

namespace Nordeus\CrowdUserBundle\Security\Authentication;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CrowdResponseListener implements EventSubscriberInterface {

	/**
	 * @param FilterResponseEvent $event
	 */
	public function onKernelResponse(FilterResponseEvent $event) {
		$request = $event->getRequest();
		$response = $event->getResponse();

		if ($request->attributes->has(CrowdSSOAuthenticationListener::CANCEL_SSO_COOKIE_ATTR_NAME)) {
			$response->headers->setCookie($request->attributes->get(CrowdSSOAuthenticationListener::CANCEL_SSO_COOKIE_ATTR_NAME));
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents() {
		return array(KernelEvents::RESPONSE => 'onKernelResponse');
	}
}
