<?php

declare(strict_types=1);

namespace Makaira\Connect\Events;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Possibility to change the query before sending it to Makaira.
 */
class ModifierQueryRequestEvent extends Event
{
    public const NAME_SEARCH = 'makaira.request.modifier.query.search';

    public const NAME_AUTOSUGGESTER = 'makaira.request.modifier.query.autosuggester';

    private readonly \ArrayObject $query;

    public function __construct(
        array $query,
    ) {
        $this->query = new \ArrayObject($query);
    }

    public function getQuery(): \ArrayObject
    {
        return $this->query;
    }
}
