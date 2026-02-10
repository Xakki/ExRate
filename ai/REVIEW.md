# Code Review Report - 2026-02-16

## Overview
The project is in excellent shape after the recent refactoring. The transition from a single source to a multi-provider strategy is well-implemented. The use of modern PHP 8.5 features and Symfony 8 attributes ensures high maintainability and type safety.

## Strengths
- **Scalability**: The Strategy pattern used for providers allows for easy addition of new sources.
- **Performance**: High-performance batch database operations and multi-level caching.
- **Observability**: Good use of Monolog context and dedicated log viewer.
- **Developer Experience**: Comprehensive Makefile and clear documentation.

## Areas for Improvement

### High Priority
*None identified.*

### Medium Priority

#### 1. Database Batching Limits
- **File**: `app/src/Repository/ExchangeRateRepository.php`
- **Method**: `saveRatesBatch`
- **Issue**: The current implementation inserts all rates in a single query. While safe for 200-300 currencies, it might hit MariaDB/MySQL placeholder limits if the list grows significantly or if many fields are added.
- **Suggestion**: Implement chunking (e.g., using `array_chunk`) to limit the number of placeholders per query (e.g., max 1000 placeholders).

#### 2. Provider Auto-Sync Safety
- **File**: `app/src/Command/SyncProviderCurrenciesCommand.php`
- **Issue**: The command modifies PHP source files directly using `preg_replace`. While efficient for keeping hardcoded lists updated, it relies on a specific method signature and formatting.
- **Suggestion**: Ensure the method template in the replacement logic is strictly enforced or consider using a more robust parser if the provider class structure becomes more complex.

### Minor Priority

#### 1. Inactive Providers
- **File**: `app/src/Provider/BnbProvider.php`
- **Issue**: Marked as broken/todo. It's correctly handled via `isActive(): false`, but the implementation remains in the codebase.
- **Suggestion**: Since it's documented, it's fine for now, but a regular "garbage collection" of permanently broken providers should be planned.

#### 2. Retry Logic Configuration
- **File**: `app/src/MessageHandler/FetchRateHandler.php`
- **Issue**: Retry intervals are hardcoded in the handler.
- **Suggestion**: Move retry strategies to Symfony Messenger configuration (`messenger.yaml`) or a dedicated configuration parameter.

## Conclusion
The architecture is robust and follows modern best practices. The project is ready for further expansion of providers and higher traffic loads.
