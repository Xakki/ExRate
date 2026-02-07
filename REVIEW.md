# Architecture Review

## Overview

The project demonstrates a solid foundation using Symfony 8 and PHP 8.5, adhering to modern practices like strict typing, constructor property promotion, and attributes. The layered architecture (Presentation, Application, Domain, Infrastructure) is generally well-respected.

## Strengths

*   **Modern Stack**: Usage of PHP 8.5 and Symfony 8 features (Attributes, Readonly classes, Enums).
*   **DDD Principles**: Clear separation of Domain entities and Application logic.
*   **Type Safety**: Extensive use of strict types and DTOs.
*   **Async Processing**: Use of Symfony Messenger for background tasks (fetching rates).
*   **Caching**: Implementation of caching strategies (Redis/KeyDB) to reduce external API calls.
*   **Documentation**: OpenAPI integration for API documentation.

## Areas for Improvement

### 1. Domain Logic Leakage
*   **Issue**: `ExchangeRateProvider` contains significant business logic regarding cross-rate calculation and fallback handling.
*   **Recommendation**: Move calculation logic (cross-rate, diff calculation) into a dedicated Domain Service (e.g., `RateCalculator`) or keep it in `ExchangeRateProvider` but ensure it doesn't become a "God Class". The current state is acceptable but watch for growth.

### 2. Repository Responsibility
*   **Issue**: `ExchangeRateRepository::existRates` checks for existence based on `baseCurrency` and `sourceId` for a date. This implies checking if *any* rates exist for that source/date.
*   **Recommendation**: Ensure this logic aligns with how the importer works. If the importer fetches all rates for a base currency at once, this is fine.

### 3. Error Handling & Resilience
*   **Issue**: `ExchangeRateProvider::fetchDirectRate` throws `RateNotFoundException` immediately after dispatching a message. This effectively fails the first request for a new date/currency.
*   **Recommendation**: This is likely by design (async fetch), but the API client receives a 404 (or 500 depending on exception handling) on the first hit. Consider returning a "pending" status or blocking (with timeout) if immediate consistency is required, though the current "eventual consistency" approach is scalable. The `isFallback` flag in `RateResponse` handles the "diff not ready" case, but not the "rate not found" case.

### 4. DTO Validation
*   **Issue**: `RateRequest` validation logic (`validateDateRange`) is inside the DTO.
*   **Recommendation**: This is a valid approach in Symfony using Callback constraints. Keep it there for cohesion.

### 5. Hardcoded Values
*   **Issue**: `ExchangeRateProvider` has hardcoded cache TTL and keys.
*   **Recommendation**: Move these to configuration (constants are fine for now, but config allows environment-specific tuning).

### 6. Entity Design
*   **Issue**: `ExchangeRate` entity uses `float` (via `DECIMAL` type mapping to string in PHP, which is good) but `sourceId` is an integer.
*   **Recommendation**: Consider using an Enum for `sourceId` in the Entity if it maps strictly to `RateSource` enum, or keep as int if dynamic sources are planned. Currently, it maps to `RateSource` enum values (implied).

### 7. Concurrency
*   **Issue**: `ExchangeRateImporter::saveRates` handles `UniqueConstraintViolationException` by clearing the EntityManager.
*   **Recommendation**: This is a brute-force approach. If a race condition occurs, the data might already exist. The current approach discards the current batch. Ensure this doesn't lead to data gaps if the existing data was partial.

## Specific Code Review Points

*   **`ExchangeRateProvider.php`**:
    *   `getCorrectedDay`: Good use of caching for holiday/weekend adjustments.
    *   `calculateCrossRate`: `bcdiv` and `bcsub` are used correctly for precision.
*   **`RateController.php`**:
    *   Clean and thin. Delegates to `ExchangeRateProvider`.
*   **`ExchangeRateImporter.php`**:
    *   `saveCorrectDays`: Logic to fill gaps in cache for non-trading days is clever.

## Conclusion

The architecture is robust and suitable for the task. The separation of concerns is largely maintained. The use of async messaging for fetching missing rates is a strong design choice for performance, though it complicates the "first request" UX.
