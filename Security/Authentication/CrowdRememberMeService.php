<?php

namespace Nordeus\CrowdUserBundle\Security\Authentication;

use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Psr\Log\LoggerInterface;

class CrowdRememberMeService implements RememberMeServicesInterface, LogoutHandlerInterface {

	const COOKIE_DELIMITER = ':';

	private $rememberMeSignature;
	protected $userProvider;
	protected $options;
	protected $logger;

	/**
	 * Constructor
	 * 
	 * @param array $userProvider
	 * @param array $options
	 * @param string $rememberMeSignature
	 * @param LoggerInterface $logger
	 */
	public function __construct($userProvider, array $options = array(), $rememberMeSignature, LoggerInterface $logger = null) {
		$this->userProvider = $userProvider;
		$this->options = $options;
		$this->rememberMeSignature = $rememberMeSignature;
		$this->logger = $logger;
	}

	/**
	 * Implementation for RememberMeServicesInterface. This is called when an
	 * authentication is successful.
	 *
	 * @param Request        $request
	 * @param Response       $response
	 * @param TokenInterface $token    The token that resulted in a successful authentication
	 */
	public function loginSuccess(Request $request, Response $response, TokenInterface $token) {
		// Make sure any old remember-me cookies are cancelled
		$this->cancelCookie($request);

		if (!$this->isRememberMeRequested($request)) {
			return;
		}

		// Remove attribute from request that sets a NULL cookie. It was set by $this->cancelCookie()
		$request->attributes->remove(self::COOKIE_ATTR_NAME);

		$username = $token->getUser()->getUsername();
		$expires = time() + $this->options['lifetime'];
		$value = $this->generateCookieValue($username, $expires);

		$response->headers->setCookie(
			new Cookie(
					$this->options['name'],
					$value,
					$expires,
					$this->options['path'],
					$this->options['domain'],
					$this->options['secure'],
					$this->options['httponly']
			)
		);
	}

	/**
	 * Implementation for RememberMeServicesInterface. Deletes the cookie when
	 * an attempted authentication fails.
	 *
	 * @param Request $request
	 */
	public function loginFail(Request $request) {
		$this->cancelCookie($request);
	}

	/**
	 * Implementation for LogoutHandlerInterface. Deletes the cookie.
	 *
	 * @param Request        $request
	 * @param Response       $response
	 * @param TokenInterface $token
	 */
	public function logout(Request $request, Response $response, TokenInterface $token) {
		$this->cancelCookie($request);
	}

	/**
	 * Implementation of RememberMeServicesInterface. Detects whether a remember-me
	 * cookie was set, decodes it, checks if it is valid, and returns CrowdAuthenticationToken filled with username.
	 *
	 * @param Request $request
	 * @return TokenInterface|null
	 */
	public function autoLogin(Request $request) {
		$cookie = $request->cookies->get($this->options['name']);
		if (!$cookie) {
			return null;
		}

		try {
			$cookieParts = $this->decodeCookie($cookie);

			if (count($cookieParts) !== 3) {
				throw new AuthenticationException('The cookie is invalid.');
			}

			list($username, $expires, $hash) = $cookieParts;
			$username = base64_decode($username, true);

			if (!$username) {
				throw new AuthenticationException($username . ' contains a character from outside the base64 alphabet.');
			}

			if (time() > $expires) {
				throw new AuthenticationException('Remeber me credentials has expired');
			}

			$value = $this->generateCookieHash($username, $expires);

			if ($hash != $value) {
				throw new AuthenticationException('Remeber me cookie has wrong hash value');
			}

			return new CrowdAuthenticationToken($username);

		} catch (AuthenticationException $e) {
			if ($this->logger) {
				$this->logger->warning($e->getMessage());
			}
		}

		$this->cancelCookie($request);
		return null;
	}

	/**
	 * Generates the cookie value.
	 *
	 * @param string  $username The username
	 * @param int     $expires  The Unix timestamp when the cookie expires
	 * @throws \RuntimeException if username contains invalid chars
	 * @return string
	 */
	protected function generateCookieValue($username, $expires) {
		return $this->encodeCookie(array(
			base64_encode($username),
			$expires,
			$this->generateCookieHash($username, $expires)
		));
	}

	/**
	 * Generates a hash for the cookie to ensure it is not being tempered with
	 *
	 * @param string  $username		The username
	 * @param int     $expires		The Unix timestamp when the cookie expires
	 * @return string
	 */
	protected function generateCookieHash($username, $expires) {
		return hash_hmac('sha256', $username.$expires, $this->rememberMeSignature);
	}

	/**
	 * Decodes the raw cookie value
	 *
	 * @param string $rawCookie
	 * @return array
	 */
	protected function decodeCookie($rawCookie) {
		return explode(self::COOKIE_DELIMITER, base64_decode($rawCookie));
	}

	/**
	 * Encodes the cookie parts
	 *
	 * @param array $cookieParts
	 * @return string
	 */
	protected function encodeCookie(array $cookieParts) {
		return base64_encode(implode(self::COOKIE_DELIMITER, $cookieParts));
	}

	/**
	 * Deletes the remember-me cookie
	 *
	 * @param Request $request
	 */
	protected function cancelCookie(Request $request) {
		$request->attributes->set(self::COOKIE_ATTR_NAME, new Cookie($this->options['name'], null, 1, $this->options['path'], $this->options['domain']));
	}

	/**
	 * Checks whether remember-me capabilities were requested
	 *
	 * @param Request $request
	 * @return bool
	 */
	protected function isRememberMeRequested(Request $request) {
		if (true === $this->options['always_remember_me']) {
			return true;
		}

		$parameter = $request->get($this->options['remember_me_parameter'], null);

		return $parameter === 'true' || $parameter === 'on' || $parameter === '1' || $parameter === 'yes';
	}
}
