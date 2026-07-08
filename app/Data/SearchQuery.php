<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class SearchQuery
{
    public function __construct(
        public string $keyword,
        public CarbonImmutable $since,
        public ?CarbonImmutable $until = null,
    ) {}
}
