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
 *   - X-Permitted-Cross-Domain-Policies: blocks Flash/PDF cross-domain requests.
 *   - Permissions-Policy: disables powerful browser features not used by this app.
 *   - Strict-Transport-Security: enforces HTTPS on subsequent visits (HTTPS only).
 *
 * CSP notes:
 *   - 'unsafe-inline' is retained in style-src because the home and chat
 *     templates use inline <style> blocks. Remove it once those blocks are
 *     moved to external stylesheets.
 *   - 'unsafe-inline' is retained in script-src for the window.DocChat /
 *     window.FileParser config data islands rendered inline by Twig.
 *   - 'data:' is included in img-src to allow the Symfony profiler toolbar,
 *     which uses data: URIs for its SVG favicon in development mode.
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
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );
        $headers->set(
            'Content-Security-Policy',
            // 'unsafe-inline' is retained for style-src (inline <style> blocks in templates) and
            // script-src (window.DocChat / window.FileParser config data islands in Twig templates).
            // img-src and script-src include 'data:' to allow the Symfony profiler toolbar, which
            // uses data: URIs for its SVG favicon and inline scripts in dev mode.
            // TODO: migrate inline <style> blocks to external CSS and remove 'unsafe-inline' from style-src.
            "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:"
        );

        // HSTS is only meaningful over HTTPS; skip it for plain HTTP (local dev).
        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
