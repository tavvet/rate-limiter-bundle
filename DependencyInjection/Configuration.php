<?php

namespace Symfony\Component\RateLimiter\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('rate_limiter');
        $rootNode = $treeBuilder->getRootNode()->info('Rate limiter configuration');

        $this->addRateLimiterSection($rootNode);

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     * @return void
     */
    private function addRateLimiterSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->canBeEnabled()
            ->fixXmlConfig('limiter')
            ->beforeNormalization()
                ->ifTrue(function ($v) { return \is_array($v) && !isset($v['limiters']) && !isset($v['limiter']); })
                ->then(function (array $v) {
                    $newV = [
                        'enabled' => $v['enabled'] ?? true,
                    ];
                    unset($v['enabled']);

                    $newV['limiters'] = $v;

                    return $newV;
                })
            ->end()
            ->children()
                ->arrayNode('limiters')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('lock_factory')
                                ->info('The service ID of the lock factory used by this limiter (or null to disable locking)')
                                ->defaultValue('lock.factory')
                            ->end()
                            ->scalarNode('cache_pool')
                                ->info('The cache pool to use for storing the current limiter state')
                                ->defaultValue('cache.rate_limiter')
                            ->end()
                            ->scalarNode('storage_service')
                                ->info('The service ID of a custom storage implementation, this precedes any configured "cache_pool"')
                                ->defaultNull()
                            ->end()
                            ->enumNode('policy')
                                ->info('The algorithm to be used by this limiter')
                                ->isRequired()
                                ->values(['fixed_window', 'token_bucket', 'sliding_window', 'no_limit'])
                            ->end()
                            ->integerNode('limit')
                                ->info('The maximum allowed hits in a fixed interval or burst')
                                ->isRequired()
                            ->end()
                            ->scalarNode('interval')
                                ->info('Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')
                            ->end()
                            ->arrayNode('rate')
                                ->info('Configures the fill rate if "policy" is set to "token_bucket"')
                                ->children()
                                    ->scalarNode('interval')
                                        ->info('Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')
                                    ->end()
                                    ->integerNode('amount')->info('Amount of tokens to add each interval')->defaultValue(1)->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
