<?php

namespace Alzaf\BdCourier\Services\Parcel;

use Alzaf\BdCourier\Contracts\ParcelServiceInterface;
use Alzaf\BdCourier\Enums\CourierEnum;
use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Traits\ParcelValidationTrait;
use App\Models\Area;
use App\Models\CourierArea;
use App\Models\CourierLocationMap;
use App\Models\PickupPoint;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Shope\Core\Exceptions\CustomException;

class RedxService implements ParcelServiceInterface
{
    use ParcelValidationTrait;

    protected string $base_url;

    protected string $token;

    public function __construct()
    {
        CourierFraudCheckerHelper::checkRequiredConfig([
            'bd-courier.redx.outgoing.token',
            'bd-courier.redx.outgoing.base_url',

        ]);

        $this->base_url = config('bd-courier.redx.outgoing.base_url');
        $this->token = config('bd-courier.redx.outgoing.token');
    }

    public function errorThrow(Response $response): never
    {
        $validation_errors = data_get($response->json(), 'validation_errors');
        $message = data_get($response->json(), 'message') ?? 'Something goes wrong!';
        $code = $response->status() ?? 500;

        Log::debug($validation_errors);
        if ($validation_errors) {
            throw ValidationException::withMessages($validation_errors);
        }

        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }
        throw new CustomException($message, $code);
    }

    private function redxRequest(string $method, string $uri, array $payload = []): Response
    {
        $request = Http::withHeaders([
            'API-ACCESS-TOKEN' => 'Bearer '.$this->token,
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);

        return match (strtolower($method)) {
            'get' => $request->get($this->base_url.$uri, $payload),
            'post' => $request->post($this->base_url.$uri, $payload),
            default => throw new \InvalidArgumentException("Unsupported Redx request method [{$method}]"),
        };
    }

    public function areaList()
    {
        $response = $this->redxRequest('get', '/areas');

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $data = data_get($response->json(), 'areas');

        $area_lists = collect($data)->map(function ($item) {
            return [
                'id' => $item['id'] ?? null,
                'name' => $item['name'] ?? null,
                'post_code' => $item['post_code'] ?? null,
                'zone_id' => $item['zone_id'] ?? null,
                'district_name' => $item['district_name'] ?? null,
                'division_name' => $item['division_name'] ?? null,
                'home_delivery_available' => $item['home_delivery_available'] ?? true,
                'pickup_available' => $item['pickup_available'] ?? true,
            ];
        });

        return $area_lists->toArray();
    }

    public function resolveLocationByArea(mixed $local_area_id, bool $throwIfNotFound = true): array
    {
        $courier_area_id = CourierLocationMap::where('local_id', $local_area_id)
            ->where('local_type', Area::class)
            ->where('courier_name', CourierEnum::REDX->value)
            ->value('remote_id');

        if (! $courier_area_id) {

            if ($throwIfNotFound) {
                throw new CustomException('Area is not valid for redx', 404);
            }

            return [];
        }

        $courierArea = CourierArea::query()
            ->select('courier_id', 'name')
            ->where('courier_name', CourierEnum::REDX->value)
            ->where('courier_id', $courier_area_id)
            ->first();

        if (! $courierArea) {
            if ($throwIfNotFound) {
                throw new CustomException('Area mapping data not found for redx', 404);
            }

            return [];
        }

        return [
            'area_id' => (int) $courierArea->courier_id,
            'area_name' => $courierArea->name,
        ];
    }

    public function storeCreate(PickupPoint $pickup_points): mixed
    {
        throw_if(is_null($pickup_points->vendor->name ?? null), new CustomException('Vendor name not found!', 500));
        CourierFraudCheckerHelper::validatePhoneNumber($pickup_points->contact_number);

        $mapping = $this->resolveLocationByArea($pickup_points->area_id);

        $data = [
            'name' => config('app.platform_name')."({$pickup_points->vendor->name}-{$pickup_points->id})",
            'phone' => $pickup_points->contact_number,
            'address' => $pickup_points->address,
            'area_id' => $mapping['area_id'],
        ];

        $response = $this->redxRequest('post', '/pickup/store', $data);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $store_id = data_get($response->json(), 'id');

        return [
            'store_id' => $store_id,
            'provider_data' => $response->json(),
        ];
    }

    public function addParcel(array $data): mixed
    {
        $this->validateRequiredFields($data, [
            'recipient_name',
            'recipient_phone',
            'recipient_address',
            'recipient_area_id',
            'item_weight',
            'amount_to_collect',
            'store_id',
            'item_quantity',
        ]);

        CourierFraudCheckerHelper::validatePhoneNumber($data['recipient_phone']);

        $mapping = $this->resolveLocationByArea($data['recipient_area_id']);

        $payload = [
            'customer_name' => $data['recipient_name'],
            'customer_phone' => $data['recipient_phone'],
            'delivery_area' => $mapping['area_name'],
            'delivery_area_id' => $mapping['area_id'],
            'customer_address' => $data['recipient_address'],
            'cash_collection_amount' => (string) $data['amount_to_collect'],
            'parcel_weight' => $this->normalizeParcelWeight($data['item_weight']),
            'merchant_invoice_id' => $data['merchant_order_id'] ?? null,
            'instruction' => $data['special_instruction'] ?? null,
            'type' => $data['type'] ?? null,
            'value' => $data['amount_to_collect'],
            'parcel_details_json' => $this->buildParcelDetailsPayload($data),
            'pickup_store_id' => $data['store_id'],
        ];

        $payload = $this->filterPayload($payload);

        $response = $this->redxRequest('post', '/parcel', $payload);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $trackingId = data_get($response->json(), 'tracking_id');

        return [
            'consignment_id' => $trackingId,
            'tracking_id' => $trackingId,
            'order_status' => 'created',
        ];

    }

    private function buildParcelDetailsPayload(array $data): array
    {
        $details = array_filter([
            'item_quantity' => $data['item_quantity'] ?? null,
            'item_type' => $data['item_type'] ?? null,
            'delivery_type' => $data['delivery_type'] ?? null,
            'item_description' => $data['item_description'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($details === []) {
            return [];
        }

        return [$details];
    }

    private function normalizeParcelWeight(mixed $weight): string
    {
        if (! is_numeric($weight)) {
            return (string) $weight;
        }

        $numericWeight = (float) $weight;

        if ($numericWeight > 0 && $numericWeight <= 20) {
            $numericWeight *= 1000;
        }

        if ((float) (int) $numericWeight === $numericWeight) {
            return (string) (int) $numericWeight;
        }

        return (string) $numericWeight;
    }

    private function filterPayload(array $payload): array
    {
        return array_filter($payload, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
