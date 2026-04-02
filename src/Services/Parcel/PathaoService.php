<?php

namespace Alzaf\BdCourier\Services\Parcel;

use Alzaf\BdCourier\Contracts\ParcelServiceInterface;
use Alzaf\BdCourier\Enums\CourierEnum;
use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Traits\ParcelValidationTrait;
use App\Models\Area;
use App\Models\CourierArea;
use App\Models\CourierLocationMap;
use App\Models\CourierZone;
use App\Models\PickupPoint;
use App\Models\Zone;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Shope\Core\Exceptions\CustomException;

class PathaoService implements ParcelServiceInterface
{
    use ParcelValidationTrait;

    protected string $username;

    protected string $password;

    protected string $base_url;

    protected string $client_id;

    protected string $client_secret;

    public function __construct()
    {
        CourierFraudCheckerHelper::checkRequiredConfig([
            'bd-courier.pathao.outgoing.username',
            'bd-courier.pathao.outgoing.password',
            'bd-courier.pathao.outgoing.base_url',
            'bd-courier.pathao.outgoing.client_id',
            'bd-courier.pathao.outgoing.client_secret',
        ]);

        $this->username = config('bd-courier.pathao.outgoing.username');
        $this->password = config('bd-courier.pathao.outgoing.password');
        $this->base_url = config('bd-courier.pathao.outgoing.base_url');
        $this->client_id = config('bd-courier.pathao.outgoing.client_id');
        $this->client_secret = config('bd-courier.pathao.outgoing.client_secret');
    }

    private function issueToken(): string
    {
        $cacheKey = 'pathao:access_token';
        $accessToken = Cache::get($cacheKey);

        if (is_string($accessToken) && $accessToken !== '') {
            return $accessToken;
        }

        if ($accessToken !== null) {
            Cache::forget($cacheKey);
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->base_url.'/aladdin/api/v1/issue-token', [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'password',
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $accessToken = (string) $response->json('access_token', '');
        if ($accessToken === '') {
            throw new CustomException('Pathao access token missing from response.', 500);
        }

        $expiresIn = max((int) $response->json('expires_in', 0) - 200, 60);

        Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn));

        return $accessToken;
    }

    public function errorThrow(Response $response): never
    {
        $errors = data_get($response->json(), 'errors');
        $message = data_get($response->json(), 'message') ?? 'Something goes wrong!';
        $code = (int) (data_get($response->json(), 'code') ?? $response->status() ?? 500);

        if ($code === 422 && is_array($errors)) {
            throw ValidationException::withMessages($errors);
        }

        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }
        throw new CustomException($message, $code);
    }

    private function authorizedJsonRequest(string $method, string $uri, array $payload = []): Response
    {
        $request = Http::withToken($this->issueToken())
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);

        return match (strtolower($method)) {
            'get' => $request->get($this->base_url.$uri, $payload),
            'post' => $request->post($this->base_url.$uri, $payload),
            default => throw new \InvalidArgumentException("Unsupported Pathao request method [{$method}]"),
        };
    }

    public function resolveLocationByArea(mixed $local_area_id, bool $throwIfNotFound = true): array
    {
        $courier_area_id = CourierLocationMap::where('local_id', $local_area_id)
            ->where('local_type', Area::class)
            ->where('courier_name', CourierEnum::PATHAO->value)
            ->value('remote_id');

        if (! $courier_area_id) {

            if ($throwIfNotFound) {
                throw new CustomException('Area is not valid for pathao', 404);
            }

            return [];
        }

        $zone_id = CourierArea::where('courier_id', $courier_area_id)
            ->where('courier_name', CourierEnum::PATHAO->value)
            ->value('zone_id');
        $city_id = CourierZone::where('courier_id', $zone_id)
            ->where('courier_name', CourierEnum::PATHAO->value)
            ->value('city_id');

        return ['zone_id' => $zone_id, 'city_id' => $city_id, 'area_id' => (int) $courier_area_id];
    }

    private function resolveLocationByZone($local_zone_id, bool $throwIfNotFound = true)
    {
        $courier_zone_id = CourierLocationMap::where('local_id', $local_zone_id)
            ->where('local_type', Zone::class)
            ->where('courier_name', CourierEnum::PATHAO->value)
            ->value('remote_id');

        if (! $courier_zone_id) {
            if ($throwIfNotFound) {
                throw new CustomException('Zone is not valid for pathao', 404);
            }

            return [];
        }

        $city_id = CourierZone::where('courier_id', $courier_zone_id)
            ->where('courier_name', CourierEnum::PATHAO->value)
            ->value('city_id');

        return ['zone_id' => (int) $courier_zone_id, 'city_id' => $city_id];
    }

    public function storeCreate(PickupPoint $pickup_points): mixed
    {
        throw_if(is_null($pickup_points->vendor->name ?? null), new CustomException('Vendor name not found!', 500));
        CourierFraudCheckerHelper::validatePhoneNumber($pickup_points->contact_number);

        $mapping = $this->resolveLocationByArea($pickup_points->area_id);

        $data = [
            'name' => config('app.platform_name')."({$pickup_points->vendor->name}-{$pickup_points->id})",
            'contact_name' => $pickup_points->contact_name,
            'contact_number' => $pickup_points->contact_number,
            'address' => $pickup_points->address,
            'city_id' => $mapping['city_id'],
            'zone_id' => $mapping['zone_id'],
            'area_id' => $mapping['area_id'],
        ];

        $response = $this->authorizedJsonRequest('post', '/aladdin/api/v1/stores', $data);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $store_id = data_get($response->json(), 'data.store_id');

        return [
            'store_id' => $store_id,
            'provider_data' => data_get($response->json(), 'data'),
        ];
    }

    public function cityList()
    {
        $cache_key = 'pathoa:city-lists';

        $city_lists = Cache::get($cache_key);

        if ($city_lists) {
            return $city_lists;
        }

        $response = $this->authorizedJsonRequest('get', '/aladdin/api/v1/city-list');

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $data = data_get($response->json(), 'data.data');

        $city_lists = collect($data)->map(function ($item) {
            return [
                'id' => $item['city_id'] ?? null,
                'name' => $item['city_name'] ?? null,
            ];
        });

        Cache::put($cache_key, $city_lists, now()->addMinutes(5));

        return $city_lists;
    }

    public function zoneList($city_id)
    {
        $cache_key = "pathoa:zone-list:{$city_id}";

        $zone_lists = Cache::get($cache_key);

        if ($zone_lists) {
            return $zone_lists;
        }

        $response = $this->authorizedJsonRequest('get', "/aladdin/api/v1/cities/{$city_id}/zone-list");

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $data = data_get($response->json(), 'data.data');

        $zone_lists = collect($data)->map(function ($item) {
            return [
                'id' => $item['zone_id'] ?? null,
                'name' => $item['zone_name'] ?? null,
            ];
        });

        Cache::put($cache_key, $zone_lists, now()->addMinutes(5));

        return $zone_lists;
    }

    public function areaList($zone_id)
    {
        $cache_key = "pathoa:area-list:{$zone_id}";

        $area_lists = Cache::get($cache_key);

        if ($area_lists) {
            return $area_lists;
        }

        $response = $this->authorizedJsonRequest('get', "/aladdin/api/v1/zones/{$zone_id}/area-list");

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $data = data_get($response->json(), 'data.data');

        $area_lists = collect($data)->map(function ($item) {
            return [
                'id' => $item['area_id'] ?? null,
                'name' => $item['area_name'] ?? null,
                'home_delivery_available' => $item['home_delivery_available'] ?? false,
                'pickup_available' => $item['pickup_available'] ?? false,
            ];
        });

        Cache::put($cache_key, $area_lists, now()->addMinutes(5));

        return $area_lists;
    }

    public function addParcel(array $data): mixed
    {
        $this->validateRequiredFields($data, [
            'store_id',
            'recipient_name',
            'recipient_phone',
            'recipient_address',
            'delivery_type', // 48 for Normal Delivery, 12 for On Demand Delivery
            'item_type',     // 1 for Document, 2 for Parcel
            'item_quantity',
            'item_weight', // Minimum 0.5 KG to Maximum 10 kg. Weight of your parcel in kg
            'amount_to_collect',
        ]);

        CourierFraudCheckerHelper::validatePhoneNumber($data['recipient_phone']);

        $mapping = $this->resolveParcelLocation($data);

        $payload = $this->filterPayload([
            'store_id' => $data['store_id'] ?? null,
            'merchant_order_id' => $data['merchant_order_id'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_phone' => $data['recipient_phone'] ?? null,
            'recipient_address' => $data['recipient_address'] ?? null,
            'recipient_city' => $mapping['city_id'] ?? null,
            'recipient_zone' => $mapping['zone_id'] ?? null,
            'recipient_area' => $mapping['area_id'] ?? null,
            'special_instruction' => $data['special_instruction'] ?? null,
            'delivery_type' => $data['delivery_type'] ?? null,
            'item_type' => $data['item_type'] ?? null,
            'item_quantity' => $data['item_quantity'] ?? null,
            'item_weight' => $data['item_weight'] ?? null,
            'amount_to_collect' => $data['amount_to_collect'] ?? null,
        ]);

        $response = $this->authorizedJsonRequest('post', '/aladdin/api/v1/orders', $payload);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $response = data_get($response->json(), 'data');

        return $response;

    }

    private function resolveParcelLocation(array $data): array
    {
        if (isset($data['recipient_area_id']) && $data['recipient_area_id'] !== null && $data['recipient_area_id'] !== '') {
            return $this->resolveLocationByArea($data['recipient_area_id']);
        }

        if (isset($data['recipient_zone_id']) && $data['recipient_zone_id'] !== null && $data['recipient_zone_id'] !== '') {
            return $this->resolveLocationByZone($data['recipient_zone_id']);
        }

        throw ValidationException::withMessages([
            'recipient_area_id' => ['Either recipient_area_id or recipient_zone_id is required.'],
        ]);
    }

    private function filterPayload(array $payload): array
    {
        return array_filter($payload, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
