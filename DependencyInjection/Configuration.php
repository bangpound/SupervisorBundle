<?php

namespace YZ\SupervisorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('yz_supervisor');

        $rootNode
            ->children()
                ->scalarNode('default_environment')->end()
                ->arrayNode('servers')
                ->requiresAtLeastOneElement()
                ->useAttributeAsKey('name')
                ->prototype('array')
                        ->isRequired()
                        ->requiresAtLeastOneElement()
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('host')->end()
                                ->scalarNode('username')->end()
                                ->scalarNode('password')->end()
                                ->scalarNode('port')->defaultValue('9001')->end()
                            ->end()
                        ->end()
                    ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
