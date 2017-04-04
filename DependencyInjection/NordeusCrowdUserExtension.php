<?php

namespace Nordeus\CrowdUserBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 */
class NordeusCrowdUserExtension extends Extension {
	
	/**
	 * {@inheritDoc}
	 */
	public function load(array $configs, ContainerBuilder $container) {
		$configuration = new Configuration();
		$config = $this->processConfiguration($configuration, $configs);

		$loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
		$loader->load('services.yml');
		
		// All parameters set under 'nordeus_crowd_user' section (defined in DependencyInjection/Configuration.php)
		// will be set as parameters, afterwards they will be visible in services.yml
		foreach ($config as $name => $value) {
			$container->setParameter('nordeus_crowd_user.' . $name, $value);
		}
	}
}
