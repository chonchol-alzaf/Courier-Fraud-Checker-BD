<?php

namespace Alzaf\BdCourier\Exceptions;

use Exception;
use Throwable;

class CourierValidationException extends Exception
{
    public function __construct($message = '', $code = 0, ?Throwable $previous = null)
    {
        if (is_array($message)) {
            $requiredColumnsImplode = implode(', ', $message);
            $label = count($message) > 1 ? 'fields are required' : 'field is required';

            parent::__construct("{$requiredColumnsImplode} {$label}", $code, $previous);
        } else {
            parent::__construct($message, $code, $previous);
        }
    }
}
