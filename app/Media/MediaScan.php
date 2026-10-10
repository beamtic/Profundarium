<?php

namespace App\Media;

final readonly class MediaScan
{
    public function __construct(
        public string $root,
        public string $baseUrl,
        public int $notes,
        public int $images,
        public array $unused,
    ) {}

    public function bytes(): int
    {
        return array_sum(array_column($this->unused, 'size'));
    }

    public function url(string $path): string
    {
        return $this->baseUrl . '/uploads/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
