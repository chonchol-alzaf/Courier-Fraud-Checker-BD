<?php
namespace Alzaf\BdCourier\Supports;

class CourierWebhookConfig
{
    public function requestHeader(string $provider): string
    {
        return $this->resolveString($provider, [
            'signature_header',
            'request_header',
            'secret_header',
        ]);
    }

    public function responseHeader(string $provider): string
    {
        return $this->resolveString($provider, [
            'response_header',
            'secret_header',
        ]);
    }

    public function requestSecret(string $provider): ?string
    {
        $secret = $this->resolveString($provider, [
            'signature_value',
        ]);

        return $secret === '' ? null : $secret;
    }

    public function responseSecret(string $provider): ?string
    {
        $secret = $this->resolveString($provider, [
            'secret',
            'secret_header_value',
        ]);

        return $secret === '' ? null : $secret;
    }

    private function resolveString(string $provider, array $keys): string
    {
        foreach ($keys as $key) {
            $value = config(sprintf('bd-courier.%s.incoming.%s', $provider, $key));

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
