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
    public function __construct(private CourierWebhookConfig $courierWebhookConfig)
    {}

    public function handle(Request $request, Closure $next, string $provider): Response
    {
        [$key_type, $key_name] = $this->resolveCredentialSource($provider);

        $signature_value = config(sprintf('bd-courier.%s.incoming.signature_value', $provider));

        if ($key_name === '' || blank($signature_value)) {
            Log::error('Courier webhook verification is not configured.', [
                'provider' => $provider,
                'source'   => $key_type,
                'route'    => $request->route()?->getName(),
            ]);

            throw new AuthenticationException('Unauthenticated.');
        }

        $providedSecret = $this->resolveProvidedSecret($request, $key_type, $key_name);

        if (! is_string($providedSecret) || $providedSecret === '' || ! hash_equals($signature_value, $providedSecret)) {
            Log::warning('Courier webhook verification failed.', [
                'provider' => $provider,
                'key_type'   => $key_type,
                'key_name'      => $key_name,
                'ip'       => $request->ip(),
                'route'    => $request->route()?->getName(),
            ]);

            throw new AuthenticationException('Unauthenticated.');
        }

        return $next($request);
    }

    /**
     * Prefer query-string verification when configured, otherwise fall back to header verification.
     *
     * @return array{string, string}
     */
    private function resolveCredentialSource(string $provider): array
    {
        $queryParam = trim((string) config("bd-courier.$provider.incoming.signature_query_param", ''));

        if ($queryParam !== '') {
            return ['query', $queryParam];
        }

        return ['header', $this->courierWebhookConfig->requestHeader($provider)];
    }

    private function resolveProvidedSecret(Request $request, string $key_type, string $key_name): mixed
    {
        return match ($key_type) {
            'query' => $request->query($key_name),
            default => $request->header($key_name),
        };
    }
}
