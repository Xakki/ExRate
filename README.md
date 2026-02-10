# CBR Exchange Rate Service

https://exrate.xakki.pro

A Symfony 8 / PHP 8.5 application to fetch and cache exchange rates

## Features

- **Real-time Rates**: Fetch current exchange rates (from many provider).
- **Historical Data**: Calculate difference with the previous trading day.
- **Cross-Rates**: Calculate rates between any two currencies
- **Caching**: High-performance caching using KeyDB (Redis compatible).
- **Resilience**: Fallback to local database if external API is unreachable.
- **API Documentation**: Auto-generated OpenAPI (Swagger) documentation.
- **AI Agent Ready**: AGENTS.md

## Requirements

- **Docker** & **Docker Compose** : run project
- **UVX** : (optional, for AI AGENTS MCP) https://docs.astral.sh/uv/getting-started/installation

## Getting Started

### 1. Clone the repository
```bash
git clone https://github.com/Xakki/ExRate
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
make ps
```

### 4. Fill rates
Run:
```bash
make load-rates
```

Open your browser and visit:
- **Home page**: [http://localhost/](http://localhost/)
- **API Docs**: [http://localhost/api](http://localhost/api)
- **Log view (For dev only)**: [http://localhost/log-viewer/log](http://localhost/log-viewer/log)

## Usage

### API Endpoints

**Get Exchange Rate**
`GET http://localhost/api/v1/rate`

Returns the rate for a specific currency and the difference from the previous trading day.

**Parameters:**
- `currency` (required): ISO code (e.g., USD).
- `date` (optional): `Y-m-d` (default: today).
- `base_currency` (optional): Default `RUB`.
- `provider` (optional): Default `cbr`.

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
    "timestamp":"2026-02-09T21:51:29+03:00"
}
```
*Note: If the requested date is a holiday/weekend, the service returns the rate for the last trading day.*

**Response Codes:**
- `200 OK`: Rate found.
- `202 Accepted`: Rate not found yet, background task started. Retry later.
- `400 Bad Request`: Invalid parameters.

## Development & Testing

**Run All Tests**
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

## Architecture

see [ARCHITECTURE.md](ARCHITECTURE.md).
