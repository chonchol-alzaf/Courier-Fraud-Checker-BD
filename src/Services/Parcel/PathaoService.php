<?php
namespace Alzaf\BdCourier\Services\Parcel;

use Alzaf\BdCourier\Contracts\CourierServiceInterface;
use Alzaf\BdCourier\Supports\CourierFraudCheckerHelper;
use Alzaf\BdCourier\Traits\ParcelValidationTrait;
use Illuminate\Support\Facades\Http;

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
            'bd-courier.pathao.username',
            'bd-courier.pathao.password',
            'bd-courier.pathao.base_url',
            'bd-courier.pathao.client_id',
            'bd-courier.pathao.client_secret',
        ]);

        $this->username      = config('bd-courier.pathao.username');
        $this->password      = config('bd-courier.pathao.password');
        $this->base_url      = config('bd-courier.pathao.base_url');
        $this->client_id     = config('bd-courier.pathao.client_id');
        $this->client_secret = config('bd-courier.pathao.client_secret');
    }

    private function issueToken()
    {

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
            throw new \Exception('Token request failed');
        }

        $data = $response->json();

        return $data;
    }

    public function add($data)
    {
        $this->validation($data, [
            "store_id",
            "recipient_name",
            "recipient_phone",
            "recipient_address",
            //"delivery_type", //48 for Normal Delivery, 12 for On Demand Delivery
            //"item_type",     //1 for Document, 2 for Parcel
            "item_quantity",
            //"item_weight", //Minimum 0.5 KG to Maximum 10 kg. Weight of your parcel in kg
            "amount_to_collect",
        ]);

        return $this->issueToken();
    }

}
