<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\DependencyInjection;

use Haeretici\LegacyWorkflowBundle\Workflow\SupportedOperations;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ibexa_legacy_workflow');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->enumNode('storage_backend')->values(['ibexa_setting', 'yaml'])->defaultValue('ibexa_setting')->end()
                ->scalarNode('setting_group')->defaultValue('ibexa_legacy_workflow')->end()
                ->scalarNode('setting_identifier')->defaultValue('workflow_data')->end()
                ->scalarNode('storage_path')->defaultValue('%kernel.project_dir%/var/ibexa_legacy_workflow/data.yaml')->end()
                ->scalarNode('workflow_ini_path')->defaultNull()->end()
                ->arrayNode('available_operations')
                    ->scalarPrototype()->end()
                    ->defaultValue(SupportedOperations::OPERATIONS)
                ->end()
                ->arrayNode('available_event_types')
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        'event_ezapprove',
                        'event_ezwaituntildate',
                        'event_ezmultiplexer',
                        'event_ezfinishuserregister',
                    ])
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}