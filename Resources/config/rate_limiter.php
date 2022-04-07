<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\RateLimiter\RateLimiterFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('cache.rate_limiter')
        ->parent('cache.app')
        ->tag('cache.pool')
        ->set('limiter', RateLimiterFactory::class)
        ->abstract()
        ->args([
            'config',
            'storage',
            null,
        ])
    ;
};
