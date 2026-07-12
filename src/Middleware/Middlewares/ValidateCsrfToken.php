<?php

namespace Novalites\Middleware\Middlewares;

use Novalites\Http\Request;
use Novalites\Http\Response;
use Novalites\Middleware\MiddlewareInterface;
use Novalites\Security\CsrfToken;

class ValidateCsrfToken implements MiddlewareInterface
{
    /**
     * Method yang WAJIB diverifikasi. GET/HEAD/OPTIONS dianggap "safe method"
     * (ga ngubah state), jadi ga perlu CSRF check.
     */
    protected array $verifyMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * URI yang dikecualikan dari CSRF check (misal webhook pihak ketiga, API dengan token auth sendiri).
     * Support wildcard pakai '*', contoh: 'webhooks/*'
     */
    protected array $except = [
        'api/*', // API biasanya pakai token-based auth (Bearer token), bukan session/CSRF
    ];

    public function handle(Request $request): void
    {
        if (!in_array($request->method(), $this->verifyMethods, true)) {
            return; // safe method, skip
        }

        if ($this->isExcluded($request)) {
            return;
        }

        $token = $this->extractToken($request);

        if (!CsrfToken::verify($token)) {
            $this->reject($request);
        }
    }

    protected function extractToken(Request $request): ?string
    {
        // Cek dari form input dulu (_token), baru header (buat request AJAX/fetch)
        return $request->input('_token')
            ?? $request->header('X-CSRF-TOKEN')
            ?? $request->header('X-XSRF-TOKEN');
    }

    protected function isExcluded(Request $request): bool
    {
        $uri = ltrim($request->uri(), '/');

        foreach ($this->except as $pattern) {
            if ($this->matchPattern($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    protected function matchPattern(string $pattern, string $uri): bool
    {
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '$#', $uri);
    }

    protected function reject(Request $request): never
    {
        $wantsJson = str_contains($request->header('accept', ''), 'application/json')
            || strtolower($request->header('x-requested-with', '')) === 'xmlhttprequest'
            || str_starts_with(ltrim($request->uri(), '/'), 'api/');

        if ($wantsJson) {
            Response::error('CSRF token tidak valid atau kadaluarsa.', 419);
        }

        Response::html(
            '<h1>419 | Page Expired</h1><p>Sesi kamu udah kadaluarsa, silakan refresh halaman dan coba lagi.</p>',
            419
        )->send();
    }
}
