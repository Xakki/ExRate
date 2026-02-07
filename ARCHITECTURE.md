# Development Plan & Architecture

## Architecture Overview

The project follows a **Layered Architecture** with Domain-Driven Design (DDD) elements.

### Layers

1.  **Presentation Layer**
    *   **Controllers**: Handle HTTP requests and responses.
    *   **DTOs**: Data Transfer Objects for strict typing of inputs and outputs.
    *   **Commands**: CLI entry points for background tasks.

2.  **Application Layer**
    *   **Services**: Orchestrate business logic (`ExchangeRateService`).
    *   **Messages**: Async message definitions.
    *   **MessageHandlers**: Process async messages.

3.  **Domain Layer**
    *   **Entities**: Core business objects (`ExchangeRate`).
    *   **Repositories**: Interfaces for data access (implementation in Infrastructure).

4.  **Infrastructure Layer**
    *   **Repositories**: Doctrine implementations.
    *   **External API Clients**: `CbrRateSource`.
    *   **Cache**: Redis/KeyDB integration.

## Key Decisions

*   **Symfony 8 / PHP 8.5**: Utilizing the latest features for performance and type safety.
*   **BCMath**: Used for all financial calculations to ensure precision.
*   **Redis/KeyDB**: Used for caching exchange rates and as a transport for Symfony Messenger.
*   **Doctrine ORM**: For database interactions.
*   **OpenAPI (Swagger)**: Auto-generated documentation via `NelmioApiDocBundle`.

## Data Flow

1.  **Rate Request**:
    *   Client -> Controller -> Service -> Cache -> DB -> CBR API.
    *   If Cache miss -> Check DB.
    *   If DB miss -> Fetch from CBR -> Save to DB -> Save to Cache.
    *   If CBR fails -> Fallback to latest DB entry (with `is_fallback` flag).

2.  **Historical Data**:
    *   Command -> Message Bus -> Worker -> Service -> CBR API -> DB.

## Future Improvements

*   Add more currency sources (ECB, OpenExchangeRates).
*   Implement circuit breaker for external APIs.
*   Add metrics (Prometheus) and tracing (OpenTelemetry).
