<?php
namespace Alzaf\BdCourier\Traits;

use Alzaf\BdCourier\Exceptions\CourierValidationException;

trait ParcelValidationTrait
{
    
    public function validation($data, $requiredFields)
    {
        if (! is_array($data) || ! is_array($requiredFields)) {
            throw new \TypeError("Argument must be of the type array", 500);
        }

        if (! count($data) || ! count($requiredFields)) {
            throw new CourierValidationException("Invalid data!", 422);
        }

        $requiredColumns = array_diff($requiredFields, array_keys($data));
        if (count($requiredColumns)) {
            throw new CourierValidationException($requiredColumns, 422);
        }

        foreach ($requiredFields as $filed) {
            if (isset($data[$filed]) && empty($data[$filed])) {
                throw new CourierValidationException("$filed is required", 422);
            }
        }
    }
}
