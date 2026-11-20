<?php

declare(strict_types=1);

namespace YoungDogs\AiSeo\Configuration;

final readonly class ManagedField
{
    public function __construct(
        public string $name,
        public string $label,
        public int $maxLength,
        public bool $multiline,
        public string $instruction,
    ) {}
}
