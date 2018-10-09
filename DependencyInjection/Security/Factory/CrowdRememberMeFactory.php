<?php

namespace Nordeus\CrowdUserBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;

/**
 * @deprecated
 */
class CrowdRememberMeFactory implements SecurityFactoryInterface {

	public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint) {

		trigger_error('Crowd remember-me firewall is deprecated.', E_USER_DEPRECATED);

		$providerId = 'security.authentication.provider.anonymous.' . $id;
		$listenerId = 'security.authentication.listener.anonymous.' . $id;

		return array($providerId, $listenerId, $defaultEntryPoint);
	}

	public function getPosition() {
		return 'pre_auth';
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
