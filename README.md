# SymfonyAI

A sandbox project for testing and evaluating the [Symfony AI Bundle](https://symfony.com/doc/current/ai.html) (`symfony/ai-bundle`), the official Symfony component for integrating AI models into Symfony applications.

Each feature in this project exercises a different capability of the bundle through a concrete use case.

## Features

| Feature | Status | Description |
|---|---|---|
| **Doc Chat** | Available | Upload a Markdown file and chat with an AI assistant about its content. Supports email escalation to a human support team. |
| **File Parser** | Available | Upload a PDF and describe what to extract in plain language. The AI returns a structured JSON object. |
| **DQL Assistant** | Coming soon | Query the database in plain language; the AI generates safe read-only DQL queries. |

## Tech Stack

- **PHP** 8.2+ / **Symfony** 7.4
- **Symfony AI Bundle** (`symfony/ai-bundle`) with Anthropic platform bridge
- **Claude Sonnet 4** as the AI model
- **Symfony UX Turbo** (Hotwire) for reactive UI
- **Twig** templates

## Project Structure

```
src/
├── Controller/
│   ├── HomeController.php          # Feature selection landing page
│   ├── DocChatController.php       # Doc Chat: upload + chat + email escalation
│   ├── FileParserController.php    # File Parser: upload + extract
│   └── DqlController.php           # DQL Assistant (placeholder)
├── Service/
│   ├── DocChat/
│   │   ├── ChatService.php         # AI prompt, agent call, tag detection
│   │   └── SupportEmailService.php # Email assembly, transcript, history sanitisation
│   └── FileParser/
│       └── FileParserService.php   # PDF read, prompt injection mitigation, JSON normalisation
└── EventSubscriber/
    └── SecurityHeadersSubscriber.php
templates/
├── home/index.html.twig            # Feature selection cards
├── doc_chat/
│   ├── upload.html.twig
│   └── index.html.twig
├── file_parser/index.html.twig
├── dql/index.html.twig
└── email/support_request.html.twig
translations/
└── messages.it.yaml
```

## Requirements

- PHP 8.2+
- Composer
- An [Anthropic API key](https://console.anthropic.com/)

## Installation

```bash
git clone <repository-url>
cd SymfonyAI
composer install
cp .env.dist .env.local
```

Edit `.env.local` with your values:

```dotenv
APP_SECRET=your-secret-here
ANTHROPIC_API_KEY=sk-ant-...
SUPPORT_EMAIL=support@yourdomain.com
FROM_EMAIL=noreply@yourdomain.com
```

## Running locally

```bash
symfony server:start
```

## AI Configuration

Model and options configured in `config/packages/ai.yaml`:

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

`max_tokens` is set to **8096** explicitly because the bundle default is 1000, which is too low for structured JSON responses over multi-page PDFs. With the default limit the model's output gets truncated mid-JSON, producing an unparseable response. 8096 matches Claude Sonnet's output token ceiling and ensures complete responses even for documents with many fields.

To switch model, replace the constant with any value from `Symfony\AI\Platform\Bridge\Anthropic\Claude`.

## Testing

```bash
php bin/phpunit
```

## License

Proprietary — all rights reserved.
