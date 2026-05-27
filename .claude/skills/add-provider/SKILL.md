---
name: add-provider
description: Use when the user asks to add a new exchange-rate provider (e.g. "add provider X", "integrate XYZ API", "поддержать новый источник курсов"). Walks through the full checklist — class, enum, services.yaml, unit + integration test, README — using the existing `AbstractApiProvider` and project conventions. PHP only; safe to invoke multiple times for separate providers.
when_to_use: Adding a new ExchangeRate source to ExRate. Triggers — "новый провайдер", "add provider", "интегрировать", "ещё один источник курсов".
argument-hint: "[provider-key] [base-currency]"
allowed-tools: Read, Glob, Grep, Edit, Write, Bash(make *)
model: inherit
---

# Add a new exchange-rate provider

Project conventions live in `AGENTS.md` and `.ai/CODESTYLE.md`. This skill stitches them into a step-by-step playbook so a new provider lands consistent with the existing 31.

## Inputs

- **provider-key** — short slug (lowercase, no spaces): e.g. `cbr`, `binance`, `nbg`. Used for class prefix, enum value, route param.
- **base-currency** — ISO code the provider publishes in (e.g. `USD`, `EUR`, `RUB`). Use constants from `\App\Util\Currencies`.
- Optional: whether the provider exposes OHLC/volume → implement `ProviderRateExtendInterface` (see `MoexProvider` as the only current example).

If the user did not specify, ASK for both before starting.

## Reference files (read before coding)

- Base class: `app/src/Provider/AbstractApiProvider.php`
- Simple example: `app/src/Provider/EcbProvider.php` (base EUR, daily XML)
- Extended example: `app/src/Provider/MoexProvider.php` (OHLC, `ProviderRateExtendInterface`)
- Contracts: `app/src/Contract/ProviderRateInterface.php`, `app/src/Contract/ProviderRateExtendInterface.php`
- Enum to register: `app/src/Enum/ProviderEnum.php`
- DI: `app/config/services.yaml` (`#[AsTagged]` autowiring — usually just attribute is enough)
- Test templates: `app/tests/Unit/Provider/`, `app/tests/Integration/Provider/`
- Currency constants: `app/src/Util/Currencies.php`, `app/src/Util/CryptoCurrencies.php`

## Steps

1. **Class** — `app/src/Provider/<Name>Provider.php`.
   - Extend `AbstractApiProvider`; `declare(strict_types=1)`.
   - Implement `getAvailableCurrencies(): array` returning `string[]` of ISO codes (use `Currencies::*` constants).
   - Implement `getRatesByDate(\DateTimeInterface $date): GetRatesResult` and `getRatesByRangeDate(...)` if API supports it; otherwise throw `NotAvailableMethod`.
   - Override `getMinDate()`, `getPeriodDays()`, `getRequestDelay()`, `isActive()` as needed.
   - Base currency — via `getBaseCurrency(): string` (constant from `Currencies`).
   - Logging via injected `LoggerInterface` (always log exceptions with severity-appropriate level).
   - For XML APIs use `xmlRequest()` from base class; for JSON use `jsonRequest()`.
   - Throw `BadDateException`, `FailedProviderException`, `LimitException`, `RetryByDateException` from `app/src/Exception/` — do not invent new ones unless justified.

2. **Enum** — add the case in `app/src/Enum/ProviderEnum.php` (key = provider slug). Order alphabetically if existing list does.

3. **Services / config** — usually no change needed (`AutowireLocator` picks it up). If the provider needs API keys, add them to `.env_dist` (placeholder only, no real secret) and document in README.

4. **Unit test** — `app/tests/Unit/Provider/<Name>ProviderTest.php`. Mock HTTP client, assert mapping from a sample API response → `GetRatesResult`. Cover: happy path, weekend/holiday (empty), bad date, limit exception.

5. **Integration test** — `app/tests/Integration/Provider/<Name>ProviderTest.php` following templates. Will run only via `make test-integration provider=<key>`.

6. **README** — append the provider to the bullet list under `## Supported Providers` and bump the count.

7. **Done check** (mandatory, in order):
   - `make cs-fix`
   - `make cs-check`
   - `make phpstan`
   - `make test-unit name=<Name>ProviderTest`
   - `make test-functional`
   - Optionally `make test-integration provider=<key>` (only if user asked).

8. **Session log** — append entry to `.ai/logs/YYYY-MM-DD.md` (prompt → plan → git-summary).

## Rules

- **No magic numbers/strings** — period days, min dates, API URLs go to typed `const string`/`const int` on the class or to `services.yaml` if env-dependent.
- **Named params** for any call with >2 args.
- **API URL** — store as class const, never inline.
- **Logging** — never `print_r`/`var_dump`; use the injected logger.
- **Do not edit** `app/vendor/`, `app/var/`, `app/composer.lock`.
- **Do not run** `make test-integration` unless user explicitly asks.
