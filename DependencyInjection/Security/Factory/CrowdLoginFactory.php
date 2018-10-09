<?php

namespace Nordeus\CrowdUserBundle\DependencyInjection\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Creates CrowdLoginFactory.
 * It extends AbstractFactory which defines all necessary method that should be override in order to create Login Factory.
 * Defines CrowdLoginAuthenticationListener.
 * Authentication Provider is CrowdAuthenticationProvider, which is common auth. provider for all Crowd's Listeneres.
 * Defines CrowdLoginSuccessHandler which should be invoked on success login, which should set SSO cookie.
 * 
 * @see README.md file,  Explanation section.
 */
class CrowdLoginFactory extends AbstractFactory {

	public function __construct() {
		$this->addOption('username_parameter', '_username');
		$this->addOption('password_parameter', '_password');
		$this->addOption('csrf_parameter', '_csrf_token');
		$this->addOption('csrf_token_id', 'authenticate');
		$this->addOption('post_only', true);
		$this->addOption('cookie_domain', null);
	}

	protected function createAuthProvider(ContainerBuilder $container, $id, $config, $userProviderId) {
		$providerId = 'security.authentication.provider.crowd.'.$id;
		$container
			->setDefinition($providerId, new ChildDefinition('crowd.security.authentication.provider'))
			->replaceArgument(0, new Reference($userProviderId));

		return $providerId;
	}

	/**
	 * @param ContainerBuilder $container
	 * @param $id
	 * @param $config
	 * @param $userProvider
	 * @return string
	 */
	protected function createListener($container, $id, $config, $userProvider) {
		$listenerId = parent::createListener($container, $id, $config, $userProvider);

		if (isset($config['csrf_token_generator'])) {
			$container
				->getDefinition($listenerId)
				->addArgument(new Reference($config['csrf_token_generator']));
		}

		return $listenerId;
	}

	/**
	 * @param ContainerBuilder $container
	 * @param $id
	 * @param $config
	 * @return string
	 */
	protected function createAuthenticationSuccessHandler($container, $id, $config) {
		$successHandlerId = $this->getSuccessHandlerId($id);
		$successHandler = $container->setDefinition($successHandlerId, new ChildDefinition('crowd.security.authentication.success_handler.login'));
		$successHandler->replaceArgument(1, array_intersect_key($config, $this->defaultSuccessHandlerOptions));
		$successHandler->addMethodCall('setProviderKey', array($id));

		return $successHandlerId;
	}

	protected function createEntryPoint($container, $id, $config, $defaultEntryPoint) {
		$entryPointId = 'security.authentication.form_entry_point.' . $id;
		$container
			->setDefinition($entryPointId, new ChildDefinition('security.authentication.form_entry_point'))
			->addArgument(new Reference('security.http_utils'))
			->addArgument($config['login_path'])
 			->addArgument($config['use_forward']);

		return $entryPointId;
	}

	public function addConfiguration(NodeDefinition $node) {
		parent::addConfiguration($node);

		$node
			->children()
				->scalarNode('csrf_token_generator')
					->cannotBeEmpty()
				->end()
			->end();
	}

	protected function getListenerId() {
		return 'crowd.security.authentication.listener.login';
	}

	public function getPosition() {
		return 'form';
	}

	public function getKey() {
		return 'crowd_form_login';
	}
}
