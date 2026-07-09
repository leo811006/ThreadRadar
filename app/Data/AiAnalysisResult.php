<?php

namespace App\Data;

final readonly class AiAnalysisResult
{
    /**
     * @param  string[]  $tags
     */
    public function __construct(
        public string $summary,
        public string $sentiment,
        public array $tags,
    ) {}
}
