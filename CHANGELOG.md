# Changelog

## 0.2.2 - 2026-10-28

- iOS links `FirebaseAnalyticsCore` instead of `FirebaseAnalytics`: no IDFA
  support (`GoogleAppMeasurementIdentitySupport`) or ad conversion SDK in the
  app by default. Every exposed API is unchanged. IDFA stays an explicit
  opt-in: add the `FirebaseAnalyticsIdentitySupport` product in the app.

## 0.1.0 - 2026-08-01

- Initial public release of the documented PAM Native package contract.
- Add bounded input validation, sequential integer protocol enums, automated
  package tests, and PHP 8.4/8.5 continuous integration.

