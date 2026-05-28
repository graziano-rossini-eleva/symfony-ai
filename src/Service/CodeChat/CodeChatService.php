<?php

namespace App\Service\CodeChat;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\RetrieverInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Implements the RAG (Retrieval-Augmented Generation) pipeline for Code Chat.
 *
 * At query time, the user question is vectorised by Ollama (nomic-embed-text),
 * the top matching code chunks are retrieved from the SQLite vector store, and
 * Claude is called with a system prompt that includes those chunks as context.
 * The text of each chunk is stored in Metadata::KEY_TEXT at indexing time and
 * read back with Metadata::getText() at retrieval time.
 */
class CodeChatService
{
    /** Maximum number of code chunks injected into the system prompt per query. */
    private const MAX_CHUNKS = 8;

    /**
     * @param AgentInterface     $agent     Claude agent wired to the `code_chat` configuration (no tools).
     * @param RetrieverInterface $retriever Vector retriever wired to the SQLite `code` store and Ollama vectorizer.
     */
    public function __construct(
        #[Autowire('@ai.agent.code_chat')] private readonly AgentInterface $agent,
        #[Autowire('@ai.retriever.code')] private readonly RetrieverInterface $retriever,
    ) {
    }

    /**
     * Answers a question about the project codebase using RAG.
     *
     * Retrieves the most semantically similar code chunks from the vector store,
     * injects them as context into the system prompt, and delegates to the Claude agent.
     *
     * @param string $question The user's natural-language question about the codebase.
     *
     * @return array{reply: string} The agent's answer.
     *
     * @throws RateLimitExceededException When the upstream Anthropic API rate limit is exceeded.
     */
    public function ask(string $question): array
    {
        $results = $this->retriever->retrieve($question, ['maxItems' => self::MAX_CHUNKS]);
        $context = $this->buildContext($results);

        $systemPrompt = <<<PROMPT
Sei un esperto sviluppatore che risponde a domande sul codebase di questo progetto Symfony.
Rispondi sempre in italiano.

Regole:
- Basati ESCLUSIVAMENTE sui frammenti di codice forniti nel contesto.
- Cita il percorso del file quando è rilevante (es. "In src/Controller/DocChatController.php...").
- Se la risposta non è ricavabile dal contesto, dillo esplicitamente senza inventare.
- Per domande architetturali spiega il flusso e i componenti coinvolti.
- Formatta il codice con blocchi markdown ```lang.

# Contesto del codebase

{$context}
PROMPT;

        $messages = new MessageBag(
            new SystemMessage($systemPrompt),
            new UserMessage(new Text($question)),
        );

        $result = $this->agent->call($messages);

        return ['reply' => trim((string) $result->getContent())];
    }

    /**
     * Builds a Markdown-formatted context string from retrieved vector documents.
     *
     * Each document is rendered as a fenced code block preceded by its source file path.
     * Documents whose stored text is empty are silently skipped. Returns a fallback
     * message when no relevant chunks are found (e.g. the store has not been indexed yet).
     *
     * @param iterable<\Symfony\AI\Store\Document\VectorDocument> $documents Retrieved documents from the vector store.
     *
     * @return string Formatted context ready to be embedded in the system prompt.
     */
    private function buildContext(iterable $documents): string
    {
        $parts = [];

        foreach ($documents as $doc) {
            $source = $doc->getMetadata()->offsetExists(Metadata::KEY_SOURCE)
                ? $doc->getMetadata()->offsetGet(Metadata::KEY_SOURCE)
                : 'unknown';

            $text = $doc->getMetadata()->getText() ?? '';

            if ('' !== trim($text)) {
                $parts[] = sprintf("### %s\n```\n%s\n```", $source, trim($text));
            }
        }

        if ([] === $parts) {
            return '(Nessun frammento rilevante trovato nel codebase indicizzato. Esegui `php bin/console app:index-codebase` per indicizzare il progetto.)';
        }

        return implode("\n\n", $parts);
    }
}
