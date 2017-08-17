<?php

namespace Nordeus\CrowdUserBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;

/**
 * Creates CrowdRememberMeFactory
 * Defines CrowdRememberMeAuthenticationListener.
 * Authentication Provider is CrowdAuthenticationProvider, which is common auth. provider for all Crowd's Listeneres.
 * Defines CrowdRememberMeService, attaches the service to CrowdLoginAuthenticationListener. Purpose of it, is that on success login,
 * LoginListener invokes loginSuccess method in CrowdRememberMeService where RemeberMe cookie is set. 
 * 
 * @see README.md file,  Explanation section.
 */
class CrowdRememberMeFactory implements SecurityFactoryInterface {

	protected $options = array(
		'name' => 'REMEMBERME',
		'lifetime' => 1209600,		// 2 weeks = 2*7*24*60*60 = 1209600
		'path' => '/',
		'domain' => null,
		'secure' => false,
		'httponly' => true,
		'always_remember_me' => false,
		'remember_me_parameter' => '_remember_me',
	);

	public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint) {

		$providerId = 'security.authentication.provider.crowd.' . $id;
		$provider = $container->setDefinition($providerId, new DefinitionDecorator('crowd.security.authentication.provider'));
		$provider->replaceArgument(0, new Reference($userProvider));

		$rememberMeServicesId = 'security.authentication.rememberme.services.crowd.' . $id;

		// add remeberMeService, which is also logout handler to logout listener
		if ($container->hasDefinition('security.logout_listener.' . $id)) {
			$logoutListener = $container->getDefinition('security.logout_listener.' . $id);
			$logoutListener->addMethodCall('addHandler', array(new Reference($rememberMeServicesId)));
		}

		$rememberMeServices = $container->setDefinition($rememberMeServicesId, new DefinitionDecorator('crowd.security.authentication.rememberme.services'));
		$rememberMeServices->replaceArgument(0, new Reference($userProvider));
		$rememberMeServices->replaceArgument(1, array_merge($this->options, $config));

		// remember-me listener
		$listenerId = 'security.authentication.listener.remember_me.crowd.' . $id;
		$listener = $container->setDefinition($listenerId, new DefinitionDecorator('crowd.security.authentication.listener.remember_me'));
		$listener->replaceArgument(1, new Reference($rememberMeServicesId));

		// attach rememberMeService to login listener
		$loginListenerId = 'crowd.security.authentication.listener.login.' . $id;
		$loginListener = $container->getDefinition($loginListenerId);
		$loginListener->addMethodCall('setRememberMeServices', array(new Reference($rememberMeServicesId)));

		return array($providerId, $listenerId, $defaultEntryPoint);
	}

	public function getPosition() {
		return 'remember_me';
	}

	public function getKey() {
		return 'crowd-remember-me';
	}

	public function addConfiguration(NodeDefinition $node) {
		$node
			->children()
			->end();
	}
}
