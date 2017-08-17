<?php

namespace Nordeus\CrowdUserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Nordeus\CrowdUserBundle\DependencyInjection\Security\Factory\CrowdSSOFactory;
use Nordeus\CrowdUserBundle\DependencyInjection\Security\Factory\CrowdLoginFactory;
use Nordeus\CrowdUserBundle\DependencyInjection\Security\Factory\CrowdRememberMeFactory;


class NordeusCrowdUserBundle extends Bundle {

	public function build(ContainerBuilder $container) {
		parent::build($container);

		$extension = $container->getExtension('security');
		$extension->addSecurityListenerFactory(new CrowdLoginFactory());
		$extension->addSecurityListenerFactory(new CrowdSSOFactory());
		$extension->addSecurityListenerFactory(new CrowdRememberMeFactory());
	}
}
