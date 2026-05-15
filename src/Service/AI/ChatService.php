<?php

namespace App\Service\AI;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Sends user questions to the Claude AI agent using a documentation-based system prompt.
 *
 * Wraps the low-level MessageBag / AgentInterface call and isolates all
 * AI-specific concerns (system prompt construction, tag detection, rate-limit
 * handling) from the HTTP layer.
 */
class ChatService
{
    /**
     * Tag that the agent embeds in its reply when it wants to offer email escalation.
     * Stripped from the reply text before it is returned to the caller.
     */
    private const EMAIL_OFFER_TAG = '[SUPPORTO_EMAIL]';

    /**
     * @param AgentInterface $default Symfony AI agent wired to the Anthropic Claude model.
     */
    public function __construct(
        private readonly AgentInterface $default,
    ) {
    }

    /**
     * Sends a question to the AI agent using the supplied documentation as context.
     *
     * @param string $docContext Raw documentation text injected into the system prompt.
     * @param string $question   The user's question.
     *
     * @return array{reply: string, offer_email: bool} The cleaned agent reply and an email-escalation flag.
     *
     * @throws RateLimitExceededException When the upstream API rate limit is exceeded.
     */
    public function ask(string $docContext, string $question): array
    {
        $tag = self::EMAIL_OFFER_TAG;
        $systemPrompt = <<<PROMPT
Sei un assistente tecnico di supporto software. Rispondi in italiano.

Comportamento principale:
- Rispondi SOLO a domande relative al software documentato di seguito.
- Saluta l'utente e chiedi come puoi aiutarlo se manda un messaggio generico di saluto.
- Per domande fuori contesto, spiega gentilmente che puoi occuparti solo del software documentato.
- Non inventare funzionalità non presenti nella documentazione.

Quando offrire supporto via email (includi il tag {$tag} nel messaggio):
- L'utente non riesce a trovare la risposta che cerca.
- L'utente segnala un bug o un problema non documentato.
- L'utente chiede esplicitamente di parlare con un operatore o inviare un'email.
- Dopo 2-3 scambi in cui non riesci a risolvere il problema.
- L'utente esprime frustrazione o insoddisfazione.

Quando includi {$tag}, aggiungi anche una frase come:
"Vuoi che ti metta in contatto con il nostro team di supporto? Posso aiutarti a inviare una richiesta via email."

# Documentazione

{$docContext}
PROMPT;

        $messages = new MessageBag(
            new SystemMessage($systemPrompt),
            new UserMessage(new Text($question)),
        );

        $result = $this->default->call($messages);

        $reply = (string) $result->getContent();
        $offerEmail = str_contains($reply, self::EMAIL_OFFER_TAG);
        $reply = trim(str_replace(self::EMAIL_OFFER_TAG, '', $reply));

        return [
            'reply' => $reply,
            'offer_email' => $offerEmail,
        ];
    }
}
