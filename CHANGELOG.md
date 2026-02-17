# Release version 0.3

- **Providers:**
    - Added new providers: Moex (with extended data), Binance (Crypto), Frankfurter, Fred, Banxico, Bank of Canada, and Bcb.
    - Implemented `ProviderRateExtendInterface` for providers with extra market data (OHLC, volume).
    - Added `MinDate` validation for providers to prevent invalid historical requests.
- **API & Controllers:**
    - Added `/api/v1/currencies` and `/api/crypto_currencies` endpoint to list available currencies and their metadata.
    - Enhanced `/api/v1/timeseries` with grouping by period (day, week, month) and grouping mode (by provider or currency pair).
    - Added `RATE_LIMIT_BYPASS_PARAM` to allow bypassing rate limits via a special request parameter.
- **Data Model & Infrastructure:**
    - Renamed `ExchangeRate` entity and table to `Rate` for simplicity.
    - Introduced `RateExtend` entity and table for storing additional market data.
    - Refactored repository layer: added `AbstractRateRepository`, `ProviderRateRepository`, and `RateExtendRepository`.
    - Added `CryptoCurrencies` utility and a comprehensive set of crypto icons for the UI.
- **Optimization:**
    - Optimized database queries (fetching two last rates in a single query).
    - Improved cache handling and removed redundant `SkipDayCache`.
    - Refactored DTOs and Response objects for better consistency and type safety.

# Release version 0.2

- **Core Architecture:**
    - Renamed `RateSource` to `Provider` across the codebase for better domain alignment.
    - Implemented `ProviderManager` and `ProviderRegistry` for better provider lifecycle management.
- **API & UI:**
    - Introduced `/api/v1/timeseries` and `/api/v1/providers` endpoints.
    - Standardized API responses to use `snake_case`.
    - Added a modern landing page with interactive charts for exchange rate visualization.
- **Caching & Performance:**
    - Integrated Redis/KeyDB for caching rates and timeseries data.
    - Implemented messenger queue optimization for unique tasks.
- **Stability:**
    - Improved handling of non-working days (weekends/holidays) with automated date correction.
    - Added comprehensive integration tests for all providers.

# Release version 0.1

- Add cbr.ru Provider
- Enhancements for MoexProvider:
    - Expanded available currencies to ~30 pairs (including metals).
    - Implemented proper historical data range fetching with pagination.
    - Improved handling of `faceValue` for currencies like KZT, AMD, JPY, UZS, etc.
    - Added validation for date ranges (max 365 days).
    - Improved robustness: handling zero prices and adding logging for non-standard API cases.
- Infrastructure:
    - Updated `ProviderImporter` to save data to both `rates` and `rates_extend` tables for extended providers.
    - Updated integration tests to support `ProviderRateExtendInterface`.
