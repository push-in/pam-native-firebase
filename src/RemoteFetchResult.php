<?php

declare(strict_types=1);

namespace Pam\Native\Firebase;

final readonly class RemoteFetchResult
{
    public function __construct(
        public RemoteFetchState $state,
        public bool $changed,
        public ?string $message = null,
    ) {}
}
