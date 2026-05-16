<?php

namespace App\Service\Advisor;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Orchestrates the multi-step AI advisor backed by the `query_database` tool.
 *
 * The advisor agent receives the user's question together with a focused system
 * prompt. It autonomously decides how many times to invoke `query_database` —
 * and with which sub-questions — before synthesising a final natural-language
 * answer in Italian.
 *
 * Tool registration and execution are handled entirely by the Symfony AI bundle;
 * this service only builds the MessageBag and calls the agent.
 */
class AdvisorService
{
    private const SYSTEM_PROMPT = <<<PROMPT
Sei un assistente analitico esperto per una piattaforma di corsi online. Rispondi sempre in italiano.

Il tuo compito è rispondere alle domande dell'utente sui dati della piattaforma: corsi, studenti,
istruttori, iscrizioni, recensioni e progressi.

Come operare:
1. Usa il tool `query_database` per recuperare i dati di cui hai bisogno.
2. Puoi invocarlo più volte con domande diverse per raccogliere tutte le informazioni necessarie.
3. Analizza i dati restituiti e fornisci una risposta chiara, completa e ben strutturata.
4. Se i dati non sono sufficienti o non esistono, dillo esplicitamente.
5. Non inventare dati: rispondi solo in base a ciò che il tool restituisce.
6. Usa elenchi, tabelle testuali o paragrafi in base a cosa rende la risposta più leggibile.
PROMPT;

    /**
     * @param AgentInterface $advisor Symfony AI agent wired to the Anthropic Claude model
     *                                with the DatabaseQueryTool in its toolbox.
     */
    public function __construct(
        private readonly AgentInterface $advisor,
    ) {
    }

    /**
     * Sends the user's question to the advisor agent and returns its final answer.
     *
     * The agent may call `query_database` multiple times internally before
     * producing the response — the caller receives only the final synthesis.
     *
     * @param string $question The user's natural-language question in Italian or English.
     *
     * @return string The agent's final natural-language answer in Italian.
     */
    public function ask(string $question): string
    {
        $messages = new MessageBag(
            new SystemMessage(self::SYSTEM_PROMPT),
            new UserMessage(new Text($question)),
        );

        $result = $this->advisor->call($messages);

        return trim((string) $result->getContent());
    }
}
