# CBR Exchange Rate Service

A Symfony 8 / PHP 8.5 application to fetch and cache exchange rates from the Central Bank of Russia (CBR).

## Features

- **Real-time Rates**: Fetch current exchange rates via CBR XML API.
- **Historical Data**: Calculate difference with the previous trading day.
- **Caching**: High-performance caching using KeyDB (Redis compatible).
- **Resilience**: Fallback to local database if external API is unreachable.
- **Background Workers**: Async processing for historical data fetching (last 180 days).
- **API Documentation**: Auto-generated OpenAPI (Swagger) documentation.

## Project Structure

```
exrate/
├── app/                    # Symfony Application Source
│   ├── config/             # Framework configuration
│   ├── public/             # Web server entry point (index.php)
│   ├── src/                # Application Code
│   │   ├── Command/        # CLI Commands (History fetch, Schedule)
│   │   ├── Controller/     # HTTP Request Handlers
│   │   ├── DTO/            # Data Transfer Objects
│   │   ├── Entity/         # Database Models
│   │   ├── Message/        # Async Messages
│   │   ├── Repository/     # Database Access Layer
│   │   └── Service/        # Business Logic (CBR integration)
│   └── tests/              # Unit and Integration Tests
├── docker/                 # Infrastructure Configuration
│   ├── nginx/              # Web Server Config
│   └── php/                # PHP-FPM & Supervisor Config
├── .env                    # Environment Variables
├── docker-compose.yml      # Container Orchestration
├── Makefile                # Task Runner
└── PLAN.md                 # Development Plan & Architecture
```

## Requirements

- **Docker** & **Docker Compose**
- **Make** (optional, for running shortcut commands)

## Getting Started

### 1. Clone the repository
```bash
git clone <repository-url>
cd exrate
```

### 2. Initialize the Project
Run the helper command to build containers, install dependencies, and setup the database:
```bash
make init
```

*This runs `composer install`, starts Docker containers, and runs database migrations.*

### 3. Verify Installation
Check if the services are running:
```bash
docker compose ps
```

Open your browser and visit:
- **API Docs**: [http://localhost/](http://localhost/)

## Usage

### API Endpoints

**Get Exchange Rate**
`GET /api/v1/rate`

Returns the rate for a specific currency and the difference from the previous trading day.

**Parameters:**
- `currency` (required): ISO code (e.g., `USD`, `EUR`).
- `date` (optional): `Y-m-d` (default: today).
- `base_currency` (optional): Default `RUB`.

**Example Request:**
```bash
curl "http://localhost/api/v1/rate?currency=USD"
```

**Example Response:**
```json
{
  "rate": "92.5000",
  "diff": "-0.5000",
  "date": "2024-02-04",
  "timestamp": "2024-02-04T12:00:00+00:00"
}
```

### Console Commands

Queue jobs and scheduler run with supervisord.

Run these commands from your host machine using `make console`:

**Fetch Historical Rates**
Populate the database with rates for the last 180 days.
```bash
make console cmd="app:fetch-history --days=180"
```

**Manual run Schedule Worker**
Start the worker that triggers daily updates (useful for testing the scheduler).
```bash
make console cmd="app:schedule-worker"
```

## Development & Testing

**Run Tests**
```bash
make test
```

**Static Analysis (PHPStan)**
```bash
make phpstan
```

**Code Style Check & Fix**
```bash
make cs-check
make cs-fix
```

**View Logs**
```bash
make logs-follow
```

## Architecture

The project follows a **Layered Architecture**:
1.  **Presentation**: Controllers, DTOs.
2.  **Application**: Services, Commands.
3.  **Domain**: Entities.
4.  **Infrastructure**: Repositories, External API Clients.

For detailed architectural decisions, see [PLAN.md](PLAN.md).
