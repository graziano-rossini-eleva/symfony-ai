# SymfonyAI — Documentazione del Progetto

## Architettura — schema visuale

![Architecture & Call Flow](../architecture.png)

## Panoramica

**SymfonyAI** è un'applicazione sandbox in Symfony 7.4 per testare le funzionalità del [Symfony AI Bundle](https://symfony.com/doc/current/ai.html). Ogni funzione esercita una diversa capacità del bundle attraverso un caso d'uso concreto. Fa parte dell'ecosistema Eleva Backoffice.

### Stack tecnologico

- **PHP 8.2+** / **Symfony 7.4**
- **Symfony AI Bundle** (`symfony/ai-bundle ^0.8`) con bridge Anthropic
- **Symfony MCP Bundle** (`symfony/mcp-bundle ^0.9`) — server MCP per Claude Desktop
- **Symfony AI Store** (`symfony/ai-store ^0.8`, `symfony/ai-sqlite-store ^0.8`) — RAG pipeline
- **Symfony AI Ollama Platform** (`symfony/ai-ollama-platform ^0.8`) — embedding locali
- **Modello AI:** Claude Sonnet 4 (`Claude::SONNET_4`) — via Anthropic API
- **Embedding:** `nomic-embed-text` via Ollama (Docker, nessuna API key richiesta)
- **Database:** MySQL 8 (Doctrine ORM + Migrations)
- **Vector store:** SQLite (`var/code_store.db`)
- **UI:** Twig + Symfony UX Turbo (Hotwire) + Asset Mapper
- **Email:** Symfony Mailer
- **Lingua predefinita:** Italiano (`it`)
- **Storage dati utente:** sessione (nessuna entity Doctrine in uso)

---

## Funzionalità

Il progetto espone sei funzioni AI indipendenti, accessibili dalla home page (`/`).

### 1. Doc Chat (`/doc-chat`)

Permette di caricare un file Markdown (`.md`) come documento di contesto e poi fare domande in linguaggio naturale. Il sistema risponde basandosi esclusivamente sul contenuto del documento caricato. Se l'utente ha bisogno di assistenza umana, può richiedere una escalation via email: il sistema invia automaticamente un'email di supporto con la trascrizione della conversazione allegata.

**Flusso d'uso:**
1. L'utente carica un file `.md` (max 512 KB) — **opzionale**.
2. Se nessun file viene caricato, il sistema usa `doc/project.md` come contesto predefinito.
3. Il file viene salvato in sessione come contesto.
4. L'utente pone domande nella chat.
5. Il sistema risponde usando il documento come base di conoscenza.
6. Se appropriato, il sistema offre l'escalation email; l'utente compila nome, email e nome progetto.

**Limiti:**
- File: solo `.md`, max 512 KB (opzionale — fallback su `doc/project.md`).
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

Un agente AI multi-step autonomo specializzato nell'analisi dei dati della piattaforma corsi. L'utente pone una domanda complessa; l'agente scrive autonomamente query SQL, interroga il database più volte tramite il tool `execute_sql` e sintetizza una risposta ragionata in linguaggio naturale.

**Flusso d'uso:**
1. L'utente scrive una domanda analitica (es. "Quali sono i corsi con il tasso di completamento più alto?").
2. L'agente decide autonomamente quali query SQL eseguire (schema nel system prompt).
3. Il tool `execute_sql` viene invocato più volte se necessario.
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

### 5. Report Analitico (`/report`)

Genera report completi scaricabili in formato Markdown, orchestrando tre tool in sequenza. La data corrente è iniettata direttamente nel system prompt da PHP. L'agente interroga il database, calcola statistiche precise e salva il report su disco; il browser riceve un link per il download.

**Pipeline: retrieval → computation → output**

| Fase | Tool | Categoria | Scopo |
|------|------|-----------|-------|
| 1 | `execute_sql` | Retrieval | Esegue query SQL (max 3) scritte dall'agente |
| 2 | `calculate_statistics` | Computation | Calcola avg/median/min/max/stddev con precisione PHP |
| 3 | `save_report` | Output | Salva il Markdown in `var/reports/`, restituisce token download |

**Perché `calculate_statistics` come tool separato:** l'LLM è un generatore di testo probabilistico, non un calcolatore. Statistiche floating-point su dataset reali richiedono un tool dedicato per risultati precisi e riproducibili.

**Flusso d'uso:**
1. L'utente descrive il report desiderato.
2. L'agente esegue le query necessarie (max 3).
3. Calcola le statistiche sui dati numerici.
4. Salva il report Markdown e restituisce una sintesi + link download.

**Limiti:**
- Prompt: max 1000 caratteri.
- Query SQL: max 3 per report.

**Route:**

| Metodo | URL | Nome | Scopo |
|--------|-----|------|-------|
| GET | `/report` | `report` | Form prompt |
| POST | `/report/generate` | `report_generate` | Esegue pipeline e restituisce sintesi + token (JSON) |
| GET | `/report/download/{token}` | `report_download` | Scarica il file Markdown generato |

---

### 6. Code Chat (`/code-chat`)

Permette di fare domande in linguaggio naturale sul codice sorgente del progetto. Usa una pipeline RAG (Retrieval-Augmented Generation) basata su `symfony/ai-store`: i file del progetto vengono indicizzati in un vector store SQLite tramite embedding generati localmente da Ollama. A ogni domanda, vengono recuperati i chunk più rilevanti tramite ricerca di similarità coseno e passati come contesto a Claude.

**Flusso d'uso:**
1. (Setup, una-tantum) `./cmd/start.sh` indicizza automaticamente il codebase.
2. L'utente pone una domanda sul codice (es. "Come funziona il ChatService?").
3. La domanda viene vettorizzata da `nomic-embed-text` (Ollama).
4. Il retriever recupera i top-8 chunk più simili dal vector store SQLite.
5. Claude risponde citando i file sorgente rilevanti.

**Componenti coinvolti:**
- `src/Command/IndexCodebaseCommand.php` — scansiona `src/`, `templates/`, `config/`, `doc/` e indicizza i chunk nel vector store
- `src/Service/CodeChat/CodeChatService.php` — esegue retrieve + costruisce il context + chiama Claude
- `src/Controller/CodeChatController.php` — route `/code-chat`
- `config/packages/ai.yaml` — agent `code_chat`, store `sqlite.code`, vectorizer `code`, indexer `code`, retriever `code`
- `config/packages/ai_ollama_platform.yaml` — endpoint Ollama

**Re-indicizzazione dopo modifiche al codice:**
```bash
php bin/console app:index-codebase --truncate
# oppure:
./cmd/start.sh --index
```

**Scope dell'indice:**

Solo un sottoinsieme mirato del progetto viene indicizzato — non l'intera codebase. Le cartelle incluse sono `src/`, `doc/`, `templates/`, `translations/` e il file `README.md`.

Motivazione: indicizzare tutta la repo (inclusi `config/`, `vendor/`, `var/`, `public/`) aggiunge rumore ai risultati RAG senza migliorare la qualità delle risposte. Le configurazioni YAML e i file generati non contengono logica applicativa utile al contesto; i vendor sono codice di terze parti non rilevante per domande sul progetto. Mantenere lo scope stretto riduce anche i tempi di indicizzazione e il numero di round-trip verso Ollama.

**Dettagli implementativi del command:**

- `chunk_size=1` — i documenti vengono inviati a Ollama uno alla volta invece che in batch. Questo evita un conflitto noto con il `TraceableResponse` del client HTTP di Symfony in modalità dev, che causa un errore `AsyncResponse already consumed` quando più richieste parallele condividono la stessa istanza decorata.
- `sanitizeUtf8()` — ogni file viene passato attraverso `mb_convert_encoding(..., 'UTF-8', 'UTF-8')` prima del chunking. Alcuni file (es. template Twig con caratteri speciali o file YAML con encoding misto) contengono byte non validi che causano un errore `Malformed UTF-8 characters` al momento della serializzazione JSON verso l'API Ollama.
- `drop()` + `setup()` per il truncate — `ManagedStoreInterface` non espone un metodo `clear()`; il re-index completo richiede di eliminare la tabella (`drop()`) e ricrearla (`setup()`).

**Limiti:**
- Domanda: max 2000 caratteri.
- Chunk recuperati per query: max 8.
- Il vector store deve essere inizializzato prima dell'uso (`ai:store:setup ai.store.sqlite.code`).

**Route:**

| Metodo | URL | Nome | Scopo |
|--------|-----|------|-------|
| GET | `/code-chat` | `code_chat` | Interfaccia chat |
| POST | `/code-chat/message` | `code_chat_message` | Risposta RAG (JSON) |

---

### 7. MCP Server (integrazione Claude Desktop)

Il server MCP espone i tool della piattaforma a qualsiasi client compatibile con il [Model Context Protocol](https://modelcontextprotocol.io/) (Claude Desktop, Cursor, ecc.). Non è una funzione utente — è un'integrazione per sviluppatori/assistenti AI.

**Tool esposti:**

| Tool | Attributo | Descrizione |
|------|-----------|-------------|
| `execute_sql` | `#[McpTool]` su `ExecuteSqlTool` | Esegue query SQL SELECT in sola lettura sul database della piattaforma |
| `calculate_statistics` | `#[McpTool]` su `CalculateStatisticsTool` | Calcola statistiche descrittive precise su array JSON di numeri |

**Transport supportati:**

| Transport | Comando / URL |
|-----------|--------------|
| stdio | `php bin/console mcp:server` |
| HTTP | `GET/POST /_mcp` |

**Configurazione:**
- `config/packages/mcp.yaml` — nome server, transport, endpoint HTTP, istruzioni con schema DB iniettato
- I tool sono taggati `mcp.tool` in `config/services.yaml` per il service locator DI

**Setup Claude Desktop:**
Dal browser aprire `GET /mcp-config` — scarica `claude_desktop_config.json` pre-compilato con il path assoluto di `bin/console`. Copiarlo in `~/Library/Application Support/Claude/` e riavviare Claude Desktop.

> Lo schema del database è iniettato nelle `instructions` del server MCP (`config/packages/mcp.yaml`), quindi Claude Desktop non interroga mai `information_schema`.

---

## Copertura del Symfony AI Bundle

### Componenti del bundle installati

| Componente | Pacchetto | Usato in questo progetto | Note |
|-----------|-----------|:------------------------:|------|
| **Platform Component** | `symfony/ai-anthropic-platform` | ✓ | Bridge Anthropic — accesso a Claude Sonnet 4 tramite interfaccia unificata |
| **Agent Component** | `symfony/ai-agent` | ✓ | `AgentInterface`, tool calling, agentic loop multi-step |
| **AI Bundle** | `symfony/ai-bundle` | ✓ | Integrazione Symfony: DI, autowiring agenti, `config/packages/ai.yaml` |
| **MCP Bundle** | `symfony/mcp-bundle` | ✓ | Server MCP attivo — espone `execute_sql` e `calculate_statistics` a Claude Desktop via stdio e HTTP (`/_mcp`) |
| **Store Component** | `symfony/ai-store` + `symfony/ai-sqlite-store` | ✓ | Vector store SQLite + indexer + retriever — pipeline RAG per Code Chat |
| **Ollama Platform** | `symfony/ai-ollama-platform` | ✓ | Embedding locali via `nomic-embed-text` (Docker, nessuna API key) |
| **Chat Component** | `symfony/ai-chat` | — | Non installato — la cronologia chat è gestita manualmente in sessione PHP |
| **Mate Component** | `symfony/ai-mate` | — | Non installato — serve per consumare MCP di server esterni; `mcp-bundle` copre il caso opposto |

> `ai-mate` sarebbe utile se la tua app Symfony dovesse orchestrare tool esposti da server MCP esterni (es. GitHub, Jira). In questo progetto Symfony è il server MCP, non il client.

---

### Funzionalità coperte per feature

La tabella mostra quali macro-funzionalità del bundle sono esercitate da ciascuna feature del progetto.

| Funzionalità del bundle | Doc Chat | File Parser | SQL Assistant | Advisor | Report | Code Chat |
|-------------------------|:--------:|:-----------:|:-------------:|:-------:|:------:|:---------:|
| `AgentInterface` — chiamata singola | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| System prompt (`SystemMessage`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Contenuto documento (`Document`) — input binario multimodale | | ✓ | | | | |
| Output strutturato — estrazione JSON da testo libero | | ✓ | | | | |
| Agentic loop con tool calling | | | | ✓ | ✓ | |
| Tool personalizzato (`AsTool` / `ToolInterface`) | | | | ✓ | ✓ | |
| Tool di retrieval (`ExecuteSqlTool`) | | | | ✓ | ✓ | |
| Tool di computation (`CalculateStatisticsTool`) | | | | | ✓ | |
| Tool di output / stato persistente (`SaveReportTool`) | | | | | ✓ | |
| Configurazione multi-agente (`config/packages/ai.yaml`) | `default` | `default` | `default` | `advisor` | `report` | `code_chat` |
| Retry con back-off su `RateLimitExceededException` | | | | ✓ | ✓ | |
| Schema DB iniettato nel system prompt | | | ✓ | ✓ | ✓ | |
| Iniezione data corrente nel system prompt (elimina tool round-trip) | | | | | ✓ | |
| Sanitizzazione prompt / delimitatori anti-injection | | ✓ | | | | |
| Escalation email con trascrizione AI | ✓ | | | | | |
| Download file via token casuale (out-of-webroot) | | | | | ✓ | |
| RAG — vector store + indexer + retriever | | | | | | ✓ |
| Embedding locali (Ollama, nessuna API key) | | | | | | ✓ |
| Contesto codebase nel system prompt (chunk rilevanti) | | | | | | ✓ |

### Pattern AI per livello di complessità

| Livello | Pattern | Feature |
|---------|---------|---------|
| **Base** | Agente a singola chiamata, nessun tool | Doc Chat, SQL Assistant |
| **Intermedio** | Agente a singola chiamata + input multimodale + normalizzazione output | File Parser |
| **Avanzato** | Agentic loop, 1 tool, schema nel system prompt, retry | Advisor |
| **Esperto** | Agentic loop, 3 tool con responsabilità distinte (retrieval / computation / output), stato persistente nel tool, pipeline sequenziale | Report |
| **RAG** | Vector store, embedding locali, retrieval semantico, contesto dinamico nel prompt | Code Chat |

---

## Architettura

### Struttura del progetto

Il progetto segue un'organizzazione a **vertical slices**: ogni funzione è autocontenuta in un controller, una directory `Service/<NomeFunzione>/` e una directory `templates/<nome_funzione>/`.

```
src/
├── Controller/
│   ├── HomeController.php          # Landing page + GET /mcp-config download
│   ├── DocChatController.php       # Upload opzionale + chat + escalation email
│   ├── FileParserController.php
│   ├── SqlController.php
│   ├── AdvisorController.php
│   ├── ReportController.php
│   └── CodeChatController.php      # /code-chat — chat RAG sul codebase
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
│       └── CodeChatService.php     # retrieve chunks → context → Claude
├── Command/
│   └── IndexCodebaseCommand.php    # app:index-codebase
├── Tool/
│   ├── ExecuteSqlTool.php          # #[AsTool] + #[McpTool] — advisor, report, MCP
│   ├── CalculateStatisticsTool.php # #[AsTool] + #[McpTool] — report, MCP
│   ├── SaveReportTool.php          # #[AsTool] — solo report
│   └── DatabaseQueryTool.php       # non attivo — conservato per riferimento
└── EventSubscriber/
    ├── AiExceptionSubscriber.php
    └── SecurityHeadersSubscriber.php
config/
├── packages/
│   ├── ai.yaml                     # Agenti + store + vectorizer + indexer + retriever
│   ├── ai_ollama_platform.yaml     # Endpoint Ollama (OLLAMA_URL)
│   └── mcp.yaml                    # Server MCP (stdio + HTTP, schema DB nelle instructions)
├── routes/
│   └── mcp.yaml
templates/
├── home/
├── doc_chat/
├── file_parser/
├── sql/
├── advisor/
├── report/
├── code_chat/
└── email/
doc/
├── db.md           ← schema completo ~2600 token (usato da SqlService)
└── db_compact.md   ← schema compatto ~210 token (usato da Advisor, Report e MCP instructions)
var/
└── code_store.db   ← vector store SQLite (auto-creato, escluso da git)
```

### Agenti AI configurati

Sono configurati quattro agenti in `config/packages/ai.yaml`:

| Agente | Servizio | Tool | Note |
|--------|----------|------|------|
| `default` | DocChat, FileParser, Sql | Nessuno | Agente base, singola chiamata |
| `advisor` | AdvisorService | `ExecuteSqlTool` | Multi-step, domande analitiche |
| `report` | ReportService | `ExecuteSqlTool`, `CalculateStatisticsTool`, `SaveReportTool` | Multi-step, genera file scaricabile |
| `code_chat` | CodeChatService | Nessuno | RAG — risponde sul codebase con contesto dinamico |

```yaml
# config/packages/ai.yaml
ai:
  agent:
    default:
      platform: 'ai.platform.anthropic'
      model: { name: Claude::SONNET_4, options: { max_tokens: 8096 } }
    advisor:
      platform: 'ai.platform.anthropic'
      model: { name: Claude::SONNET_4, options: { max_tokens: 8096 } }
      tools: [App\Tool\ExecuteSqlTool]
    report:
      platform: 'ai.platform.anthropic'
      model: { name: Claude::SONNET_4, options: { max_tokens: 8096 } }
      tools: [App\Tool\ExecuteSqlTool, App\Tool\CalculateStatisticsTool, App\Tool\SaveReportTool]
    code_chat:
      platform: 'ai.platform.anthropic'
      model: { name: Claude::SONNET_4, options: { max_tokens: 4096 } }

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

### Tool disponibili

#### ExecuteSqlTool
Esegue una query SQL SELECT passata direttamente dall'agente via DBAL. L'agente conosce lo schema perché è iniettato nel system prompt (`doc/db_compact.md`). Nessuna chiamata AI interna — eliminato il pattern double-AI che causava rate limit. Usato da Advisor e Report.

#### CalculateStatisticsTool
Calcola statistiche descrittive precise (media, mediana, min, max, deviazione standard) su un array JSON di numeri. Necessario perché l'LLM non è un calcolatore affidabile: le operazioni floating-point su dataset reali richiedono un tool dedicato. Usato solo dal Report.

#### SaveReportTool
Salva il report Markdown in `var/reports/{token}.md` e memorizza il token nell'istanza (shared service). Il controller legge il token dopo la chiamata all'agente e lo include nella risposta JSON per il download. Usato solo dal Report.

#### DatabaseQueryTool _(non attivo)_
Traduce una domanda in linguaggio naturale in SQL tramite una seconda chiamata AI interna (SqlService). Non è più registrato su nessun agente perché il pattern double-AI esauriva il rate limit Anthropic. Conservato per documentazione.

### Schema database e rate limit

Il free tier Anthropic (5 req/min, 10K token/min) impone vincoli stringenti. Ogni round dell'agentic loop re-invia il system prompt, quindi la sua dimensione determina quanti round sono possibili prima di esaurire la quota.

| File schema | Token (~) | Usato da |
|-------------|-----------|----------|
| `doc/db.md` | ~2600 | SqlService (chiamata singola, no loop) |
| `doc/db_compact.md` | ~210 | AdvisorService, ReportService (agentic loop) |

Advisor e Report usano `db_compact.md` per ridurre il token budget per round. Il Report limita inoltre `execute_sql` a max 3 chiamate nel system prompt. Entrambi i servizi hanno retry con back-off lineare (30 s × tentativo) su `RateLimitExceededException`.

---

## Servizi principali

### ChatService

**File:** `src/Service/DocChat/ChatService.php`

Gestisce le richieste AI per la chat documentazione. Rileva quando l'utente ha bisogno di supporto umano tramite il tag `[SUPPORTO_EMAIL]` nella risposta.

**Metodo principale:** `ask(string $docContext, string $question): array`
- Output: `{reply: string, offer_email: bool}`

### SupportEmailService

**File:** `src/Service/DocChat/SupportEmailService.php`

Assembla e invia l'email di escalation. Sanitizza la cronologia chat (max 100 messaggi, max 2000 caratteri ciascuno), renderizza il template HTML e allega il transcript come `.txt`.

**Metodo principale:** `send(string $name, string $userEmail, string $projectName, array $rawHistory): void`

### FileParserService

**File:** `src/Service/FileParser/FileParserService.php`

Estrae dati da PDF tramite AI. Il prompt utente viene sanitizzato e delimitato con tag `[USER_INSTRUCTION_START]` per prevenire prompt injection. L'output AI viene normalizzato a JSON valido.

**Metodo principale:** `extract(string $filePath, string $prompt): array`

### SqlService

**File:** `src/Service/Sql/SqlService.php`

Genera ed esegue query SQL. Carica lo schema completo da `doc/db.md`, lo inietta nel system prompt e valida che la query generata sia un SELECT prima dell'esecuzione.

**Metodo principale:** `query(string $prompt): array{sql, columns, rows, total}`

### AdvisorService

**File:** `src/Service/Advisor/AdvisorService.php`

Inietta lo schema compatto (`doc/db_compact.md`) nel system prompt e delega all'agente `advisor` con `ExecuteSqlTool`. Retry con back-off su rate limit.

**Metodo principale:** `ask(string $question): string`

### ReportService

**File:** `src/Service/Report/ReportService.php`

Inietta data corrente e schema compatto nel system prompt, poi delega all'agente `report` con tre tool. La data è risolta in PHP (`buildSystemPrompt`) per eliminare il tool `get_current_date` e risparmiare un round API. Retry con back-off su rate limit.

**Metodo principale:** `generate(string $prompt): string`

### CodeChatService

**File:** `src/Service/CodeChat/CodeChatService.php`

Implementa la pipeline RAG per Code Chat. Usa `#[Autowire('@ai.retriever.code')]` per selezionare il retriever specifico tra più retriever configurati. Il testo di ogni chunk è archiviato in `Metadata::KEY_TEXT` al momento dell'indicizzazione e letto con `getText()` al momento del retrieval.

**Metodo principale:** `ask(string $question): array{reply: string}`

Pipeline interna:
1. `$retriever->retrieve($question, ['maxItems' => 8])` — vettorizza la query con Ollama e recupera i chunk simili
2. Costruisce il contesto come lista di blocchi `### path/to/file.php\n```\ncodice\n````
3. Inietta il contesto nel system prompt e chiama l'agente `code_chat`

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
| Download | Token validato con regex allowlist, file serviti fuori da `public/` |
| Risposta HTTP | Security headers su ogni risposta |
| Rate limit | Retry con back-off + `RateLimitExceededException` → HTTP 429 |

### Security Headers (SecurityHeadersSubscriber)

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
| `DATABASE_URL` | DSN MySQL | `mysql://app:app@127.0.0.1:3306/symfonyai` |
| `OLLAMA_URL` | Endpoint Ollama per gli embedding | `http://localhost:11434` |

### Comandi principali

```bash
composer install
php bin/console cache:clear
php bin/phpunit
php bin/console doctrine:fixtures:load

# Avvio completo (MySQL + Ollama + Symfony + indicizzazione automatica)
./cmd/start.sh

# Ferma tutto
./cmd/start.sh --stop

# Re-indicizza il codebase dopo modifiche al codice
./cmd/start.sh --index
# oppure direttamente:
php bin/console app:index-codebase --truncate

# Setup manuale vector store (solo prima volta, se non si usa start.sh)
php bin/console ai:store:setup ai.store.sqlite.code
php bin/console app:index-codebase
```

---

## Database

MySQL 8 via Doctrine DBAL. Lo schema completo è in `doc/db.md`; la versione compatta per i system prompt degli agenti è in `doc/db_compact.md`. Le fixtures (FakerPHP) generano oltre 100 record di dati realistici.

---

## Traduzioni

File attivo: `translations/messages.it.yaml` — Italiano (locale predefinita). Chiavi in dot-notation per feature (es. `report.generate`, `advisor.ask`).

---

## Come aggiungere una nuova funzione

1. Creare `src/Controller/<NomeFunzione>Controller.php`.
2. Creare `src/Service/<NomeFunzione>/<NomeFunzione>Service.php`.
3. Creare `templates/<nome_funzione>/index.html.twig`.
4. Aggiungere le chiavi in `translations/messages.it.yaml`.
5. Aggiungere la card in `templates/home/index.html.twig`.
6. Se serve un agente con tool: configurare in `config/packages/ai.yaml` e aggiungere `autoconfigure: false` in `config/services.yaml` per ogni nuovo tool.
