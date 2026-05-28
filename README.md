# SymfonyAI

A sandbox project for testing and evaluating the [Symfony AI Bundle](https://symfony.com/doc/current/ai.html) (`symfony/ai-bundle`), the official Symfony component for integrating AI models into Symfony applications.

Each feature exercises a different capability of the bundle through a concrete use case.

## Features

| Feature | Route | Description |
|---|---|---|
| **Doc Chat** | `/doc-chat` | Upload a Markdown file (or use the built-in project doc as fallback) and chat with an AI assistant about its content. Supports email escalation to a human support team. |
| **File Parser** | `/file-parser` | Upload a PDF and describe what to extract in plain language. The AI returns a structured JSON object. |
| **SQL Assistant** | `/sql` | Describe in plain language what data you want; the AI generates a safe read-only SQL SELECT and runs it against the database, showing paginated results. |
| **Advisor AI** | `/advisor` | Ask complex questions about the platform data. A dedicated agent with multi-step tool calling autonomously runs as many SQL queries as needed and synthesises a natural-language answer. |
| **Report Analitico** | `/report` | Generate downloadable Markdown reports through a 3-tool agentic pipeline: SQL retrieval → precise statistics computation → file output with download token. |

## Tech Stack

- **PHP** 8.2+ / **Symfony** 7.4
- **Symfony AI Bundle** (`symfony/ai-bundle ^0.8`) with Anthropic platform bridge
- **Symfony MCP Bundle** (`symfony/mcp-bundle ^0.9`) — MCP server for Claude Desktop integration
- **Claude Sonnet 4** as the AI model
- **MySQL 8** via Docker
- **Doctrine ORM** + **Doctrine Migrations** + **DoctrineFixturesBundle**
- **Symfony UX Turbo** (Hotwire) for reactive UI
- **Twig** templates

## Project Structure

```
src/
├── Controller/
│   ├── HomeController.php          # Landing page + GET /mcp-config download
│   ├── DocChatController.php       # Doc Chat: upload + chat + email escalation
│   ├── FileParserController.php    # File Parser: upload + extract
│   ├── SqlController.php           # SQL Assistant: prompt → SQL → paginated table
│   ├── AdvisorController.php       # Advisor AI: question → multi-step agent → answer
│   └── ReportController.php        # Report: prompt → 3-tool pipeline → download
├── Service/
│   ├── DocChat/
│   │   ├── ChatService.php         # AI prompt, agent call, tag detection
│   │   └── SupportEmailService.php # Email assembly, transcript, history sanitisation
│   ├── FileParser/
│   │   └── FileParserService.php   # PDF read, prompt injection mitigation, JSON normalisation
│   ├── Sql/
│   │   └── SqlService.php          # Schema loading, SQL generation via AI, safe DBAL execution
│   ├── Advisor/
│   │   └── AdvisorService.php      # Builds MessageBag, calls advisor agent, returns answer
│   └── Report/
│       └── ReportService.php       # Injects date + schema, calls report agent, handles retry
├── Tool/
│   ├── ExecuteSqlTool.php          # #[AsTool] + #[McpTool] — validates and runs SQL SELECT
│   ├── CalculateStatisticsTool.php # #[AsTool] + #[McpTool] — precise descriptive statistics
│   ├── SaveReportTool.php          # #[AsTool] — saves Markdown to var/reports/, stores token
│   └── DatabaseQueryTool.php       # unused — double-AI pattern, kept for reference
├── DataFixtures/
│   └── AppFixtures.php             # Seed data: 100+ records per table via FakerPHP
└── EventSubscriber/
    ├── SecurityHeadersSubscriber.php
    └── AiExceptionSubscriber.php   # Converts RateLimitExceededException to JSON 429
config/
├── packages/
│   ├── ai.yaml                     # Agent definitions (default, advisor, report)
│   └── mcp.yaml                    # MCP server config (stdio + HTTP, schema in instructions)
doc/
├── db.md                           # Full database schema (~2600 tokens, used by SqlService)
└── db_compact.md                   # Compact schema (~210 tokens, used by Advisor + Report + MCP)
templates/
├── home/index.html.twig
├── doc_chat/
│   ├── upload.html.twig
│   └── index.html.twig
├── file_parser/index.html.twig
├── sql/index.html.twig
├── advisor/index.html.twig
├── report/index.html.twig
└── email/support_request.html.twig
translations/
└── messages.it.yaml
```

## Requirements

- PHP 8.2+
- Composer
- Docker + Docker Compose (for the MySQL database)
- An [Anthropic API key](https://console.anthropic.com/)

## Installation

### 1. Clone and install dependencies

```bash
git clone <repository-url>
cd SymfonyAI
composer install
```

### 2. Create your local environment file

```bash
cp .env .env.local
```

Edit `.env.local` and set at minimum:

```dotenv
APP_SECRET=          # generate with: openssl rand -hex 32
ANTHROPIC_API_KEY=   # sk-ant-...
SUPPORT_EMAIL=support@yourdomain.com
FROM_EMAIL=noreply@yourdomain.com
```

The `DATABASE_URL` is already configured for the Docker MySQL container and does not need to be changed for local development:

```dotenv
DATABASE_URL="mysql://app:app@127.0.0.1:3306/symfonyai?serverVersion=8.0.32&charset=utf8mb4"
```

### 3. Start database and dev server

A convenience script handles Docker and the Symfony server in one command:

```bash
chmod +x cmd/start.sh
./cmd/start.sh
```

The script starts the MySQL 8 Docker container, waits until MySQL is ready, then starts the Symfony dev server in background:

```
✓ Tutto avviato.
  App:   https://localhost:8000
  MySQL: 127.0.0.1:3306  (user: app / pass: app)

  Per fermare tutto: ./cmd/start.sh --stop
```

To stop everything: `./cmd/start.sh --stop`

MySQL credentials:

| Parameter | Value |
|---|---|
| Host | `127.0.0.1:3306` |
| Database | `symfonyai` |
| User | `app` |
| Password | `app` |
| Root password | `root` |

### 4. Run migrations

```bash
php bin/console doctrine:migrations:migrate
```

### 5. Load seed fixtures

> **Before running fixtures, verify that `DATABASE_URL` points to a local development database.
> The database name must NOT contain `_staging` or `_prod`.**

```bash
php bin/console doctrine:fixtures:load
```

Confirm the prompt (`yes`). This purges the database and inserts:

| Table | Records |
|---|---|
| `users` | 100 (20 instructors + 80 students) |
| `categories` | 100 |
| `courses` | 100 |
| `lessons` | 500 (5 per course) |
| `enrollments` | 100 |
| `lesson_progress` | 200 |
| `reviews` | 100 |

All data is randomly generated via [FakerPHP](https://fakerphp.org/).

Open [https://localhost:8000](https://localhost:8000).

---

## Database management

| Task | Command |
|---|---|
| Start MySQL container | `docker compose -f docker/docker-compose.yml up -d` |
| Stop MySQL container | `docker compose -f docker/docker-compose.yml down` |
| Destroy volume (full reset) | `docker compose -f docker/docker-compose.yml down -v` |
| Run migrations | `php bin/console doctrine:migrations:migrate` |
| Reload fixtures | `php bin/console doctrine:fixtures:load` |
| Open MySQL shell | `docker exec -it symfonyai_mysql mysql -u app -papp symfonyai` |

---

## AI Configuration

Model and agents are configured in `config/packages/ai.yaml`:

```yaml
ai:
    agent:
        default:                          # used by Doc Chat, File Parser, SQL Assistant
            platform: 'ai.platform.anthropic'
            model:
                name: !php/const Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_4
                options:
                    max_tokens: 8096
        advisor:                          # used by Advisor AI — multi-step tool calling
            platform: 'ai.platform.anthropic'
            model:
                name: !php/const Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_4
                options:
                    max_tokens: 8096
            tools:
                - App\Tool\ExecuteSqlTool
        report:                           # used by Report — 3-tool orchestration pipeline
            platform: 'ai.platform.anthropic'
            model:
                name: !php/const Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_4
                options:
                    max_tokens: 8096
            tools:
                - App\Tool\ExecuteSqlTool
                - App\Tool\CalculateStatisticsTool
                - App\Tool\SaveReportTool
```

`max_tokens` is set to **8096** explicitly because the bundle default (1000) is too low for structured JSON responses over multi-page PDFs — the model output gets truncated mid-JSON.

All Tool classes have `autoconfigure: false` in `services.yaml` to prevent `#[AsTool]` from registering them globally on every agent. Each tool is wired explicitly to its agent in `ai.yaml`. `ExecuteSqlTool` and `CalculateStatisticsTool` are additionally tagged `mcp.tool` for MCP server exposure.

---

## MCP Server (Claude Desktop integration)

The project exposes an MCP server via `symfony/mcp-bundle`, allowing Claude Desktop (and any MCP-compatible client) to query the platform database directly.

### Exposed tools

| Tool | Description |
|---|---|
| `execute_sql` | Runs a read-only SQL SELECT on the platform database |
| `calculate_statistics` | Computes precise descriptive statistics on a JSON array of numbers |

The database schema is embedded in the MCP server `instructions` (`config/packages/mcp.yaml`), so the client never needs to query `information_schema`.

### Transports

| Transport | Endpoint |
|---|---|
| stdio | `php bin/console mcp:server` |
| HTTP | `GET/POST /_mcp` |

### Claude Desktop setup

Download the pre-filled config file from the home page (`GET /mcp-config`) and copy it to:

```
~/Library/Application Support/Claude/claude_desktop_config.json   # macOS
%APPDATA%\Claude\claude_desktop_config.json                        # Windows
```

Then restart Claude Desktop.

---

## Testing

```bash
php bin/phpunit
```

> **Before running tests, verify that `DATABASE_URL` does not point to a `_staging` or `_prod` schema.**

---

## License

Proprietary — all rights reserved.
