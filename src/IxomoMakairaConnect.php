<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect;

use Ixomo\MakairaConnect\DependencyInjection\Compiler\EntityRepositoryPass;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class IxomoMakairaConnect extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new EntityRepositoryPass());
    }
}
