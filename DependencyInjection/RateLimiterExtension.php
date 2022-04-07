<?php

namespace Symfony\Component\RateLimiter\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

final class RateLimiterExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (count($configs) === 1 && count($configs[0]) === 0) {
            return;
        }

        if ($this->isConfigEnabled($container, $config)) {
            if (!interface_exists(LimiterInterface::class)) {
                throw new LogicException('Rate limiter support cannot be enabled as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
            }

            $this->registerRateLimiterConfiguration($config, $container, $loader);
        }
    }

    private function registerRateLimiterConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
    {
        $loader->load('rate_limiter.php');

        foreach ($config['limiters'] as $name => $limiterConfig) {
            self::registerRateLimiter($container, $name, $limiterConfig);
        }
    }

    public static function registerRateLimiter(ContainerBuilder $container, string $name, array $limiterConfig)
    {
        $limiterConfig += ['lock_factory' => 'lock.factory', 'cache_pool' => 'cache.rate_limiter'];
        $limiter = $container->setDefinition($limiterId = 'limiter.'.$name, new ChildDefinition('limiter'));

        if (null !== $limiterConfig['lock_factory']) {
            $limiter->replaceArgument(2, new Reference($limiterConfig['lock_factory']));
        }
        unset($limiterConfig['lock_factory']);

        $storageId = $limiterConfig['storage_service'] ?? null;
        if (null === $storageId) {
            $container->register($storageId = 'limiter.storage.'.$name, CacheStorage::class)->addArgument(new Reference($limiterConfig['cache_pool']));
        }

        $limiter->replaceArgument(1, new Reference($storageId));
        unset($limiterConfig['storage_service']);
        unset($limiterConfig['cache_pool']);

        $limiterConfig['id'] = $name;
        $limiter->replaceArgument(0, $limiterConfig);

        $container->registerAliasForArgument($limiterId, RateLimiterFactory::class, $name.'.limiter');
    }
}
