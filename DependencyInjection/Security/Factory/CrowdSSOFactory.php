<?php

namespace Nordeus\CrowdUserBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;

/**
 * Creates CrowdSSOFactory.
 * Defines CrowdSSOAuthenticationListener.
 * Authentication Provider is CrowdAuthenticationProvider, which is common auth. provider for all Crowd's Listeneres.
 * It also attaches Logout Handler to default Logout Listener, which should be called on logout action.
 * 
 * @see README.md file,  Explanation section.
 */
class CrowdSSOFactory implements SecurityFactoryInterface {

	public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint) {
		$providerId = 'security.authentication.provider.crowd.' . $id;
		$provider = $container->setDefinition($providerId, new ChildDefinition('crowd.security.authentication.provider'));
		$provider->replaceArgument(0, new Reference($userProvider));

		$listenerId = 'security.authentication.listener.crowd.' . $id;
		$container->setDefinition($listenerId, new ChildDefinition('crowd.security.authentication.listener.sso'));

		$logoutHandlerId = 'security.logout.handler.sso.crowd.' . $id;
		$container->setDefinition($logoutHandlerId, new ChildDefinition('crowd.security.logout.handler.sso'));

		// attach logout handler to logout listener
		if ($container->hasDefinition('security.logout_listener.' . $id)) {
			$logoutListener = $container->getDefinition('security.logout_listener.' . $id);
			$logoutListener->addMethodCall('addHandler', array(new Reference($logoutHandlerId)));
		}

		return array($providerId, $listenerId, $defaultEntryPoint);
	}

	public function addConfiguration(NodeDefinition $node) {
		$node
			->children()
			->end();
	}

	public function getPosition() {
		return 'pre_auth';
	}

	public function getKey() {
		return 'crowd_sso';
	}
}
