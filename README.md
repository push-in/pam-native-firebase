<!-- pam:product-page:start -->
<div align="center">

# PAM Native Firebase

**The Firebase services mobile teams need, behind PAM-native contracts.**

Connect Analytics, Crashlytics, Remote Config, and Messaging without coupling application code directly to vendor SDK objects.

[![Latest version](https://img.shields.io/packagist/v/pushinbr/pam-native-firebase?style=flat-square&label=stable)](https://packagist.org/packages/pushinbr/pam-native-firebase)
[![CI](https://img.shields.io/github/actions/workflow/status/push-in/pam-native-firebase/ci.yml?branch=main&style=flat-square&label=CI)](https://github.com/push-in/pam-native-firebase/actions)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?style=flat-square&logo=php&logoColor=white)
![Android](https://img.shields.io/badge/Android-API%2026%2B-3DDC84?style=flat-square&logo=android&logoColor=white)
![iOS](https://img.shields.io/badge/iOS-15%2B-000000?style=flat-square&logo=apple&logoColor=white)

**[Documentation](https://push-in.github.io/pam-docs/native/overview/) · [Quick start](#quick-start) · [What you can build](#what-you-can-build) · [PAM ecosystem](https://push-in.github.io/pam-docs/ecosystem/) · [Issues](https://github.com/push-in/pam-native-firebase/issues)**

</div>

---

## Why PAM Native Firebase

Connect Analytics, Crashlytics, Remote Config, and Messaging without coupling application code directly to vendor SDK objects. The public API is strictly typed for PHP 8.5; expensive or frame-sensitive work stays in Rust or the platform SDK instead of crossing the application boundary every frame.

| | |
| --- | --- |
| **Best for** | A focused capability you can add to any PAM Native application |
| **Native path** | Firebase Android SDK · Firebase Apple SDK |
| **Application model** | Composer package + generated native integration |
| **Design rule** | Independent module; no feed, vertical, or application template bundled |

## What you can build

- Product analytics and conversion events
- Crash diagnostics with application context
- Push messaging and remotely controlled behavior

## Quick start

Already have a PAM Native project? Add only this capability:

```bash
pam composer require pushinbr/pam-native-firebase
pam doctor --fix
```

New to PAM? Follow the **[five-minute PAM Native setup](https://push-in.github.io/pam-docs/native/overview/)** once, then return here. Your application stays a normal Composer project with a committed lockfile.
<!-- pam:product-page:end -->

## See it in action

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
| `Firebase::remoteValue()` | Read one provider-neutral Remote Config document. |

Firebase deliberately does not depend on the feature-flags, sync, HTTP, or any
other PAM Native plugin. Compose capabilities in application code: pass the
string returned by `remoteValue()` to whichever independent consumer you chose.

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

This package targets PAM Native `0.8.x`, Android API 26+, and iOS 15+ unless a platform-specific section above states a stricter requirement. Platform SDKs, credentials, entitlements, physical hardware, and store configuration remain application responsibilities.

- [PAM documentation](https://push-in.github.io/pam-docs/introduction/)
- [PAM Native overview](https://push-in.github.io/pam-docs/native/overview/)
- [Plugin and native capability model](https://push-in.github.io/pam-docs/native/plugins/)
- [Report an issue](https://github.com/push-in/pam-native-firebase/issues)

Security vulnerabilities should be reported through the repository security policy or GitHub private vulnerability reporting, not a public issue.
