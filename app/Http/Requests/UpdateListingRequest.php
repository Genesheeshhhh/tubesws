<?php

namespace App\Http\Requests;

use App\Models\Car;

class UpdateListingRequest extends StoreListingRequest
{
    public function authorize(): bool
    {
        $car = $this->route('car');

        return $car instanceof Car && $this->user()?->can('update', $car) === true;
    }
}
