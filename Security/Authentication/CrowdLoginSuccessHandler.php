<?php
namespace Nordeus\CrowdUserBundle\Security\Authentication;

use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;

class CrowdLoginSuccessHandler implements AuthenticationSuccessHandlerInterface {

	protected $httpUtils;
	protected $options;
	protected $ssoCookieName;
	protected $ssoCookieDomain;

	protected $providerKey;

	/**
	 * Constructor.
	 *
	 * @param HttpUtils $httpUtils
	 * @param array     $options			Options for processing a successful authentication attempt.
	 * @param string	$ssoCookieName
	 * @param string	$ssoCookieDomain
	 */
	public function __construct(HttpUtils $httpUtils, array $options, $ssoCookieName, $ssoCookieDomain) {
		$this->httpUtils = $httpUtils;
		$this->options = $options;
		$this->ssoCookieName = $ssoCookieName;
		$this->ssoCookieDomain = $ssoCookieDomain;
	}

	/**
	 * {@inheritdoc}
	 */
	public function onAuthenticationSuccess(Request $request, TokenInterface $token) {
		$response = $this->httpUtils->createRedirectResponse($request, $this->determineTargetUrl($request));
		$cookie = new Cookie($this->ssoCookieName, $token->getUser()->getCrowdSessionToken(), 0, '/', '.' . $this->ssoCookieDomain);

		$response->headers->setCookie($cookie);

		return $response;
	}

	/**
	 * @return string
	 */
	public function getProviderKey() {
		return $this->providerKey;
	}

	/**
	 * @param string $providerKey
	 * @return CrowdLoginSuccessHandler
	 */
	public function setProviderKey($providerKey) {
		$this->providerKey = $providerKey;
		return $this;
	}

	/**
	 * Builds the target URL according to the defined options.
	 *
	 * @param Request $request
	 *
	 * @return string
	 */
	protected function determineTargetUrl(Request $request) {
		if ($this->options['always_use_default_target_path']) {
			return $this->options['default_target_path'];
		}

		if ($targetUrl = $request->get($this->options['target_path_parameter'], null)) {
			return $targetUrl;
		}

		if ($targetUrl = $request->getSession()->get("_security.$this->providerKey.target_path")) {
			$request->getSession()->remove("_security.$this->providerKey.target_path");
			return $targetUrl;
		}

		if ($this->options['use_referer'] && ($targetUrl = $request->headers->get('Referer')) && $targetUrl !== $this->httpUtils->generateUri($request, $this->options['login_path'])) {
			return $targetUrl;
		}

		return $this->options['default_target_path'];
	}
}
