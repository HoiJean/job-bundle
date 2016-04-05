<?php
/*
* This file is part of the job-bundle package.
*
* (c) Hannes Schulz <hannes.schulz@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\JobBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Hannes Schulz <hannes.schulz@aboutcoders.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('abc_job');

        $supportedDrivers = array('orm', 'custom');
        $supportedQueueEngines = array('sonata', 'custom');
        $supportedLoggingHandlers = array('file', 'orm', 'custom');

        $rootNode
            ->children()
                ->scalarNode('db_driver')
                    ->validate()
                        ->ifNotInArray($supportedDrivers)
                        ->thenInvalid('The driver %s is not supported. Please choose one of ' . json_encode($supportedDrivers))
                    ->end()
                    ->cannotBeOverwritten()
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('register_default_jobs')
                    ->defaultTrue()
                ->end()
                ->scalarNode('model_manager_name')
                    ->defaultNull()
                ->end()
                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('handler')
                            ->defaultValue('file')
                            ->validate()
                                ->ifNotInArray($supportedLoggingHandlers)
                                ->thenInvalid('The handler %s is not supported. Please choose one of ' . json_encode($supportedLoggingHandlers))
                            ->end()
                            ->cannotBeOverwritten()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('directory')->defaultValue('%kernel.logs_dir%')->end()
                        ->scalarNode('default_level')->defaultValue('info')->end()
                        ->scalarNode('formatter')->end()
                        ->arrayNode('processor')
                            ->canBeUnset()
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('custom_level')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        $this->addServiceSection($rootNode);

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $node
     */
    private function addServiceSection(ArrayNodeDefinition $node)
    {
        $node
            ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('service')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('manager')->defaultValue('abc.job.manager.default')->end()
                            ->scalarNode('job_manager')->defaultValue('abc.job.job_manager.default')->end()
                            ->scalarNode('agent_manager')->defaultValue('abc.job.agent_manager.default')->end()
                            ->scalarNode('queue_engine')->defaultValue('abc.job.queue_engine.default')->end()
                            ->scalarNode('schedule_manager')->defaultValue('abc.job.schedule_manager.default')->end()
                            ->scalarNode('schedule_iterator')->defaultValue('abc.job.schedule_iterator.default')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}