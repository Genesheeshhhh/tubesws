<?php

namespace App\Http\Requests;

use App\Models\Car;
use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{8,30}$/'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'message' => 'pesan',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inquiryAttributes(Car $car): array
    {
        return $this->safe()->only(['name', 'email', 'phone', 'message']) + [
            'car_id' => $car->id,
            'user_id' => $this->user()?->id,
        ];
    }
}
