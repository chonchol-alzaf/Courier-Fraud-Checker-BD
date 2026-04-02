<?php

namespace Alzaf\BdCourier\Traits;

use Alzaf\BdCourier\Exceptions\CourierValidationException;

trait ParcelValidationTrait
{
    public function validateRequiredFields(array $data, array $requiredFields): void
    {
        if ($requiredFields === []) {
            throw new CourierValidationException('Invalid data!', 422);
        }

        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $data) || $this->isEmptyRequiredValue($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if ($missingFields !== []) {
            throw new CourierValidationException($missingFields, 422);
        }
    }

    public function validation($data, $requiredFields): void
    {
        if (! is_array($data) || ! is_array($requiredFields)) {
            throw new \TypeError('Argument must be of the type array', 500);
        }

        $this->validateRequiredFields($data, $requiredFields);
    }

    private function isEmptyRequiredValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return is_array($value) && $value === [];
    }
}
