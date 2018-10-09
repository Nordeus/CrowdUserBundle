<?php

namespace Nordeus\CrowdUserBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class SecurityController extends Controller {

	/**
	 * @Route("/login", name="nordeus_crowd_user_login")
	 * @Template()
	 * @param Request $request
	 * @return array
	 */
	public function loginAction(Request $request) {
		$session = $request->getSession();
		$error = '';

		if ($request->attributes->has(Security::AUTHENTICATION_ERROR)) {
			$error = $request->attributes->get(Security::AUTHENTICATION_ERROR);
		} elseif ($session && $session->has(Security::AUTHENTICATION_ERROR)) {
			$error = $session->get(Security::AUTHENTICATION_ERROR);
			$session->remove(Security::AUTHENTICATION_ERROR);
		}

		if ($error) {
			$error = $error->getMessage();
		}

		$lastUsername = $session ? $session->get(Security::LAST_USERNAME) : '';
		$csrfToken = null;
		if ($this->has('security.csrf.token_manager')) {
			$csrfToken = $this->get('security.csrf.token_manager')->getToken('authenticate')->getValue();
		}

		return array(
			'last_username' => $lastUsername,
			'error' => $error,
			'csrf_token' => $csrfToken,
		);
	}

	/**
	 * @Route("/check", name="nordeus_crowd_user_login_check")
	 */
	public function checkAction() {
		throw new \RuntimeException('You must configure the check path to be handled by the firewall using form_login in your security firewall configuration.');
	}

	/**
	 * @Route("/logout", name="nordeus_crowd_user_logout")
	 */
	public function logoutAction() {
		throw new \RuntimeException('You must activate the logout in your security firewall configuration.');
	}
}
