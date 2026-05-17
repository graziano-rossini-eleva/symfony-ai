# SymfonyAI — Documentazione del Progetto

## Panoramica

**SymfonyAI** è un'applicazione sandbox in Symfony 7.4 per testare le funzionalità del [Symfony AI Bundle](https://symfony.com/doc/current/ai.html). Ogni funzione esercita una diversa capacità del bundle attraverso un caso d'uso concreto. Fa parte dell'ecosistema Eleva Backoffice.

### Stack tecnologico

- **PHP 8.2+** / **Symfony 7.4**
- **Symfony AI Bundle** (`symfony/ai-bundle ^0.8`) con bridge Anthropic
- **Modello AI:** Claude Sonnet 4 (`Claude::SONNET_4`)
- **Database:** MySQL 8 (Doctrine ORM + Migrations)
- **UI:** Twig + Symfony UX Turbo (Hotwire) + Asset Mapper
- **Email:** Symfony Mailer
- **Lingua predefinita:** Italiano (`it`)
- **Storage dati utente:** sessione (nessuna entity Doctrine in uso)

---

## Funzionalità

Il progetto espone quattro funzioni AI indipendenti, accessibili dalla home page (`/`).

### 1. Doc Chat (`/doc-chat`)

Permette di caricare un file Markdown (`.md`) come documento di contesto e poi fare domande in linguaggio naturale. Il sistema risponde basandosi esclusivamente sul contenuto del documento caricato. Se l'utente ha bisogno di assistenza umana, può richiedere una escalation via email: il sistema invia automaticamente un'email di supporto con la trascrizione della conversazione allegata.

**Flusso d'uso:**
1. L'utente carica un file `.md` (max 512 KB).
2. Il file viene salvato in sessione come contesto.
3. L'utente pone domande nella chat.
4. Il sistema risponde usando il documento come base di conoscenza.
5. Se appropriato, il sistema offre l'escalation email; l'utente compila nome, email e nome progetto.

**Limiti:**
- File: solo `.md`, max 512 KB.
- Domande: max 2000 caratteri.

**Route:**

| Metodo | URL | Nome | Scopo |
|--------|-----|------|-------|
| GET | `/doc-chat` | `doc_chat` | Form caricamento file |
| POST | `/doc-chat/upload` | `doc_chat_upload` | Elabora il file caricato |
| GET | `/doc-chat/chat` | `doc_chat_chat` | Interfaccia chat |
| POST | `/doc-chat/message` | `doc_chat_message` | Invia messaggio e ottieni risposta AI |
| POST | `/doc-chat/send-email` | `doc_chat_send_email` | Invia email di escalation |

---

### 2. File Parser (`/file-parser`)

Estrae dati strutturati in formato JSON da file PDF. L'utente carica un PDF e descrive cosa estrarre tramite un prompt testuale. Il sistema restituisce un oggetto JSON con i dati estratti.

**Flusso d'uso:**
1. L'utente carica un file PDF (max 10 MB).
2. L'utente scrive un'istruzione di estrazione (es. "Estrai nome, cognome, data di nascita").
3. Il sistema analizza il PDF con AI e restituisce il JSON risultante.

**Limiti:**
- File: solo PDF, max 10 MB.
- Prompt: max 1000 caratteri.
- Output: sempre JSON valido (l'AI è vincolata a restituire solo JSON).

**Route:**

| Metodo | URL | Nome | Scopo |
|--------|-----|------|-------|
| GET | `/file-parser` | `file_parser` | Form caricamento PDF |
| POST | `/file-parser/parse` | `file_parser_parse` | Estrae JSON dal PDF |

---

### 3. SQL Assistant (`/sql`)

Permette di interrogare il database in linguaggio naturale. L'utente descrive cosa vuole sapere; il sistema genera automaticamente una query SQL, la esegue e mostra i risultati in una tabella paginata.

**Flusso d'uso:**
1. L'utente scrive una domanda in italiano (es. "Quanti utenti si sono iscritti questo mese?").
2. Il sistema genera una query SELECT.
3. La query viene eseguita sul database.
4. I risultati sono mostrati in tabella con paginazione client-side.

**Sicurezza:**
- Solo query SELECT sono ammesse (validazione regex pre-esecuzione).
- L'AI non può eseguire INSERT, UPDATE, DELETE, DROP o altri comandi DDL/DML.
- I record soft-deleted (colonna `deleted = 0`) sono filtrati automaticamente.
- Lo schema del database è fornito all'AI come contesto (da `doc/db.md`).

**Limiti:**
- Prompt: max 1000 caratteri.

**Route:**

| Metodo | URL | Nome | Scopo |
|--------|-----|------|-------|
| GET | `/sql` | `sql` | Form prompt |
| POST | `/sql/query` | `sql_query` | Genera SQL, esegue, restituisce risultati (JSON) |

---

### 4. Advisor AI (`/advisor`)

Un agente AI multi-step autonomo specializzato nell'analisi dei dati della piattaforma corsi. L'utente pone una domanda complessa; l'agente interroga autonomamente il database più volte (tramite tool calls) e sintetizza una risposta ragionata in linguaggio naturale.

**Flusso d'uso:**
1. L'utente scrive una domanda analitica (es. "Quali sono i corsi con il tasso di completamento più alto tra gli utenti registrati nell'ultimo trimestre?").
2. L'agente decide autonomamente quali query eseguire.
3. Il tool `query_database` viene invocato più volte se necessario.
4. L'agente sintetizza i risultati e risponde in italiano.

**Differenze rispetto a SQL Assistant:**
- L'utente non vede la query SQL generata.
- L'agente può fare più query in sequenza e ragionare sui risultati.
- Adatto a domande complesse che richiedono più passaggi logici.

**Limiti:**
- Domanda: max 1000 caratteri.

**Route:**

| Metodo | URL | Nome | Scopo |
|--------|-----|------|-------|
| GET | `/advisor` | `advisor` | Form domanda |
| POST | `/advisor/ask` | `advisor_ask` | Risposta agente multi-step (JSON) |

---

## Architettura

### Struttura del progetto

Il progetto segue un'organizzazione a **vertical slices**: ogni funzione è autocontenuta in un controller, una directory `Service/<NomeFunzione>/` e una directory `templates/<nome_funzione>/`.

```
src/
├── Controller/
│   ├── HomeController.php
│   ├── DocChatController.php
│   ├── FileParserController.php
│   ├── SqlController.php
│   └── AdvisorController.php
├── Service/
│   ├── DocChat/
│   │   ├── ChatService.php
│   │   └── SupportEmailService.php
│   ├── FileParser/
│   │   └── FileParserService.php
│   ├── Sql/
│   │   └── SqlService.php
│   └── Advisor/
│       └── AdvisorService.php
├── Tool/
│   └── DatabaseQueryTool.php
└── EventSubscriber/
    ├── AiExceptionSubscriber.php
    └── SecurityHeadersSubscriber.php
templates/
├── home/
├── doc_chat/
├── file_parser/
├── sql/
├── advisor/
└── email/
```

### Agenti AI configurati

Sono configurati due agenti distinti in `config/packages/ai.yaml`:

| Agente | Servizio/i | Tool | Note |
|--------|-----------|------|------|
| `default` | DocChat, FileParser, Sql | Nessuno | Agente base |
| `advisor` | Advisor | `DatabaseQueryTool` | Autonomo, multi-step |

**`default`** è iniettato come `AgentInterface` standard. **`advisor`** ha il tool `query_database` registrato, che gli permette di interrogare il database in autonomia.

```yaml
# config/packages/ai.yaml
ai:
  agent:
    default:
      platform: 'ai.platform.anthropic'
      model:
        name: Claude::SONNET_4
        options:
          max_tokens: 8096
    advisor:
      platform: 'ai.platform.anthropic'
      model:
        name: Claude::SONNET_4
        options:
          max_tokens: 8096
      tools:
        - App\Tool\DatabaseQueryTool
```

### Tool: DatabaseQueryTool

Il tool `query_database` è registrato solo nell'agente `advisor`. Riceve una domanda in linguaggio naturale, la passa a `SqlService` (che genera ed esegue la query), e restituisce un testo formattato con i risultati tabulari (max 100 righe). In caso di errore, restituisce un messaggio di errore invece di sollevare un'eccezione, così l'agente può gestire il fallimento in modo autonomo.

---

## Servizi principali

### ChatService

**File:** `src/Service/DocChat/ChatService.php`

Gestisce le richieste AI per la chat documentazione. Il prompt di sistema configura l'AI come assistente di supporto in italiano. Il sistema rileva quando l'utente ha bisogno di supporto umano tramite il tag `[SUPPORTO_EMAIL]` nella risposta: se presente, il tag viene rimosso dalla risposta visibile e viene impostato il flag `offer_email: true` per mostrare il form di escalation.

**Metodo principale:** `ask(string $docContext, string $question): array`
- Input: contenuto del documento e domanda dell'utente
- Output: `{reply: string, offer_email: bool}`

### SupportEmailService

**File:** `src/Service/DocChat/SupportEmailService.php`

Assembla e invia l'email di escalation. Sanitizza la cronologia chat (max 100 messaggi, max 2000 caratteri ciascuno), costruisce un transcript in testo semplice, renderizza il template HTML e allega il transcript come file `.txt`.

**Metodo principale:** `send(string $name, string $userEmail, string $projectName, array $rawHistory): void`

### FileParserService

**File:** `src/Service/FileParser/FileParserService.php`

Estrae dati da PDF tramite AI. Il prompt utente viene sanitizzato (rimozione di null bytes e caratteri di controllo) e delimitato con tag `[USER_INSTRUCTION_START]` per prevenire prompt injection. L'output AI viene normalizzato: vengono rimossi i markdown fences, estratto il blocco JSON e rimossi eventuali trailing commas. Se l'output non è JSON valido viene sollevata una `RuntimeException`.

**Metodo principale:** `extract(string $filePath, string $prompt): array`

### SqlService

**File:** `src/Service/Sql/SqlService.php`

Genera ed esegue query SQL. Carica lo schema dal file `doc/db.md` e lo inietta nel prompt di sistema. Prima dell'esecuzione valida che la query generata sia un SELECT (regex `^\s*SELECT\b`). Restituisce un array strutturato con `{sql, columns, rows, total}`.

**Metodo principale:** `query(string $prompt): array`

### AdvisorService

**File:** `src/Service/Advisor/AdvisorService.php`

Delega completamente all'agente `advisor` configurato con il tool `DatabaseQueryTool`. Il prompt di sistema specifica che l'agente deve usare il tool più volte se necessario, analizzare i risultati e non inventare dati.

**Metodo principale:** `ask(string $question): string`

---

## Sicurezza

### Misure implementate

| Area | Misura |
|------|--------|
| Form | CSRF token su tutti i form POST |
| Upload file | Validazione MIME type + dimensione massima |
| SQL | Regex pre-esecuzione, solo SELECT ammessi |
| Prompt | Sanitizzazione null bytes e caratteri di controllo |
| Prompt injection | Delimitatori `[USER_INSTRUCTION_START]` in FileParser |
| Email | Sanitizzazione cronologia (allowlist ruoli, cap per messaggio) |
| Risposta HTTP | Security headers su ogni risposta |
| Rate limit | Gestione `RateLimitExceededException` → HTTP 429 |

### Security Headers (SecurityHeadersSubscriber)

Applicati a tutte le risposte (solo main request):

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()`
- `Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:`
- `Strict-Transport-Security` (solo su HTTPS)

---

## Configurazione ambiente

### Variabili d'ambiente

| Variabile | Scopo | Esempio |
|-----------|-------|---------|
| `APP_SECRET` | Symfony secret | stringa casuale |
| `ANTHROPIC_API_KEY` | Chiave API Anthropic | `sk-ant-...` |
| `SUPPORT_EMAIL` | Destinatario email escalation | `support@example.com` |
| `FROM_EMAIL` | Mittente email in uscita | `noreply@example.com` |
| `MAILER_DSN` | Trasporto mailer | `null://null` in dev |
| `DATABASE_URL` | DSN PostgreSQL/MySQL | `mysql://app:app@127.0.0.1:3306/symfony_ai` |

### Comandi principali

```bash
# Installazione dipendenze
composer install

# Pulizia cache
php bin/console cache:clear

# Esecuzione test
php bin/phpunit

# Caricamento fixtures (database dev)
php bin/console doctrine:fixtures:load

# Avvio ambiente Docker
./cmd/start.sh
```

---

## Database

Il progetto usa MySQL 8 tramite Doctrine DBAL. Lo schema completo è documentato in `doc/db.md` ed è usato come contesto nei prompt di sistema per SQL Assistant e Advisor AI.

Le fixtures (FakerPHP) generano oltre 100 record di dati realistici per testare le funzionalità di interrogazione. Prima di caricare le fixtures, verificare sempre che `DATABASE_URL` non punti a un database `_staging` o `_prod`.

---

## Traduzioni

Tutte le stringhe visibili all'utente sono tradotte. Il file di traduzione attivo è:

- `translations/messages.it.yaml` — Italiano (locale predefinita)

Le chiavi sono organizzate per feature con dot-notation (es. `doc_chat.error.invalid_file`, `advisor.ask`, `sql.results.title`). Le eccezioni PHP e i messaggi di log non richiedono traduzione.

---

## Come aggiungere una nuova funzione

1. Creare `src/Controller/<NomeFunzione>Controller.php` con le route necessarie.
2. Creare `src/Service/<NomeFunzione>/<NomeFunzione>Service.php` con la logica AI.
3. Creare `templates/<nome_funzione>/index.html.twig`.
4. Aggiungere le chiavi di traduzione in `translations/messages.it.yaml`.
5. Aggiungere la card nella home page (`templates/home/index.html.twig`).
6. Se serve un agente con tool dedicati, aggiungere la configurazione in `config/packages/ai.yaml`.
