<?php

namespace Alzaf\BdCourier\Contracts;

use App\Models\PickupPoint;
use Illuminate\Http\Client\Response;

interface ParcelServiceInterface
{
    public function errorThrow(Response $response): never;

    public function resolveLocationByArea(mixed $local_area_id, bool $throwIfNotFound = true): array;

    public function storeCreate(PickupPoint $pickup_points): mixed;

    public function addParcel(array $data): mixed;
}
