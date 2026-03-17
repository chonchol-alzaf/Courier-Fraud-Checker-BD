<?php
namespace Alzaf\BdCourier\Services\Parcel;

use Alzaf\BdCourier\Contracts\CourierServiceInterface;
use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Traits\ParcelValidationTrait;
use App\Models\CourierArea;
use App\Models\CourierZone;
use App\Models\PickupPoints;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Shope\Core\Exceptions\CustomException;

class PathaoService implements CourierServiceInterface
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

        $this->username      = config('bd-courier.pathao.outgoing.username');
        $this->password      = config('bd-courier.pathao.outgoing.password');
        $this->base_url      = config('bd-courier.pathao.outgoing.base_url');
        $this->client_id     = config('bd-courier.pathao.outgoing.client_id');
        $this->client_secret = config('bd-courier.pathao.outgoing.client_secret');
    }

    private function issueToken()
    {

        $cache_key = "patha:access_key";

        $access_token = Cache::get($cache_key);

        if ($access_token) {
            return $access_token;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->base_url . '/aladdin/api/v1/issue-token', [
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type'    => 'password',
            'username'      => $this->username,
            'password'      => $this->password,
        ]);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $access_token = $response->json("access_token");
        $expires_in   = $response->json("expires_in");

        Cache::put($cache_key, $access_token, now()->addSeconds($expires_in - 200));

        return $access_token;
    }

    private function errorThrow($response)
    {
        $errors  = data_get($response->json(), "errors");
        $message = data_get($response->json(), "message") ?? 'Something goes wrong!';
        $code    = data_get($response->json(), "code") ?? 500;

        if ($code == 422) {
            throw ValidationException::withMessages($errors);
        }

        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }
        throw new CustomException($message, $code);
    }

    private function resolveStoreLocation(PickupPoints $pickup_points)
    {
        $zone_id = CourierArea::where("courier_id", $pickup_points->area_id)
            ->value("zone_id");
        $city_id = CourierZone::where("courier_id", $zone_id)->value("city_id");

        return ['zone_id' => $zone_id, "city_id" => $city_id, 'area_id' => $pickup_points->area_id];
    }

    public function storeCreate(PickupPoints $pickup_points)
    {

        $access_token = $this->issueToken();

        throw_if(is_null($pickup_points->vendor->name ?? null), new CustomException("Vendor name not found!", 500));
        $mapping = $this->resolveStoreLocation($pickup_points);

        $data = [
            "name"           => $pickup_points->vendor->name . "-" . Str::ulid(),
            "contact_name"   => $pickup_points->contact_name,
            "contact_number" => $pickup_points->contact_number,
            "address"        => $pickup_points->address,
            "city_id"        => $mapping['city_id'],
            "zone_id"        => $mapping['zone_id'],
            "area_id"        => $mapping['area_id'],
        ];

        $response = Http::withToken($access_token)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->base_url . '/aladdin/api/v1/stores', $data);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $store_id = data_get($response->json(), 'data.store_id');
        return [
            "store_id"      => $store_id,
            "provider_data" => data_get($response->json(), 'data'),
        ];
    }

    public function cityList()
    {
        $cache_key = "pathoa:city-lists";

        $city_lists = Cache::get($cache_key);

        if ($city_lists) {
            return $city_lists;
        }

        $access_token = $this->issueToken();

        $response = Http::withToken($access_token)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ])->get($this->base_url . '/aladdin/api/v1/city-list', []);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $data = data_get($response->json(), 'data.data');

        $city_lists = collect($data)->map(function ($item) {
            return [
                "id"   => $item['city_id'] ?? null,
                "name" => $item['city_name'] ?? null,
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

        $access_token = $this->issueToken();

        $response = Http::withToken($access_token)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ])->get($this->base_url . "/aladdin/api/v1/cities/{$city_id}/zone-list", []);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $data = data_get($response->json(), 'data.data');

        $zone_lists = collect($data)->map(function ($item) {
            return [
                "id"   => $item['zone_id'] ?? null,
                "name" => $item['zone_name'] ?? null,
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

        $access_token = $this->issueToken();

        $response = Http::withToken($access_token)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ])->get($this->base_url . "/aladdin/api/v1/zones/{$zone_id}/area-list", []);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }

        $data = data_get($response->json(), 'data.data');

        $area_lists = collect($data)->map(function ($item) {
            return [
                "id"                      => $item['area_id'] ?? null,
                "name"                    => $item['area_name'] ?? null,
                'home_delivery_available' => $item['home_delivery_available'] ?? false,
                'pickup_available'        => $item['pickup_available'] ?? false,
            ];
        });

        Cache::put($cache_key, $area_lists, now()->addMinutes(5));

        return $area_lists;
    }

    public function addParcel($data)
    {
        $this->validation($data, [
            "store_id",
            "recipient_name",
            "recipient_phone",
            "recipient_address",
            "delivery_type", //48 for Normal Delivery, 12 for On Demand Delivery
            "item_type",     //1 for Document, 2 for Parcel
            "item_quantity",
            "item_weight", //Minimum 0.5 KG to Maximum 10 kg. Weight of your parcel in kg
            "amount_to_collect",
        ]);

        $access_token = $this->issueToken();

        $response = Http::withToken($access_token)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
            ])->post($this->base_url . "/aladdin/api/v1/orders", $data);

        if (! $response->successful()) {
            $this->errorThrow($response);
        }


          $response = data_get($response->json(), 'data');
       
        return $response;

    }

}
