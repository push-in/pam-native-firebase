<?php

declare(strict_types=1);

$vendor = dirname(__DIR__).'/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
}

$roots = [
    'Pam\\Native\\Firebase\\' => dirname(__DIR__).'/src/',
    'Pam\\Native\\FeatureFlags\\' => dirname(__DIR__, 2).'/pam-native-feature-flags/src/',
    'Pam\\Native\\Testing\\' => dirname(__DIR__, 2).'/pam-native-testing/src/',
    'Pam\\Native\\' => dirname(__DIR__, 2).'/../pam-native/packages/native/src/',
];
spl_autoload_register(static function (string $class) use ($roots): void {
    foreach ($roots as $prefix => $root) {
        if (str_starts_with($class, $prefix)) {
            $path = $root.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) { require $path; }
            return;
        }
    }
});

use Pam\Native\Firebase\Firebase;
use Pam\Native\Firebase\FirebaseAppOptions;
use Pam\Native\Firebase\FirebaseFeatureFlagLoader;
use Pam\Native\Firebase\FirebaseOperationState;
use Pam\Native\Firebase\RemoteFetchState;
use Pam\Native\Internal\Wire;
use Pam\Native\Testing\NativeTestHarness;

$tests = [];
$test = static function (string $name, Closure $callback) use (&$tests): void { $tests[$name] = $callback; };
$expect = static function (bool $condition, string $message = 'Expectation failed'): void {
    if (!$condition) { throw new RuntimeException($message); }
};

$test('configures named apps with bounded typed options', static function () use ($expect): void {
    $fake = NativeTestHarness::install();
    $fake->succeed('firebase', 'configure', ['name' => 'secondary']);
    $state = null;
    (new Firebase())->configure(new FirebaseAppOptions(
        '1:123:android:abc',
        'api-key',
        projectId: 'pam-production',
        messagingSenderId: '123',
    ), static function (FirebaseOperationState $value) use (&$state): void { $state = $value; }, 'secondary');
    $expect($state === FirebaseOperationState::Succeeded);
    $payload = Wire::decodeMap($fake->lastCall()?->payload ?? '');
    $expect($payload['name'] === 'secondary' && $payload['projectId'] === 'pam-production');
    $fake->assertSatisfied();
    NativeTestHarness::uninstall();
});

$test('encodes Analytics events without JSON', static function () use ($expect): void {
    $fake = NativeTestHarness::install();
    $fake->succeed('firebase', 'analyticsLog');
    (new Firebase())->logEvent('checkout_completed', [
        'order_id' => 'order-1',
        'amount' => 42.5,
        'first_purchase' => true,
    ], static fn () => null);
    $payload = Wire::decodeMap($fake->lastCall()?->payload ?? '');
    $expect($payload == [
        'event' => 'checkout_completed',
        'param_order_id' => 'order-1',
        'param_amount' => 42.5,
        'param_first_purchase' => true,
    ]);
    $fake->assertSatisfied();
    NativeTestHarness::uninstall();
});

$test('normalizes Remote Config fetch states', static function () use ($expect): void {
    $fake = NativeTestHarness::install();
    $fake->succeed('firebase', 'remoteFetch', ['state' => 3, 'changed' => false, 'message' => 'throttled']);
    $fetch = null;
    (new Firebase())->fetchRemoteConfig(static function ($value) use (&$fetch): void { $fetch = $value; }, 60);
    $expect($fetch?->state === RemoteFetchState::Throttled);
    $expect($fetch?->changed === false && $fetch?->message === 'throttled');
    $fake->assertSatisfied();
    NativeTestHarness::uninstall();
});

$test('loads provider-neutral feature flags from Remote Config', static function () use ($expect): void {
    $document = json_encode([
        'version' => 1,
        'flags' => [[
            'key' => 'firebase.flag',
            'default' => ['kind' => 1, 'value' => true],
        ]],
    ], JSON_THROW_ON_ERROR);
    $fake = NativeTestHarness::install();
    $fake->succeed('firebase', 'remoteValues', ['json' => json_encode(['pam_flags' => $document], JSON_THROW_ON_ERROR)]);
    $provider = null;
    $error = null;
    (new FirebaseFeatureFlagLoader(new Firebase()))->load(
        'pam_flags',
        static function ($value, $message) use (&$provider, &$error): void {
            $provider = $value;
            $error = $message;
        },
    );
    $expect($error === null && $provider?->definition('firebase.flag') !== null);
    $fake->assertSatisfied();
    NativeTestHarness::uninstall();
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $throwable) {
        ++$failures;
        NativeTestHarness::uninstall();
        fwrite(STDERR, "FAIL {$name}: {$throwable->getMessage()}\n");
    }
}
fwrite(STDOUT, sprintf("%d tests, %d failures\n", count($tests), $failures));
exit($failures === 0 ? 0 : 1);
