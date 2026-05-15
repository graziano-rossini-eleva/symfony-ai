<?php

namespace App\Service\Mail;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Assembles and dispatches support-request emails with a plain-text chat transcript.
 *
 * Isolates all mail-assembly concerns (template rendering, transcript generation,
 * address configuration) from the HTTP layer.
 */
class SupportEmailService
{
    /**
     * @param MailerInterface     $mailer       Symfony Mailer used to dispatch the email.
     * @param TranslatorInterface $translator   Translator used for subject and transcript strings.
     * @param Environment         $twig         Twig environment used to render the HTML body.
     * @param string              $supportEmail Recipient address for escalated support requests.
     * @param string              $fromEmail    Sender address used in outbound support emails.
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly Environment $twig,
        private readonly string $supportEmail,
        private readonly string $fromEmail,
    ) {
    }

    /**
     * Sends a support-request email with the full chat transcript attached.
     *
     * @param string                                         $name        Display name of the user.
     * @param string                                         $userEmail   Email address of the user.
     * @param string                                         $projectName Software project name taken from the session.
     * @param array<int, array{role: string, text: string}> $history     Ordered list of chat messages.
     */
    public function send(string $name, string $userEmail, string $projectName, array $history): void
    {
        $subject = $this->translator->trans('email.subject', [
            '%projectName%' => $projectName,
            '%name%' => $name,
        ]);

        $transcript = $this->buildTranscript($name, $userEmail, $projectName, $history);

        $html = $this->twig->render('email/support_request.html.twig', [
            'name' => $name,
            'userEmail' => $userEmail,
            'projectName' => $projectName,
            'history' => $history,
            'date' => new \DateTimeImmutable(),
        ]);

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($this->supportEmail)
            ->replyTo($userEmail)
            ->subject($subject)
            ->html($html)
            ->attach($transcript, 'chat-transcript.txt', 'text/plain; charset=utf-8');

        $this->mailer->send($email);
    }

    /**
     * Builds a plain-text chat transcript for the support email attachment.
     *
     * @param string                                         $name    Display name of the user.
     * @param string                                         $email   Email address of the user.
     * @param string                                         $project Software project name taken from the session.
     * @param array<int, array{role: string, text: string}> $history Ordered list of chat messages.
     *
     * @return string Plain-text transcript ready to be attached to the support email.
     */
    private function buildTranscript(string $name, string $email, string $project, array $history): string
    {
        $t = $this->translator;
        $lines = [];
        $lines[] = $t->trans('transcript.header');
        $lines[] = $t->trans('transcript.date', ['%date%' => date('d/m/Y H:i')]);
        $lines[] = $t->trans('transcript.project', ['%project%' => $project]);
        $lines[] = $t->trans('transcript.user_info', ['%name%' => $name, '%email%' => $email]);
        $lines[] = str_repeat('=', 40);
        $lines[] = '';

        foreach ($history as $msg) {
            $role = $msg['role'] === 'user'
                ? $t->trans('transcript.role.user', ['%name%' => $name])
                : $t->trans('transcript.role.assistant');
            $lines[] = "[{$role}]";
            $lines[] = $msg['text'];
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
