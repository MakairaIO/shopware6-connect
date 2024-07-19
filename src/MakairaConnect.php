<?php

declare(strict_types=1);

namespace Makaira\Connect;

use Makaira\Connect\DependencyInjection\Compiler\EntityRepositoryPass;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MakairaConnect extends Plugin
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
