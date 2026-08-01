<?php

declare(strict_types=1);

namespace Pam\Native\Firebase;

use Closure;
use InvalidArgumentException;
use JsonException;
use Pam\Native\Modules\NativeModuleResult;
use Pam\Native\Modules\NativeModules;

final class Firebase
{
    private const string MODULE = 'firebase';

    /** @param Closure(FirebaseOperationState, ?string): void $complete */
    public function configure(
        FirebaseAppOptions $options,
        Closure $complete,
        string $name = '[DEFAULT]',
    ): int {
        return $this->call('configure', $options->payload($name), $complete);
    }

    /** @param array<string, bool|int|float|string> $parameters @param Closure(FirebaseOperationState, ?string): void $complete */
    public function logEvent(string $name, array $parameters, Closure $complete): int
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/D', $name) !== 1 || count($parameters) > 25) {
            throw new InvalidArgumentException('Firebase event names and parameter count must satisfy Analytics limits.');
        }
        $payload = ['event' => $name];
        foreach ($parameters as $key => $value) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/D', $key) !== 1) {
                throw new InvalidArgumentException("Invalid Firebase Analytics parameter {$key}.");
            }
            $payload['param_'.$key] = $value;
        }
        return $this->call('analyticsLog', $payload, $complete);
    }

    /** @param Closure(FirebaseOperationState, ?string): void $complete */
    public function setUserId(?string $identifier, Closure $complete): int
    {
        return $this->call('analyticsUserId', ['userId' => $identifier ?? ''], $complete);
    }

    /** @param Closure(FirebaseOperationState, ?string): void $complete */
    public function setUserProperty(string $name, ?string $value, Closure $complete): int
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,23}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid Analytics user property name.');
        }
        return $this->call('analyticsUserProperty', ['name' => $name, 'value' => $value ?? ''], $complete);
    }

    /** @param Closure(RemoteFetchResult): void $complete */
    public function fetchRemoteConfig(Closure $complete, int $minimumIntervalSeconds = 3600, string $app = '[DEFAULT]'): int
    {
        if ($minimumIntervalSeconds < 0) {
            throw new InvalidArgumentException('Remote Config minimum interval cannot be negative.');
        }
        return NativeModules::call(self::MODULE, 'remoteFetch', [
            'app' => $app,
            'minimumInterval' => $minimumIntervalSeconds,
        ], static function (NativeModuleResult $result) use ($complete): void {
            if (!$result->succeeded()) {
                $complete(new RemoteFetchResult(RemoteFetchState::Failed, false, $result->message()));
                return;
            }
            $values = $result->values();
            $state = RemoteFetchState::tryFrom((int) ($values['state'] ?? RemoteFetchState::Failed->value))
                ?? RemoteFetchState::Failed;
            $complete(new RemoteFetchResult(
                $state,
                (bool) ($values['changed'] ?? false),
                isset($values['message']) && is_string($values['message']) ? $values['message'] : null,
            ));
        });
    }

    /** @param Closure(array<string, string>): void $complete */
    public function remoteValues(Closure $complete, string $app = '[DEFAULT]'): int
    {
        return NativeModules::call(self::MODULE, 'remoteValues', ['app' => $app], static function (NativeModuleResult $result) use ($complete): void {
            if (!$result->succeeded()) {
                $complete([]);
                return;
            }
            $values = $result->values();
            $json = $values['json'] ?? '{}';
            try {
                $decoded = is_string($json) ? json_decode($json, true, flags: JSON_THROW_ON_ERROR) : [];
            } catch (JsonException) {
                $decoded = [];
            }
            $complete(is_array($decoded) ? array_filter($decoded, 'is_string') : []);
        });
    }

    /** @param array<string, bool|int|float|string> $defaults @param Closure(FirebaseOperationState, ?string): void $complete */
    public function setRemoteDefaults(array $defaults, Closure $complete, string $app = '[DEFAULT]'): int
    {
        try {
            $json = json_encode($defaults, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Remote Config defaults cannot be encoded.', previous: $exception);
        }
        return $this->call('remoteDefaults', ['app' => $app, 'json' => $json], $complete);
    }

    /** @param Closure(?string): void $complete */
    public function messagingToken(Closure $complete): int
    {
        return $this->value('messagingToken', 'token', $complete);
    }

    /** @param Closure(FirebaseOperationState, ?string): void $complete */
    public function deleteMessagingToken(Closure $complete): int
    {
        return $this->call('messagingDeleteToken', [], $complete);
    }

    /** @param Closure(?string): void $complete */
    public function installationId(Closure $complete, string $app = '[DEFAULT]'): int
    {
        return $this->value('installationId', 'identifier', $complete, ['app' => $app]);
    }

    /** @param Closure(FirebaseOperationState, ?string): void $complete */
    public function crashLog(string $message, Closure $complete): int
    {
        return $this->call('crashLog', ['message' => substr($message, 0, 4096)], $complete);
    }

    /** @param Closure(FirebaseOperationState, ?string): void $complete */
    public function recordNonFatal(string $name, string $message, Closure $complete): int
    {
        return $this->call('crashRecord', [
            'name' => substr($name, 0, 128),
            'message' => substr($message, 0, 4096),
        ], $complete);
    }

    /** @param array<string, string|int|float|bool> $payload @param Closure(FirebaseOperationState, ?string): void $complete */
    private function call(string $method, array $payload, Closure $complete): int
    {
        return NativeModules::call(self::MODULE, $method, $payload, static function (NativeModuleResult $result) use ($complete): void {
            $complete(
                $result->succeeded() ? FirebaseOperationState::Succeeded : FirebaseOperationState::Failed,
                $result->succeeded() ? null : $result->message(),
            );
        });
    }

    /** @param Closure(?string): void $complete @param array<string, string|int|float|bool> $payload */
    private function value(string $method, string $key, Closure $complete, array $payload = []): int
    {
        return NativeModules::call(self::MODULE, $method, $payload, static function (NativeModuleResult $result) use ($complete, $key): void {
            if (!$result->succeeded()) {
                $complete(null);
                return;
            }
            $values = $result->values();
            $complete(isset($values[$key]) && is_string($values[$key]) ? $values[$key] : null);
        });
    }
}
