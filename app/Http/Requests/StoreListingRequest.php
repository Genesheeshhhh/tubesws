<?php

namespace App\Http\Requests;

use App\Enums\CarBrand;
use App\Enums\CarCondition;
use App\Enums\CarStatus;
use App\Enums\Drivetrain;
use App\Enums\FuelType;
use App\Enums\Transmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:60', Rule::in(CarBrand::options())],
            'model' => ['required', 'string', 'max:80'],
            'variant' => ['nullable', 'string', 'max:80'],
            'year' => ['required', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'price' => ['required', 'integer', 'min:1000000', 'max:5000000000'],
            'mileage_min' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'mileage_max' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'transmission' => ['nullable', Rule::in(Transmission::options())],
            'drivetrain' => ['nullable', Rule::in(Drivetrain::options())],
            'fuel' => ['nullable', Rule::in(FuelType::options())],
            'condition' => ['required', Rule::in(CarCondition::options())],
            'color' => ['nullable', 'string', 'max:40'],
            'body_type' => ['nullable', 'string', 'max:60'],
            'city' => ['required', 'string', 'max:60'],
            'location' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(CarStatus::options())],
            'down_payment' => ['nullable', 'integer', 'min:0', 'max:5000000000'],
            'installment_monthly' => ['nullable', 'integer', 'min:100000', 'max:500000000'],
            'installment_term' => ['nullable', 'integer', 'min:1', 'max:120'],
            'discount_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'is_verified' => ['nullable', 'boolean'],
            'has_warranty' => ['nullable', 'boolean'],
            'booking_is_safe' => ['nullable', 'boolean'],
            'free_insurance' => ['nullable', 'boolean'],
            'home_test_drive' => ['nullable', 'boolean'],
            'free_maintenance' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['mileage_min', 'mileage_max'])) {
                    return;
                }

                $min = $this->integer('mileage_min');
                $max = $this->integer('mileage_max');

                if ($min !== null && $max !== null && $min > $max) {
                    $validator->errors()->add('mileage_max', 'Kilometer maksimum harus lebih besar dari kilometer minimum.');
                }

                if ($this->filled('installment_monthly') && ! $this->filled('installment_term')) {
                    $validator->errors()->add('installment_term', 'Tentukan jangka waktu cicilan bila cicilan dicantumkan.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'brand' => 'merek',
            'model' => 'tipe mobil',
            'variant' => 'varian',
            'year' => 'tahun pembuatan',
            'price' => 'harga',
            'mileage_min' => 'kilometer minimum',
            'mileage_max' => 'kilometer maksimum',
            'transmission' => 'transmisi',
            'drivetrain' => 'sistem penggerak',
            'fuel' => 'bahan bakar',
            'condition' => 'kondisi',
            'city' => 'kota',
            'location' => 'lokasi',
            'down_payment' => 'uang muka',
            'installment_monthly' => 'cicilan per bulan',
            'installment_term' => 'jangka waktu cicilan',
            'discount_amount' => 'nominal diskon',
            'photos' => 'foto',
            'photos.*' => 'foto',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_verified' => $this->boolean('is_verified'),
            'has_warranty' => $this->boolean('has_warranty'),
            'booking_is_safe' => $this->boolean('booking_is_safe'),
            'free_insurance' => $this->boolean('free_insurance'),
            'home_test_drive' => $this->boolean('home_test_drive'),
            'free_maintenance' => $this->boolean('free_maintenance'),
        ]);
    }

    /**
     * The listing columns this request owns, excluding the uploaded photos
     * which the photo pipeline stores separately.
     *
     * @return array<string, mixed>
     */
    public function listingAttributes(): array
    {
        return collect($this->validated())
            ->except('photos')
            ->all();
    }
}
