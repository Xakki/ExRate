# Main info
- You are a high-level PHP developer with experience in developing web projects on Symfony and building architecture, as well as OpenSource development;
- ./app contain PHP code Symfony project;
- ./docker contain docker build configs;
- [README.md](README.md) Contain project info and instruction for run and test;
- Logs ./app/var/log
- Project architecture [ARCHITECTURE.md](ARCHITECTURE.md)
- AI Review [REVIEW.md](REVIEW.md)

# Code write rules
- Apply SOLID, KISS, DRY, YAGNI and explicit error handling. Prefer small, cohesive changes with clear interfaces and minimal coupling;
- Keep documentation and code comments in English;
- Check logs in php container;
- comply with PER Coding Style 3.0;
- Use only DTO instead of array for the return values of the methods.
- Low coupling and high cohesion

## PHP 8.5 & Symfony 8 Best Practices
- **Strict Typing**: Always use `declare(strict_types=1);` at the beginning of PHP files.
- **Modern PHP Features**:
  - Use **Constructor Property Promotion** to reduce boilerplate.
  - Use **Readonly Classes** for DTOs and Value Objects.
  - Use **Enums** for fixed sets of values.
  - Use **Match expressions** instead of complex switch/if-else chains.
  - Use **Attributes** (#[Attribute]) instead of PHPDoc annotations.
  - Use **Property Hooks** (PHP 8.4+) for concise getter/setter logic.
- **Symfony Architecture**:
  - Use **Attributes** for Routing, Validation, Doctrine Mapping, and Dependency Injection configuration.
  - **Dependency Injection**: Always use constructor injection. Avoid `ContainerAware` or direct container access.
  - **Service Configuration**: Use `#[AsCommand]`, `#[AsEventListener]`, `#[AsSchedule]`, etc., to register services automatically.
  - **Controllers**: Keep controllers thin. Delegate business logic to Services/Handlers. Return DTOs or specific Response objects.
- **Type Safety**:
  - Use native PHP types for arguments and return values.
  - Use `mixed`, `union types`, and `intersection types` appropriately.
  - Avoid `mixed` if a more specific type can be defined.

# Make commands
- Load @./Makefile;
- Use MCP server `make-exrate` to run Makefile commands;
- DONT use commands `logs-follow`, `php-console`, `composer-bash`;
- When fully finish the task - Run the make commands `test`, `phpstan`, `cs-fix`, `cs-check`, and fix any errors that occur;
- If need run specific command add it to Makefile and run with MCP `make-exrate`;
