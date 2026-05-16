<?php

namespace App\EventSubscriber;

use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts platform-level AI exceptions into JSON error responses.
 *
 * The Symfony AI bundle throws exceptions (e.g. RateLimitExceededException)
 * from inside the agent's tool-calling loop. In some execution paths this
 * exception bypasses the controller's try/catch and reaches the kernel
 * exception handler; this subscriber converts it to a proper JSON response
 * before Symfony renders the HTML error page.
 */
class AiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // High priority so we run before the default ErrorListener (priority 0).
        return [KernelEvents::EXCEPTION => ['onKernelException', 64]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Unwrap chained exceptions — the root cause may be nested.
        $root = $exception;
        while (null !== $root->getPrevious()) {
            $root = $root->getPrevious();
        }

        if ($root instanceof RateLimitExceededException || $exception instanceof RateLimitExceededException) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Troppe richieste verso l\'AI. Attendi qualche secondo e riprova.'],
                Response::HTTP_TOO_MANY_REQUESTS
            ));

            return;
        }
    }
}
