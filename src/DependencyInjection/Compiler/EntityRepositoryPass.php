<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\DependencyInjection\Compiler;

use Ixomo\MakairaConnect\PersistenceLayer\EntityRepository;
use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\NormalizerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class EntityRepositoryPass implements CompilerPassInterface
{
    private const NORMALIZER_TAG = 'ixomo.makaira_connect.persistence_layer.normalizer';

    public function process(ContainerBuilder $container): void
    {
        $repositories = [];
        $services = $container->findTaggedServiceIds(self::NORMALIZER_TAG);

        foreach (array_keys($services) as $serviceId) {
            /** @var class-string<NormalizerInterface> $class */
            $class = $container->getDefinition($serviceId)->getClass();

            if (!\in_array(NormalizerInterface::class, class_implements($class))) {
                throw new \LogicException('Classes tagged with ' . self::NORMALIZER_TAG . ' must implement ' . NormalizerInterface::class);
            }

            $repositoryId = 'sales_channel.' . $class::getSupportedEntity() . '.repository';
            if (!$container->has($repositoryId)) {
                $repositoryId = $class::getSupportedEntity() . '.repository';
            }

            if ($container->has($repositoryId)) {
                $repositories[$class::getSupportedEntity()] = $container->getDefinition($repositoryId);
            }
        }

        $container->getDefinition(EntityRepository::class)->setArgument('$repositories', $repositories);
    }
}
