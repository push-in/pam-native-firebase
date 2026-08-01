# PAM Native Firebase

Official Firebase integration for PAM Native: multi-app configuration,
Analytics, Remote Config, Messaging token lifecycle, Installations and
Crashlytics.

```bash
composer require pushinbr/pam-native-firebase
pam mobile prepare
```

```php
$firebase = new Firebase();

$firebase->logEvent('checkout_completed', [
    'order_id' => $order->id,
    'amount' => $order->total,
], static function (FirebaseOperationState $state, ?string $error): void {
    // Handle native acceptance or failure.
});

$firebase->fetchRemoteConfig(static function (RemoteFetchResult $result): void {
    // Activated, fetched, throttled, or failed.
});
```

Android reads `.pam/google-services.json` through the PAM host and uses pinned
main Firebase modules, not the discontinued KTX artifacts. Apple uses the
official Firebase Swift Package products. Named apps can also be configured at
runtime with `FirebaseAppOptions`.

Incoming notification presentation and routing remain owned by PAM Native's
notification capability; this package owns Firebase token and service APIs.
