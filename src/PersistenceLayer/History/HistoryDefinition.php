<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\History;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

final class HistoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ixomo_makaira_connect_history';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return HistoryCollection::class;
    }

    public function getEntityClass(): string
    {
        return HistoryEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('entity_name', 'entityName'))->addFlags(new ApiAware(), new Required()),
            (new IdField('entity_id', 'entityId'))->addFlags(new ApiAware(), new Required()),
            (new JsonField('data', 'data'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('sent_at', 'sentAt'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
