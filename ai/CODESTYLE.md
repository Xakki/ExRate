# Code write rules
- Apply SOLID, KISS, DRY, YAGNI and explicit error handling;
- Prefer small, cohesive changes with clear interfaces and minimal coupling;
- Low coupling and high cohesion;
- Keep documentation and code comments in English;
- Check logs in php container;
- comply with PER Coding Style 3.0;
- Use strict typing for arguments and return values;
- Use only DTO instead of array for the return values of the methods;
- if need to delete file - clear them and writ "@deleted" into the file;
- **Constants**: Always specify the type (e.g., `public const string MY_CONST = 'value';`).
- **Values & Configuration**:
    - Do not use hardcoded numeric or string values (magic numbers/strings) directly in logic.
    - Move fixed values to typed constants.
    - Move configurable or environment-dependent values to configuration files (`services.yaml` or `.env`).
- **File Operations**: Use MCP server `phpstorm` for working with files.

## Testing Standards
- **Functional Tests** (`app/tests/Functional`):
    - Must mock cache and message queues.
    - Use `app/tests/Functional/RateControllerTest.php` as a template for implementation (specifically for mocking cache and message bus via public interfaces).
- **Integration Tests** (`app/tests/Integration`):
    - Do NOT run integration tests unless specifically requested by the user.
    - Run as a full application flow.
    - Use a dedicated test database and dedicated cache namespace (based on `APP_ENV`).
    - Message queues must be configured to process synchronously in the test environment.

## Best Practices
- **Strict Typing**: Always use `declare(strict_types=1);` at the beginning of PHP files.
- **Modern PHP Features**:
    - Use **Constructor Property Promotion** to reduce boilerplate.
    - Use **Readonly Classes** for DTOs and Value Objects.
    - Use **Enums** for fixed sets of values.
    - Use **Match expressions** instead of complex switch/if-else chains.
    - Use **Attributes** (#[Attribute]) instead of PHPDoc annotations.
    - Use **Property Hooks** (PHP 8.5+) for concise getter/setter logic.
- **Symfony Architecture**:
    - Use **Attributes** for Routing, Validation, Doctrine Mapping, and Dependency Injection configuration.
    - **Dependency Injection**: Always use constructor injection. Avoid `ContainerAware` or direct container access.
    - **Service Configuration**: Use `#[AsCommand]`, `#[AsEventListener]`, `#[AsSchedule]`, etc., to register services automatically.
    - **Controllers**: Keep controllers thin. Delegate business logic to Services/Handlers. Return DTOs or specific Response objects. **Do not place business logic inside controllers.**
    - **API Endpoints**: 
        - Use specific DTOs for all request parameters (e.g., `\App\DTO\RateRequest`).
        - Use specific DTOs for all responses (e.g., `\App\DTO\RateResponse`).
        - **All API output (JSON) must use `snake_case` for keys.**
- **Type Safety**:
    - Use native PHP types for arguments and return values.
    - Use `mixed`, `union types`, and `intersection types` appropriately.
    - Avoid `mixed` if a more specific type can be defined.
