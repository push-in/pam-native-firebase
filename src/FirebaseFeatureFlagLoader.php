<?php

declare(strict_types=1);

namespace Pam\Native\Firebase;

use Closure;
use InvalidArgumentException;
use Pam\Native\FeatureFlags\JsonFlagProvider;

final readonly class FirebaseFeatureFlagLoader
{
    public function __construct(private Firebase $firebase) {}

    /** @param Closure(?JsonFlagProvider, ?string): void $complete */
    public function load(string $remoteKey, Closure $complete, string $app = '[DEFAULT]'): int
    {
        return $this->firebase->remoteValues(static function (array $values) use ($remoteKey, $complete): void {
            $document = $values[$remoteKey] ?? null;
            if (!is_string($document)) {
                $complete(null, "Remote Config key {$remoteKey} is missing.");
                return;
            }
            try {
                $complete(JsonFlagProvider::fromJson($document), null);
            } catch (InvalidArgumentException $exception) {
                $complete(null, $exception->getMessage());
            }
        }, $app);
    }
}
