# Development Plan & Architecture

# Project Structure
- `app/`: Symfony PHP application source code.
- `app/var/log/`: Application logs.
- `docker/`: Docker infrastructure and configuration.
- `Makefile`: Task runner for project operations.


# Make Commands Reference

## Lifecycle & Setup
- `make init`: Full initialization (build, up, vendor install, migrations).
- `make build`: Build Docker images.
- `make up`: Start containers.
- `make down`: Stop containers.
- `make ps`: List running containers.
- `make db-reset`: Reset Database and Redis.

## Application & Dependencies
- `make composer-i [name=...]`: Install dependencies.
- `make composer-u [name=...]`: Update dependencies.
- `make composer-dump`: Run `composer dump-autoload`.
- `make migrate`: Execute Doctrine migrations.
- `make console cmd="..."`: Run a Symfony console command (e.g., `make console cmd="cache:clear"`).
- `make php-restart`: Restart the PHP container.

## Domain Specific
- `make load-rates`: Fetch rates for the last 180 days.
- `make queue-run`: Manually consume messenger queue.
- `make queue-stats`: View queue status.
- `make queue-failed-stats`: View failed messages.
- `make queue-failed-retry`: Retry failed messages.
- `make warmup-providers-cache`: Warmup providers cache.
- `make sync-provider-currencies`: Sync hardcoded currencies with API.

## Testing & QA
- `make test`: Run all tests (excluding integration).
- `make test-unit [name=...]`: Run unit tests (optional filter).
- `make test-functional [name=...]`: Run functional tests.
- `make test-integration [name=...]`: Run integration tests (Full system test without mock).
- `make phpstan`: Run PHPStan static analysis.
- `make cs-check`: Check code style (PHP-CS-Fixer).
- `make cs-fix`: Fix code style (PHP-CS-Fixer).
- `make clear-file-var cache-clear`: command to clean the logs and cache.

## System
- `make logs [name=...]`: View last 200 log lines from container.
- `make clear-file-var`: Clear `var/cache` and `var/log`.
- `make supervisor-status`: Check background worker status.
- `make supervisor-restart`: Restart background workers.


## Architecture Overview

The project follows a **Layered Architecture** with Domain-Driven Design (DDD) elements. It heavily relies on the **Strategy Pattern** to support multiple exchange rate providers.

### Layers

1.  **Presentation Layer**
    *   **Controllers**: Handle HTTP requests and responses (`RateController`).
    *   **DTOs**: Data Transfer Objects for strict typing of inputs and outputs (`RateRequest`, `RateResponse`).
    *   **Commands**: CLI entry points for background tasks (`FetchHistoricalRatesCommand`).
    *   **Scheduler**: Scheduled tasks configuration (`MainSchedule`) using Symfony Scheduler.

2.  **Application Layer**
    *   **Services**: Orchestrate business logic.
        *   `ProviderManager`: Main entry point for retrieving rates (from cache, DB, or API). Handles cross-rate calculations and day corrections.
        *   `ProviderRegistry`: Locates and provides specific implementation of `ProviderInterface` using `AutowireLocator`.
        *   `ProviderImporter`: Handles the logic of fetching data from external APIs and saving it to the database, including handling holidays/weekends.
    *   **Messages**: Async message definitions (`FetchRateMessage`).
    *   **MessageHandlers**: Process async messages (`FetchRateHandler`).
    *   **ServiceCache**: Caching strategies (`RateCache`, `CorrectedDayCache`).

3.  **Domain Layer**
    *   **Entities**: Core business objects (`Rate`, `RateExtend`).
    *   **Repositories**: `RateRepositoryInterface` for data access.
    *   **Enums**: Fixed sets of values (`ProviderEnum` - lists all supported providers).
    *   **Contracts**: Interfaces for services (`ProviderInterface`, `RateCacheInterface`, `RateEntityInterface`).

4.  **Infrastructure Layer**
    *   **Repositories**:
        *   `RateRepository`, `RateExtendRepository`: Doctrine implementations.
        *   `ProviderRateRepository`: A **Proxy/Strategy** service implementing `RateRepositoryInterface`. It transparently routes calls to either `RateRepository` or `RateExtendRepository` based on the provider's capabilities (whether it implements `ProviderRateExtendInterface`). This allows the system to store additional market data (like OHLC) for advanced providers without bloating the base `Rate` entity.
    *   **External API Clients**: Implementations of `ProviderInterface`.
        *   Base: `AbstractApiProvider`.
        *   Implementations: `CbrProvider`, `EcbProvider`, `OpenExchangeRatesProvider`, and many others (20+ providers).
        *   Extended: `MoexProvider` implements `ProviderRateExtendInterface` to provide additional market data (OHLC).
    *   **Cache**: Redis/KeyDB integration via Symfony Cache.
    *   **Messenger**: Async task processing via Symfony Messenger (Redis transport).

## Key Decisions

*   **Symfony 8 / PHP 8.5**: Utilizing the latest features for performance and type safety.
*   **Provider Abstraction**: A flexible system where new providers can be added by implementing `ProviderInterface` and registering them via `ProviderEnum`. The `ProviderRegistry` automatically locates them.
*   **Resilience & Stability**: 
    *   `isActive()`: Providers can be programmatically disabled if they are unreachable or have invalid configuration.
    *   `getRequestDelay()`: Supports per-provider delays to respect external API constraints during batch imports.
    *   **Rate Limiting**: Built-in tracking of request counts to avoid hitting external API quotas.
*   **Symfony Scheduler**: Used for managing recurring tasks (e.g., daily rate fetches).
*   **BCMath**: Used for all financial calculations to ensure precision.
*   **Redis/KeyDB**: Used for caching exchange rates and as a transport for Symfony Messenger.
*   **Doctrine ORM**: For database interactions.
*   **OpenAPI (Swagger)**: Auto-generated documentation via `NelmioApiDocBundle`.
*   **Strict Typing**: `declare(strict_types=1);` everywhere.
*   **DTOs**: Used for all data transfer between layers.

## Data Flow

1.  **Rate Request**:
    *   Client -> `RateController` -> `ProviderManager`.
    *   `ProviderManager` -> `CorrectedDayCache` (check for holiday adjustment).
    *   `ProviderManager` -> `RateCache` (check for cached response).
    *   If Cache miss -> `ProviderManager` -> `ProviderRegistry` -> `SpecificProvider` (get base currency).
    *   If provider's base currency matches requested -> `ProviderManager` -> `RateRepository` (DB).
    *   If DB miss -> Dispatch `FetchRateMessage` (async) -> Return 202 Accepted.
    *   Async Worker -> `FetchRateHandler` -> `ProviderImporter` -> `SpecificProvider` (API) -> `RateRepository` (Save).

2.  **Cross-Rate Calculation**:
    *   If requested base currency != provider's base currency (e.g., USD/EUR via RUB source).
    *   Fetch Target/ProviderBase (USD/RUB).
    *   Fetch Base/ProviderBase (EUR/RUB).
    *   Calculate Rate = (Target/ProviderBase) / (Base/ProviderBase).
    *   Calculate Diff using previous day's rates.

3.  **Historical Data & Import**:
    *   **Manual**: `FetchHistoricalRatesCommand` -> Dispatch `FetchRateMessage` for N days.
    *   **Scheduled**: `MainSchedule` -> Dispatch `FetchRateMessage` (cron).
    *   **Worker**: `FetchRateHandler` -> `ProviderImporter`.
    *   `ProviderImporter` handles holiday logic:
        *   If API returns a date different from requested (e.g., weekend), it saves the rate for the *returned* date.
        *   It updates `CorrectedDayCache` to map the *requested* date to the *returned* date.
        *   It may trigger additional fetches for previous days to calculate the difference (`diff`).
