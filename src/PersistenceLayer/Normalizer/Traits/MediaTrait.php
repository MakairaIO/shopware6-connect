<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\Normalizer\Traits;

use Shopware\Core\Content\Media\MediaEntity;

trait MediaTrait
{
    private function processMedia(?MediaEntity $media): ?array
    {
        if (null === $media) {
            return null;
        }

        $thumbnails = [];
        foreach ($media->getThumbnails() as $thumbnail) {
            $thumbnails[$thumbnail->getWidth() . 'x' . $thumbnail->getHeight()] = $thumbnail->getUrl();
        }

        return [
            'original' => $media->getUrl(),
            'thumbnails' => $thumbnails,
        ];
    }
}
