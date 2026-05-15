# SymfonyAI

A sandbox project for testing and evaluating the [Symfony AI Bundle](https://symfony.com/doc/current/ai.html) (`symfony/ai-bundle`), the official Symfony component for integrating AI models into Symfony applications.

The bundle is exercised through a concrete use case: a chatbot that answers questions about uploaded Markdown documentation and can escalate conversations to a support team via email. The app is intentionally minimal — no database entities, no business logic — to keep the focus on how the AI layer works.

## Tech Stack

- **PHP** 8.2+ / **Symfony** 7.4
- **Symfony AI Bundle** (`symfony/ai-bundle`) with Anthropic platform bridge
- **Claude Sonnet 4** as the AI model
- **Symfony UX Turbo** (Hotwire) for reactive UI
- **Twig** templates

## Requirements

- PHP 8.2+
- Composer
- PostgreSQL 16+
- An [Anthropic API key](https://console.anthropic.com/)

## Installation

```bash
git clone <repository-url>
cd SymfonyAI
composer install
```

Copy the environment template and fill in your values:

```bash
cp .env .env.local
```

Edit `.env.local`:

```dotenv
APP_SECRET=your-secret-here
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/symfonyai?serverVersion=16&charset=utf8"
ANTHROPIC_API_KEY=sk-ant-...
SUPPORT_EMAIL=support@yourdomain.com
FROM_EMAIL=noreply@yourdomain.com
```

## Running locally

```bash
symfony server:start
```

Or with the built-in PHP server:

```bash
php -S localhost:8000 -t public/
```

## Usage

1. Open the home page and upload a Markdown (`.md`) file containing your documentation.
2. Go to the chat page and ask questions — the AI answers based on the uploaded content.
3. Use the **Send to support** button to escalate the conversation via email.

## Project Structure

```
src/
├── Controller/
│   ├── HomeController.php   # Upload page
│   └── ChatController.php   # Chat interface and AI interaction
config/
├── packages/
│   ├── ai.yaml                      # AI agent configuration (model)
│   └── ai_anthropic_platform.yaml   # Anthropic API key binding
templates/
├── home/index.html.twig
├── chat/index.html.twig
└── email/support_request.html.twig
translations/
└── messages.it.yaml   # Italian translations
```

## Configuration

### AI model

The model is configured in `config/packages/ai.yaml`:

```yaml
ai:
    agent:
        default:
            platform: 'ai.platform.anthropic'
            model: !php/const Symfony\AI\Platform\Bridge\Anthropic\Claude::SONNET_4
```

To switch model, replace the constant with any value from `Symfony\AI\Platform\Bridge\Anthropic\Claude`.

### Email

Configure `MAILER_DSN` in `.env.local` for your SMTP provider. The default `null://null` discards all emails (useful for development).

## Testing

```bash
php bin/phpunit
```

## License

Proprietary — all rights reserved.
