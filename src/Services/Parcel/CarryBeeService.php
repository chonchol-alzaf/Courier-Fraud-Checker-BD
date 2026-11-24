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
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Shope\Core\Exceptions\CustomException;

/**
 * CarryBee Developers' API v2.0 (merchant.carrybee.com/webhook/documentation).
 * Auth: Client-ID / Client-Secret / Client-Context headers, one set per environment.
 * Production https://developers.carrybee.com, sandbox https://sandbox.carrybee.com.
 */
class CarryBeeService implements ParcelServiceInterface
{
    use ParcelValidationTrait;

    public const PRODUCTION_URL = 'https://developers.carrybee.com';

    public const SANDBOX_URL = 'https://sandbox.carrybee.com';

    /** CarryBee store name limit. */
    public const STORE_NAME_MAX = 30;

    protected string $base_url;

    protected array $headers;

    public function __construct()
    {
        CourierFraudCheckerHelper::checkRequiredConfig([
            'bd-courier.carrybee.outgoing.client_id',
            'bd-courier.carrybee.outgoing.client_secret',
            'bd-courier.carrybee.outgoing.client_context',
        ]);

        $baseUrl = trim((string) config('bd-courier.carrybee.outgoing.base_url'));
        if ($baseUrl === '') {
            $baseUrl = config('bd-courier.carrybee.sandbox') ? self::SANDBOX_URL : self::PRODUCTION_URL;
        }
        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            $baseUrl = 'https://'.$baseUrl;
        }

        $this->base_url = rtrim($baseUrl, '/');
        $this->headers = [
            'Client-ID' => (string) config('bd-courier.carrybee.outgoing.client_id'),
            'Client-Secret' => (string) config('bd-courier.carrybee.outgoing.client_secret'),
            'Client-Context' => (string) config('bd-courier.carrybee.outgoing.client_context'),
        ];
    }

    public function errorThrow(Response $response): never
    {
        $json = $response->json() ?? [];
        $causes = data_get($json, 'causes');
        $message = data_get($json, 'message') ?: 'CarryBee request failed (HTTP '.$response->status().').';

        if (is_array($causes) && $causes !== []) {
            $messages = [];
            foreach ($causes as $field => $rules) {
                $types = collect((array) $rules)->pluck('type')->filter()->implode(', ');
                $messages[$field] = [trim("{$field}: {$types}")];
            }
            throw ValidationException::withMessages($messages);
        }

        throw new CustomException(is_array($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message, $response->status() ?: 500);
    }

    protected function request(string $method, string $uri, array $payload = []): Response
    {
        $request = Http::acceptJson()->asJson()->timeout(20)->withHeaders($this->headers);
        $url = $this->base_url.'/api/v2/'.ltrim($uri, '/');

        $response = match (strtolower($method)) {
            'get' => $request->get($url, $payload),
            'post' => $request->post($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported CarryBee request method [{$method}]"),
        };

        if (! $response->successful() || data_get($response->json(), 'error') === true) {
            $this->errorThrow($response);
        }

        return $response;
    }

    /* ------------------------------------------------------------------ Location */

    /** @return array<int, array{id:int, name:string}> */
    public function cities(): array
    {
        return $this->locationRows($this->request('get', 'cities'), 'data.cities');
    }

    /** @return array<int, array{id:int, name:string}> */
    public function zones(int $cityId): array
    {
        return $this->locationRows($this->request('get', "cities/{$cityId}/zones"), 'data.zones');
    }

    /** @return array<int, array{id:int, name:string}> */
    public function areas(int $cityId, int $zoneId): array
    {
        return $this->locationRows($this->request('get', "cities/{$cityId}/zones/{$zoneId}/areas"), 'data.areas');
    }

    public function areaSuggestion(string $search): array
    {
        return (array) data_get($this->request('get', 'area-suggestion', ['search' => $search])->json(), 'data.items', []);
    }

    public function addressDetails(string $query): array
    {
        return (array) data_get($this->request('post', 'address-details', ['query' => $query])->json(), 'data', []);
    }

    private function locationRows(Response $response, string $path): array
    {
        return collect(data_get($response->json(), $path, []))
            ->map(fn ($row) => ['id' => (int) data_get($row, 'id'), 'name' => trim((string) data_get($row, 'name'))])
            ->filter(fn ($row) => $row['id'] > 0 && $row['name'] !== '')
            ->values()
            ->all();
    }

    /** Local Shope area -> CarryBee city/zone/area ids from the approved location map. */
    public function resolveLocationByArea(mixed $local_area_id, bool $throwIfNotFound = true): array
    {
        $courier = CourierEnum::CARRYBEE->value;
        $remoteAreaId = CourierLocationMap::where('local_id', $local_area_id)
            ->where('local_type', Area::class)
            ->where('courier_name', $courier)
            ->where('is_approved', true)
            ->where('is_active', true)
            ->value('remote_id');

        $zoneId = $remoteAreaId
            ? CourierArea::where('courier_name', $courier)->where('courier_id', $remoteAreaId)->value('zone_id')
            : null;
        $cityId = $zoneId
            ? CourierZone::where('courier_name', $courier)->where('courier_id', $zoneId)->value('city_id')
            : null;

        if (! $remoteAreaId || ! $zoneId || ! $cityId) {
            if ($throwIfNotFound) {
                throw new CustomException('Area is not valid for carrybee', 404);
            }

            return [];
        }

        return ['city_id' => (int) $cityId, 'zone_id' => (int) $zoneId, 'area_id' => (int) $remoteAreaId];
    }

    /* ------------------------------------------------------------------ Stores */

    /**
     * Unique, stable store name (<= 30 chars). CarryBee returns no id on create, so the name is how we find
     * the store again in GET /stores. The suffix changes on every edit, because an edit creates a new store.
     */
    public function storeName(PickupPoint $pickupPoint): string
    {
        $version = base_convert((string) ($pickupPoint->updated_at?->timestamp ?? time()), 10, 36);
        $suffix = " #{$pickupPoint->id}-{$version}";
        $vendor = trim(preg_replace('/\s+/u', ' ', (string) ($pickupPoint->vendor->name ?? 'Shope')));
        $prefix = mb_substr($vendor, 0, max(3, self::STORE_NAME_MAX - mb_strlen($suffix)));

        return mb_substr(trim($prefix).$suffix, 0, self::STORE_NAME_MAX);
    }

    public function storeCreate(PickupPoint $pickup_points): mixed
    {
        throw_if(is_null($pickup_points->vendor->name ?? null), new CustomException('Vendor name not found!', 500));
        CourierFraudCheckerHelper::validatePhoneNumber($pickup_points->contact_number);

        $mapping = $this->resolveLocationByArea($pickup_points->area_id);
        $name = $this->storeName($pickup_points);

        $this->request('post', 'stores', [
            'name' => $name,
            'contact_person_name' => $this->fit((string) ($pickup_points->contact_name ?: $pickup_points->vendor->name), 3, 30),
            'contact_person_number' => $pickup_points->contact_number,
            'address' => $this->fit((string) $pickup_points->address, 3, 100),
            'city_id' => $mapping['city_id'],
            'zone_id' => $mapping['zone_id'],
            'area_id' => $mapping['area_id'],
        ]);

        $store = $this->findStoreByName($name);

        if (! $store) {
            throw new CustomException("CarryBee store [{$name}] was created but not found in the store list yet.", 502);
        }

        return [
            'store_id' => $store['store_id'],
            'is_approved' => $store['is_approved'],
            'provider_data' => $store,
        ];
    }

    /**
     * All stores of the business, normalized for approval sync.
     *
     * @return array<int, array{store_id:string, name:string, is_active:bool, is_approved:bool}>
     */
    public function storeList(): array
    {
        return collect(data_get($this->request('get', 'stores')->json(), 'data.stores', []))
            ->map(fn ($store) => [
                'store_id' => (string) data_get($store, 'id'),
                'name' => (string) data_get($store, 'name'),
                'is_active' => (bool) data_get($store, 'is_active'),
                'is_approved' => (bool) data_get($store, 'is_approved'),
                'city_id' => data_get($store, 'city_id'),
                'zone_id' => data_get($store, 'zone_id'),
                'area_id' => data_get($store, 'area_id'),
            ])
            ->filter(fn ($store) => $store['store_id'] !== '')
            ->values()
            ->all();
    }

    public function findStoreByName(string $name): ?array
    {
        foreach ($this->storeList() as $store) {
            if (mb_strtolower(trim($store['name'])) === mb_strtolower(trim($name))) {
                return $store;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ Orders */

    public function addParcel(array $data): mixed
    {
        $this->validateRequiredFields($data, [
            'store_id',
            'recipient_name',
            'recipient_phone',
            'recipient_address',
            'recipient_area_id',
            'item_weight',
        ]);

        CourierFraudCheckerHelper::validatePhoneNumber($data['recipient_phone']);

        $mapping = $this->resolveLocationByArea($data['recipient_area_id']);

        $payload = $this->filterPayload([
            'store_id' => (string) $data['store_id'],
            'merchant_order_id' => isset($data['merchant_order_id']) ? mb_substr((string) $data['merchant_order_id'], 0, 49) : null,
            'delivery_type' => (int) ($data['carrybee_delivery_type'] ?? 1),   // 1 = Normal, 2 = Express
            'product_type' => (int) ($data['carrybee_product_type'] ?? 1),     // 1 = Parcel, 2 = Book, 3 = Document
            'recipient_phone' => $data['recipient_phone'],
            'recipient_name' => $this->fit((string) $data['recipient_name'], 2, 99),
            'recipient_address' => $this->fit((string) $data['recipient_address'], 10, 200),
            'city_id' => $mapping['city_id'],
            'zone_id' => $mapping['zone_id'],
            'area_id' => $mapping['area_id'],
            'special_instruction' => isset($data['special_instruction']) ? mb_substr((string) $data['special_instruction'], 0, 254) : null,
            'product_description' => isset($data['item_description']) ? mb_substr((string) $data['item_description'], 0, 254) : null,
            'item_weight' => $this->grams($data['item_weight']),
            'item_quantity' => isset($data['item_quantity']) ? max(1, min(200, (int) $data['item_quantity'])) : null,
            'collectable_amount' => (int) round((float) ($data['amount_to_collect'] ?? 0)),
        ]);
        // 0 is a valid (prepaid) collectable amount; filterPayload keeps it.

        $order = (array) data_get($this->request('post', 'orders', $payload)->json(), 'data.order', []);
        $consignmentId = data_get($order, 'consignment_id');

        return [
            'consignment_id' => $consignmentId,
            'tracking_id' => $consignmentId,
            'order_status' => 'order.created',
            'store_id' => data_get($order, 'store_id'),
            'merchant_order_id' => data_get($order, 'merchant_order_id'),
            'collectable_amount' => data_get($order, 'collectable_amount'),
            'delivery_fee' => is_numeric(data_get($order, 'delivery_fee')) ? (float) data_get($order, 'delivery_fee') : null,
            'cod_fee' => is_numeric(data_get($order, 'cod_fee')) ? (float) data_get($order, 'cod_fee') : null,
        ];
    }

    /** GET /orders/{id}/details: transfer_status, delivery_fee, cod_fee, collected_amount, ... */
    public function orderDetails(string $consignmentId): array
    {
        return (array) data_get($this->request('get', 'orders/'.rawurlencode($consignmentId).'/details')->json(), 'data', []);
    }

    public function cancelOrder(string $consignmentId, string $reason): bool
    {
        $this->request('post', 'orders/'.rawurlencode($consignmentId).'/cancel', [
            'cancellation_reason' => mb_substr(trim($reason) !== '' ? $reason : 'Cancelled by merchant', 0, 199),
        ]);

        return true;
    }

    /* ------------------------------------------------------------------ Helpers */

    /** Shope sends KG (e.g. 0.5); CarryBee wants whole grams 1..25000. Values above 25 are treated as grams. */
    public function grams(mixed $weight): int
    {
        $value = is_numeric($weight) ? (float) $weight : 0.5;
        $grams = $value <= 25 ? $value * 1000 : $value;

        return (int) max(1, min(25000, round($grams)));
    }

    private function fit(string $value, int $min, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        $value = mb_substr($value, 0, $max);

        return mb_strlen($value) < $min ? str_pad($value, $min, '.') : $value;
    }

    private function filterPayload(array $payload): array
    {
        return array_filter($payload, fn ($value) => $value !== null && $value !== '');
    }
}
