# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Context

**SymfonyAI** is an AI-powered technical support chatbot. Users upload Markdown documentation files, ask questions about them, and can escalate to a human support team via email. The project is part of the Eleva Backoffice ecosystem at `/Users/grazianorossini/Documents/Eleva/Backoffice/`.

## Tech Stack

- PHP 8.2+ / Symfony 7.4
- Doctrine ORM (PostgreSQL) — no entities defined yet, session-based storage
- Twig templates with Symfony UX Turbo (Hotwire)
- Symfony AI Bundle (`symfony/ai-bundle`) with Anthropic platform bridge
- Claude Sonnet 4 model (`Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_4`)
- Italian translations (`translations/messages.it.yaml`)

## Common Commands

```bash
composer install
php bin/console cache:clear
php bin/phpunit                        # run all tests
php bin/phpunit tests/path/to/Test.php # run single test
# No migrations yet — Entity/ and Repository/ are empty
```

## Architecture

This project does NOT use the Eleva Backoffice base classes. It is a standalone Symfony 7.4 app:

- **Controllers** use standard Symfony `AbstractController`
- **Services** will live in `src/Service/` — register in `config/services.yaml`
- **Entities** go in `src/Entity/` — none defined yet
- **Repositories** go in `src/Repository/` — none defined yet
- **Templates** use Twig in `templates/` (base.html.twig, chat/, email/, home/)
- **Translations** are Italian: `translations/messages.it.yaml`

## Existing Controllers

### `ChatController` (`src/Controller/ChatController.php`)

| Route | Method | Name |
|---|---|---|
| `/chat` | GET | `chat_index` |
| `/chat/message` | POST | `chat_message` |
| `/chat/send-email` | POST | `chat_send_email` |

- `message()` — processes user questions, calls the Symfony AI Agent
- `sendEmail()` — sends support email with chat transcript
- Injects: `AgentInterface`, `TranslatorInterface`, `MailerInterface`
- Gets `$supportEmail` and `$fromEmail` from `services.yaml` via env vars

### `HomeController` (`src/Controller/HomeController.php`)

| Route | Method | Name |
|---|---|---|
| `/` | GET | `home` |
| `/upload` | POST | `upload` |

- `upload()` — validates and stores uploaded Markdown content in session

## AI Integration

Configured via Symfony AI Bundle — no custom SDK calls needed.

```yaml
# config/packages/ai.yaml
ai:
    agent:
        default:
            platform: 'ai.platform.anthropic'
            model: !php/const Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_4

# config/packages/ai_anthropic_platform.yaml
ai:
    platform:
        anthropic:
            api_key: '%env(ANTHROPIC_API_KEY)%'
```

Inject `Symfony\AI\Agent\AgentInterface` in controllers/services — autowiring handles it.

## Environment Variables

| Variable | Purpose |
|---|---|
| `APP_ENV` | Application environment |
| `APP_SECRET` | Symfony secret |
| `DATABASE_URL` | PostgreSQL DSN |
| `MESSENGER_TRANSPORT_DSN` | Messenger transport |
| `MAILER_DSN` | Mailer transport |
| `ANTHROPIC_API_KEY` | Anthropic API key |
| `SUPPORT_EMAIL` | Recipient for escalation emails |
| `FROM_EMAIL` | Sender address for outgoing emails |