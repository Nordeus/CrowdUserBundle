<?php

namespace Nordeus\CrowdUserBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from app/config files
 */
class Configuration implements ConfigurationInterface {
	/**
	 * {@inheritDoc}
	 */
	public function getConfigTreeBuilder() {
		// All parameters that user can set are under nordeus_crowd_user section in user's config file (app/config/config.yml)
		$treeBuilder = new TreeBuilder();
		$rootNode = $treeBuilder->root('nordeus_crowd_user');

		$rootNode
			->children()
				->scalarNode('crowd_application_name')
					->isRequired()
					->cannotBeEmpty()
				->end()
				->scalarNode('crowd_service_url')
					->isRequired()
					->cannotBeEmpty()
				->end()
				->scalarNode('sso_cookie_domain')
					->isRequired()
					->cannotBeEmpty()
				->end()
				->scalarNode('crowd_application_password')
					->isRequired()
					->cannotBeEmpty()
				->end()
				->scalarNode('remember_me_signature')
					->isRequired()
					->cannotBeEmpty()
				->end()
				->arrayNode('roles_to_groups')
					->isRequired()
					->cannotBeEmpty()
					->prototype('array')
						->prototype('scalar')
						->end()
					->end()
				->end()
				->scalarNode('user_class')
					->defaultValue('Nordeus\CrowdUserBundle\Security\User\CrowdUser')
				->end()
				->scalarNode('sso_cookie_name')
					->defaultValue('crowd.token_key')
				->end()
				->scalarNode('service_uri')
					->defaultValue('/crowd/rest/usermanagement/1/')
				->end()
				->integerNode('curl_timeout')
					->defaultValue(10)
					->min(1)
				->end()
				->integerNode('connection_retries')
					->defaultValue(2)
					->min(0)
				->end()
				->integerNode('user_refresh_time')
					->defaultValue(600)		// 10min = 600s
				->end()
			->end()
		;
		
		return $treeBuilder;
	}
}
