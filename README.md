# SymfonyAI

A sandbox project for testing and evaluating the [Symfony AI Bundle](https://symfony.com/doc/current/ai.html) (`symfony/ai-bundle`), the official Symfony component for integrating AI models into Symfony applications.

Each feature exercises a different capability of the bundle through a concrete use case.

## Features

| Feature | Status | Description |
|---|---|---|
| **Doc Chat** | Available | Upload a Markdown file and chat with an AI assistant about its content. Supports email escalation to a human support team. |
| **File Parser** | Available | Upload a PDF and describe what to extract in plain language. The AI returns a structured JSON object. |
| **SQL Assistant** | Available | Describe in plain language what data you want; the AI generates a safe read-only SQL SELECT and runs it against the database, showing paginated results. |

## Tech Stack

- **PHP** 8.2+ / **Symfony** 7.4
- **Symfony AI Bundle** (`symfony/ai-bundle`) with Anthropic platform bridge
- **Claude Sonnet 4** as the AI model
- **MySQL 8** via Docker
- **Doctrine ORM** + **Doctrine Migrations** + **DoctrineFixturesBundle**
- **Symfony UX Turbo** (Hotwire) for reactive UI
- **Twig** templates

## Project Structure

```
src/
├── Controller/
│   ├── HomeController.php          # Feature selection landing page
│   ├── DocChatController.php       # Doc Chat: upload + chat + email escalation
│   ├── FileParserController.php    # File Parser: upload + extract
│   └── SqlController.php           # SQL Assistant: prompt → SQL → paginated table
├── Service/
│   ├── DocChat/
│   │   ├── ChatService.php         # AI prompt, agent call, tag detection
│   │   └── SupportEmailService.php # Email assembly, transcript, history sanitisation
│   ├── FileParser/
│   │   └── FileParserService.php   # PDF read, prompt injection mitigation, JSON normalisation
│   └── Sql/
│       └── SqlService.php          # Schema loading, SQL generation via AI, safe DBAL execution
├── DataFixtures/
│   └── AppFixtures.php             # Seed data: 100+ records per table via FakerPHP
└── EventSubscriber/
    └── SecurityHeadersSubscriber.php
doc/
└── db.md                           # Database schema reference (used as AI context)
docker/
├── docker-compose.yml              # MySQL 8 service
└── mysql/.env                      # MySQL credentials
templates/
├── home/index.html.twig
├── doc_chat/
│   ├── upload.html.twig
│   └── index.html.twig
├── file_parser/index.html.twig
├── sql/index.html.twig
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

Model and options are configured in `config/packages/ai.yaml`:

```yaml
ai:
    agent:
        default:
            platform: 'ai.platform.anthropic'
            model:
                name: !php/const Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_4
                options:
                    max_tokens: 8096
```

`max_tokens` is set to **8096** explicitly because the bundle default (1000) is too low for structured JSON responses over multi-page PDFs — the model output gets truncated mid-JSON. 8096 matches Claude Sonnet's output token ceiling.

To switch model, replace the constant with any value from `Symfony\AI\Platform\Bridge\Anthropic\Claude`.

---

## Testing

```bash
php bin/phpunit
```

> **Before running tests, verify that `DATABASE_URL` does not point to a `_staging` or `_prod` schema.**

---

## License

Proprietary — all rights reserved.
