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

class CodeChatService
{
    private const MAX_CHUNKS = 8;

    public function __construct(
        #[Autowire('@ai.agent.code_chat')] private readonly AgentInterface $agent,
        #[Autowire('@ai.retriever.code')] private readonly RetrieverInterface $retriever,
    ) {
    }

    /**
     * @return array{reply: string}
     *
     * @throws RateLimitExceededException
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
