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

## Mandatory Workflows

### Doctrine Entities — PHPDoc

**IMPORTANT: After creating or modifying any file in `src/Entity/`, you MUST immediately invoke the `symfony-phpdoc` agent on the changed files before committing.**

This applies to:
- New entity classes
- Added or changed properties
- Added or changed methods (getters, setters, lifecycle callbacks, domain methods)

Every element **must** have a PHPDoc block — no exceptions:

| Element | Required tags |
|---|---|
| Class | Description, `@package` |
| Property | `@var` with type + description |
| Method / function | Description, `@param` (all params), `@return`, `@throws` if applicable |
| Constructor | Description of what it initialises |
| Lifecycle callback | Description of when it runs and what it sets |

Rules:
- All PHPDoc blocks and inline code comments must be written in **English**.
- A description is required even when the method name seems self-explanatory — document *behaviour*, not just the name.
- `@param` and `@return` must always include a short inline description, not just the type.
- `@throws` is mandatory whenever an exception can be raised inside the method.
- Do not skip this step even for trivial changes or single-line methods.

### Translations

**IMPORTANT: Every user-facing string must be translated for all languages available in the project.**

Currently active translation files:
- `translations/messages.it.yaml` — Italian (default locale)

Rules:
- Never hardcode a user-facing string in a Twig template, controller, or service. Always use the `trans()` filter/function or the `TranslatorInterface`.
- Every new key added to one translation file must be added to **all** other translation files in the same commit.
- Use dot-notation keys grouped by feature (e.g. `course.title`, `enrollment.completed_at`).
- When a new language file is added to `translations/`, back-fill all existing keys immediately.
- PHP exceptions and log messages do not need translation. Only strings visible to end users do.

### Doctrine Migrations

**IMPORTANT: Every migration file must have a human-readable description.**

Override the `getDescription()` method in every generated migration class:

```php
public function getDescription(): string
{
    return 'Short description of what this migration does';
}
```

Rules:
- The description must explain *what* changes (e.g. `"Create user and role tables"`, `"Add status column to orders"`).
- Write descriptions in **English**.
- Never leave the default empty string returned by the parent.

### Fixtures and Tests

**IMPORTANT: Before running fixtures (`doctrine:fixtures:load`) or tests (`bin/phpunit`), always check that the target database schema does not contain `_staging` or `_prod` in its name.**

Read the `DATABASE_URL` value from `.env` or `.env.local` and extract the database name. If the name contains `_staging` or `_prod`, stop and ask the user for explicit confirmation before proceeding.

```bash
# Example check
grep DATABASE_URL .env .env.local 2>/dev/null
```

Never run destructive operations (fixtures purge the entire database) against staging or production schemas.

### Commits

**IMPORTANT: Every commit must follow Conventional Commits standards and be kept small and focused.**

Format: `<type>(<scope>): <short description>`

Common types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `style`

Rules:
- Each commit must touch only files that belong to a single logical unit of work (e.g. one feature, one bug fix, one refactor). Do not bundle unrelated changes.
- If staged files span multiple unrelated concerns, split them into separate commits.
- Never commit more files than can be described accurately in a single subject line.
- **Always show the proposed commit message to the user and wait for explicit confirmation before running `git commit`.**
- The subject line must be concise (≤ 72 chars), imperative mood, no trailing period.
- A body is optional but recommended when the "why" is not obvious from the subject.

## Environment Variables

| Variable | Purpose |
|---|---|
| `APP_SECRET` | Symfony secret |
| `ANTHROPIC_API_KEY` | Anthropic API key |
| `SUPPORT_EMAIL` | Recipient for escalation emails |
| `FROM_EMAIL` | Sender address for outgoing emails |
| `MAILER_DSN` | Mailer transport (`null://null` in dev) |
| `DATABASE_URL` | PostgreSQL DSN (not yet used) |
