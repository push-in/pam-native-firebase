# PAM Native Firebase

Official Firebase integration for PAM Native: multi-app configuration,
Analytics, Remote Config, Messaging token lifecycle, Installations and
Crashlytics.

```bash
pam add firebase
pam doctor
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


## What installation does

`pam add firebase` resolves the official compatible package, performs a non-mutating Composer preflight, updates the normal `composer.json` and `composer.lock`, refreshes generated native integration when required, and leaves the project ready for `pam doctor` validation.

Use `pam packages` to inspect availability and `pam remove firebase` to uninstall the capability safely. Direct Composer commands are an advanced interoperability path; PAM is the supported application workflow.

## API guide

| API | Responsibility |
| --- | --- |
| `Firebase` | Configure apps and access Analytics, Remote Config, Messaging, Installations, and Crashlytics. |
| `FirebaseAppOptions` | Configure named Firebase applications. |
| `RemoteFetchResult` | Typed Remote Config fetch/activation result. |
| `FirebaseFeatureFlagLoader` | Load Remote Config values into PAM feature flags. |

All coded states, kinds, and variants are sequential integer-backed enums. Use enum cases in application code; do not depend on raw wire numbers.

## Production checklist

- Use platform configuration files for the default app and runtime options only for named apps.
- Keep notification routing in PAM Native and Firebase token ownership in this package.
- Never place service-account or server credentials in the mobile bundle.
- Run `pam doctor`, `pam test`, and a signed release build on every supported platform.
- Exercise denial, cancellation, backgrounding, process restart, and offline behavior before release.

## Troubleshooting

- **Default app is missing:** confirm `.pam/google-services.json` and the Apple configuration are present.
- **Events do not appear immediately:** Firebase Analytics delivery is intentionally buffered.
- **Remote Config is throttled:** respect `RemoteFetchResult` and the minimum interval.
- **Native integration is stale:** run `pam doctor --fix`, rebuild the native host, and inspect the first reported diagnostic.

## Compatibility and support

This package targets PAM Native `0.6.x`, Android API 26+, and iOS 15+ unless a platform-specific section above states a stricter requirement. Platform SDKs, credentials, entitlements, physical hardware, and store configuration remain application responsibilities.

- [PAM documentation](https://push-in.github.io/pam-docs/introduction/)
- [PAM Native overview](https://push-in.github.io/pam-docs/native/overview/)
- [Plugin and native capability model](https://push-in.github.io/pam-docs/native/plugins/)
- [Report an issue](https://github.com/push-in/pam-native-firebase/issues)

Security vulnerabilities should be reported through the repository security policy or GitHub private vulnerability reporting, not a public issue.
