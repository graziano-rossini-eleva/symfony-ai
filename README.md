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
| **Code Chat** | `/code-chat` | Ask questions about this project's source code in natural language. Uses RAG (Retrieval-Augmented Generation) via `symfony/ai-store` + a local Ollama embedding model to retrieve relevant code chunks and answer with Claude. |

## Tech Stack

- **PHP** 8.2+ / **Symfony** 7.4
- **Symfony AI Bundle** (`symfony/ai-bundle ^0.8`) with Anthropic platform bridge
- **Symfony MCP Bundle** (`symfony/mcp-bundle ^0.9`) — MCP server for Claude Desktop integration
- **Symfony AI Store** (`symfony/ai-store ^0.8`) — vector store, indexer, retriever for RAG
- **Symfony AI SQLite Store** (`symfony/ai-sqlite-store ^0.8`) — SQLite vector store backend
- **Symfony AI Ollama Platform** (`symfony/ai-ollama-platform ^0.8`) — local embedding model bridge
- **Claude Sonnet 4** as the AI model (Anthropic)
- **Ollama** (`nomic-embed-text`) for local embeddings — runs in Docker, no API key required
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
│   ├── ReportController.php        # Report: prompt → 3-tool pipeline → download
│   └── CodeChatController.php      # Code Chat: question → RAG → Claude answer
├── Service/
│   ├── DocChat/
│   │   ├── ChatService.php
│   │   └── SupportEmailService.php
│   ├── FileParser/
│   │   └── FileParserService.php
│   ├── Sql/
│   │   └── SqlService.php
│   ├── Advisor/
│   │   └── AdvisorService.php
│   ├── Report/
│   │   └── ReportService.php
│   └── CodeChat/
│       └── CodeChatService.php     # retrieve chunks → build context → call Claude
├── Command/
│   └── IndexCodebaseCommand.php    # app:index-codebase — scans files, chunks, vectorises
├── Tool/
│   ├── ExecuteSqlTool.php          # #[AsTool] + #[McpTool]
│   ├── CalculateStatisticsTool.php # #[AsTool] + #[McpTool]
│   ├── SaveReportTool.php          # #[AsTool]
│   └── DatabaseQueryTool.php       # unused — kept for reference
├── DataFixtures/
│   └── AppFixtures.php
└── EventSubscriber/
    ├── SecurityHeadersSubscriber.php
    └── AiExceptionSubscriber.php
config/
├── packages/
│   ├── ai.yaml                     # Agents + store + vectorizer + indexer + retriever
│   ├── ai_ollama_platform.yaml     # Ollama endpoint (OLLAMA_URL)
│   └── mcp.yaml
doc/
├── db.md
└── db_compact.md
var/
└── code_store.db                   # SQLite vector store (auto-created on first run)
```

## Requirements

- PHP 8.2+
- Composer
- Docker + Docker Compose (MySQL + Ollama)
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

The `DATABASE_URL` and `OLLAMA_URL` are already configured for the Docker containers and do not need to be changed for local development.

### 3. Start all services and the dev server

```bash
chmod +x cmd/start.sh
./cmd/start.sh
```

The script:
1. Starts MySQL + Ollama Docker containers
2. Waits for MySQL and Ollama to be ready
3. Downloads `nomic-embed-text` model on first run (~274 MB, cached in Docker volume)
4. Creates the SQLite vector store and indexes the codebase on first run
5. Starts the Symfony dev server in background

```
✓ Tutto avviato.
  App:    https://localhost:8000
  MySQL:  127.0.0.1:3306  (user: app / pass: app)
  Ollama: http://localhost:11434

  Per fermare tutto:          ./cmd/start.sh --stop
  Per re-indicizzare:         ./cmd/start.sh --index
```

| Command | Effect |
|---|---|
| `./cmd/start.sh` | Start everything |
| `./cmd/start.sh --stop` | Stop Symfony + Docker |
| `./cmd/start.sh --index` | Re-index codebase after code changes |

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

---

## Database management

| Task | Command |
|---|---|
| Start containers | `docker compose -f docker/docker-compose.yml up -d` |
| Stop containers | `docker compose -f docker/docker-compose.yml down` |
| Destroy volumes (full reset) | `docker compose -f docker/docker-compose.yml down -v` |
| Run migrations | `php bin/console doctrine:migrations:migrate` |
| Reload fixtures | `php bin/console doctrine:fixtures:load` |
| Open MySQL shell | `docker exec -it symfonyai_mysql mysql -u app -papp symfonyai` |

---

## Code Chat — RAG Setup

The Code Chat feature uses `symfony/ai-store` to index the project source files and retrieve relevant chunks at query time.

### Manual setup (if not using `cmd/start.sh`)

```bash
# 1. Start Ollama
docker compose -f docker/docker-compose.yml up -d ollama

# 2. Download the embedding model (once, ~274 MB)
docker exec symfonyai_ollama ollama pull nomic-embed-text

# 3. Create the SQLite vector store table
php bin/console ai:store:setup sqlite code

# 4. Index the project files
php bin/console app:index-codebase

# 5. Re-index after code changes
php bin/console app:index-codebase --truncate
```

### How it works

```
user question
    → Ollama (nomic-embed-text): vectorise query
    → SQLite vector store: cosine similarity search → top 8 chunks
    → Claude (code_chat agent): system prompt with chunks as context
    → answer citing source files
```

The SQLite database lives at `var/code_store.db`. It is excluded from git.

---

## AI Configuration

Agents, store, vectorizer, indexer and retriever are configured in `config/packages/ai.yaml`:

```yaml
ai:
    agent:
        default:          # Doc Chat, File Parser, SQL Assistant
        advisor:          # Advisor AI — multi-step tool calling
        report:           # Report — 3-tool orchestration pipeline
        code_chat:        # Code Chat — RAG, no tools

    store:
        sqlite:
            code:
                dsn: 'sqlite:///%kernel.project_dir%/var/code_store.db'
                table_name: 'code_chunks'

    vectorizer:
        code:
            platform: 'ai.platform.ollama'
            model: 'nomic-embed-text'

    indexer:
        code:
            vectorizer: 'ai.vectorizer.code'
            store: 'ai.store.sqlite.code'

    retriever:
        code:
            vectorizer: 'ai.vectorizer.code'
            store: 'ai.store.sqlite.code'
```

Ollama endpoint is configured separately in `config/packages/ai_ollama_platform.yaml`:

```yaml
ai:
    platform:
        ollama:
            endpoint: '%env(OLLAMA_URL)%'
```

---

## MCP Server (Claude Desktop integration)

The project exposes an MCP server via `symfony/mcp-bundle`, allowing Claude Desktop (and any MCP-compatible client) to query the platform database directly.

### Exposed tools

| Tool | Description |
|---|---|
| `execute_sql` | Runs a read-only SQL SELECT on the platform database |
| `calculate_statistics` | Computes precise descriptive statistics on a JSON array of numbers |

### Transports

| Transport | Endpoint |
|---|---|
| stdio | `php bin/console mcp:server` |
| HTTP | `GET/POST /_mcp` |

### Claude Desktop setup

Download the pre-filled config file from the home page and copy it to:

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
