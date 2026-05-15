# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Context

**SymfonyAI** is a sandbox project for testing the [Symfony AI Bundle](https://symfony.com/doc/current/ai.html). Each feature exercises a different capability of the bundle through a concrete use case. Part of the Eleva Backoffice ecosystem at `/Users/grazianorossini/Documents/Eleva/Backoffice/`.

## Tech Stack

- PHP 8.2+ / Symfony 7.4
- Symfony AI Bundle (`symfony/ai-bundle`) with Anthropic platform bridge
- Claude Sonnet 4 model (`Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_4`)
- Symfony UX Turbo (Hotwire) for reactive UI
- Twig templates
- Italian translations (`translations/messages.it.yaml`)
- No database entities — session-based storage

## Common Commands

```bash
composer install
php bin/console cache:clear
php bin/phpunit
php bin/phpunit tests/path/to/Test.php
```

## Architecture

Standalone Symfony 7.4 app — no Eleva base classes. Standard `AbstractController`.

Features are organized as independent vertical slices:

```
src/
├── Controller/
│   ├── HomeController.php          # Feature selection landing page (/)
│   ├── DocChatController.php       # /doc-chat — upload + chat + email escalation
│   ├── FileParserController.php    # /file-parser — placeholder
│   └── DqlController.php           # /dql — placeholder
├── Service/
│   └── DocChat/
│       ├── ChatService.php         # AI prompt, agent call, tag detection
│       └── SupportEmailService.php # Email assembly, transcript, history sanitisation
└── EventSubscriber/
    └── SecurityHeadersSubscriber.php
templates/
├── home/index.html.twig
├── doc_chat/upload.html.twig
├── doc_chat/index.html.twig
├── file_parser/index.html.twig
├── dql/index.html.twig
└── email/support_request.html.twig
```

When adding a new feature, follow the same pattern: one controller, one `Service/<FeatureName>/` directory, one `templates/<feature_name>/` directory.

## Routes (DocChat)

| Route | Method | Name |
|---|---|---|
| `/doc-chat` | GET | `doc_chat` |
| `/doc-chat/upload` | POST | `doc_chat_upload` |
| `/doc-chat/chat` | GET | `doc_chat_chat` |
| `/doc-chat/message` | POST | `doc_chat_message` |
| `/doc-chat/send-email` | POST | `doc_chat_send_email` |

## AI Integration

Inject `Symfony\AI\Agent\AgentInterface` — autowiring handles it. Service classes in `src/Service/<Feature>/` own all AI logic (prompt, call, response parsing). Controllers only validate HTTP input and delegate.

```yaml
# config/packages/ai.yaml
ai:
    agent:
        default:
            platform: 'ai.platform.anthropic'
            model: !php/const Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_4
```

## Environment Variables

| Variable | Purpose |
|---|---|
| `APP_SECRET` | Symfony secret |
| `ANTHROPIC_API_KEY` | Anthropic API key |
| `SUPPORT_EMAIL` | Recipient for escalation emails |
| `FROM_EMAIL` | Sender address for outgoing emails |
| `MAILER_DSN` | Mailer transport (`null://null` in dev) |
| `DATABASE_URL` | PostgreSQL DSN (not yet used) |
