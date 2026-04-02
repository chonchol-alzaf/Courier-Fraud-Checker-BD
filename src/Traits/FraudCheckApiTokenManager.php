<?php

namespace Alzaf\BdCourier\Traits;

use Illuminate\Support\Facades\Cache;

trait FraudCheckApiTokenManager
{
    protected int $tokenCacheMinutes = 50;

    /**
     * Get cached token or fetch new one
     */
    protected function getApiToken(): ?string
    {
        $token = Cache::get($this->tokenCacheKey);

        if ($token) {
            return $token;
        }

        $token = $this->requestNewToken();

        if ($token) {
            Cache::put(
                $this->tokenCacheKey,
                $token,
                now()->addMinutes($this->tokenCacheMinutes)
            );
        }

        return $token;
    }

    /**
     * Forget cached token
     */
    protected function forgetApiToken(): void
    {
        Cache::forget($this->tokenCacheKey);
    }

    /**
     * Execute API request with token auto refresh
     */
    protected function requestWithToken(callable $callback)
    {
        return retry(2, function () use ($callback) {

            $token = $this->getApiToken();

            if (! $token) {
                throw new \Exception('Failed to obtain API token');
            }

            $response = $callback($token);

            if ($response->status() === 401) {
                $this->forgetApiToken();
                throw new \Exception('Token expired');
            }

            return $response;

        }, 200);
    }

    /**
     * Child service must implement this
     */
    abstract protected function requestNewToken(): ?string;
}
