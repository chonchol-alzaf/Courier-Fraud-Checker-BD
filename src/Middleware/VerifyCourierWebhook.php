<?php

namespace Alzaf\BdCourier\Middleware;

use Alzaf\BdCourier\Supports\CourierWebhookConfig;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyCourierWebhook
{
    public function __construct(private CourierWebhookConfig $courierWebhookConfig) {}

    public function handle(Request $request, Closure $next, string $provider): Response
    {
        $headerName = $this->courierWebhookConfig->requestHeader($provider);

        $secret = $this->courierWebhookConfig->requestSecret($provider);

        if ($headerName === '' || blank($secret)) {
            Log::error('Courier webhook verification is not configured.', [
                'provider' => $provider,
                'route' => $request->route()?->getName(),
            ]);

            throw new AuthenticationException('Unauthenticated.');
        }

        $providedSecret = $request->header($headerName);

        if (! is_string($providedSecret) || $providedSecret === '' || ! hash_equals($secret, $providedSecret)) {
            Log::warning('Courier webhook verification failed.', [
                'provider' => $provider,
                'header' => $headerName,
                'ip' => $request->ip(),
                'route' => $request->route()?->getName(),
            ]);

            throw new AuthenticationException('Unauthenticated.');
        }

        return $next($request);
    }
}
