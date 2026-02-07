# CBR Exchange Rate Service

A Symfony 8 / PHP 8.5 application to fetch and cache exchange rates from the Central Bank of Russia (CBR).

## Features

- **Real-time Rates**: Fetch current exchange rates via CBR XML API.
- **Historical Data**: Calculate difference with the previous trading day.
- **Caching**: High-performance caching using KeyDB (Redis compatible).
- **Resilience**: Fallback to local database if external API is unreachable.
- **Background Workers**: Async processing for historical data fetching (last 180 days).
- **API Documentation**: Auto-generated OpenAPI (Swagger) documentation.
- **AI Agent Ready**: MCP configs and AGENTS.md

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
├── .env_dist               # Environment Variables
├── docker-compose.yml      # Container Orchestration
├── Makefile                # Task Runner
```

## Requirements

- **Docker** & **Docker Compose** : run project
- **UVX** : (optional, for AI AGENTS MCP) https://docs.astral.sh/uv/getting-started/installation

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

### 4. Fill rates
Run:
```bash
make load-rates
```

Open your browser and visit:
- **API Docs**: [http://localhost/](http://localhost/)

## Usage

### API Endpoints

**Get Exchange Rate**
`GET http://localhost/api/v1/rate`

Returns the rate for a specific currency and the difference from the previous trading day.

**Parameters:**
- `currency` (required): ISO code,.
- `date` (optional): `Y-m-d` (default: today).
- `base_currency` (optional): Default `RUB`.

**Example Request:**
```bash
curl "http://localhost/api/v1/rate?date=2026-02-09&currency=USD"
```

**Example Response:**
```json
{
    "rate":"77.05400000",
    "diff":"0.5017",
    "dateDiff":"2026-02-06",
    "date":"2026-02-07",
    "timestamp":"2026-02-09T21:51:29+03:00",
    "isFallback":false
}
```
Because for `2026-02-09` CBR return rate for `2026-02-07`

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

see [ARCHITECTURE.md](ARCHITECTURE.md).
