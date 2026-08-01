<?php

declare(strict_types=1);

namespace Pam\Native\Firebase;

use InvalidArgumentException;

final readonly class FirebaseAppOptions
{
    public function __construct(
        public string $applicationId,
        public string $apiKey,
        public ?string $projectId = null,
        public ?string $messagingSenderId = null,
        public ?string $storageBucket = null,
        public ?string $databaseUrl = null,
        public ?string $trackingId = null,
    ) {
        if ($applicationId === '' || $apiKey === '') {
            throw new InvalidArgumentException('Firebase applicationId and apiKey are required.');
        }
    }

    /** @return array<string, string> */
    public function payload(string $name): array
    {
        return array_filter([
            'name' => $name,
            'applicationId' => $this->applicationId,
            'apiKey' => $this->apiKey,
            'projectId' => $this->projectId,
            'senderId' => $this->messagingSenderId,
            'storageBucket' => $this->storageBucket,
            'databaseUrl' => $this->databaseUrl,
            'trackingId' => $this->trackingId,
        ], static fn (?string $value): bool => $value !== null);
    }
}
