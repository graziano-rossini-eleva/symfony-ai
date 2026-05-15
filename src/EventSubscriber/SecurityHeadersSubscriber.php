<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security-related HTTP response headers to every main request.
 *
 * Applied headers:
 *   - X-Frame-Options: prevents the page from being embedded in a frame.
 *   - X-Content-Type-Options: prevents MIME-type sniffing.
 *   - Referrer-Policy: limits referrer information sent to third parties.
 *   - Content-Security-Policy: restricts resource origins.
 *
 * Note on 'unsafe-inline': both the home and chat templates use inline <style>
 * blocks, and the chat template contains an inline <script> block, so
 * 'unsafe-inline' is required for style-src and script-src. Remove these
 * directives if the templates are later refactored to use external assets.
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * Attaches security headers to the response for every master request.
     *
     * Sub-requests (e.g. ESI fragments) are skipped intentionally.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set(
            'Content-Security-Policy',
            "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'"
        );
    }
}
