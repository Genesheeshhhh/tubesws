<?php

namespace App\Models;

use App\Enums\CarCondition;
use App\Enums\CarStatus;
use App\Enums\Drivetrain;
use App\Enums\FuelType;
use App\Enums\Transmission;
use Database\Factories\CarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'seller_id',
    'slug',
    'brand',
    'model',
    'variant',
    'year',
    'price',
    'mileage_min',
    'mileage_max',
    'transmission',
    'drivetrain',
    'fuel',
    'condition',
    'color',
    'body_type',
    'city',
    'location',
    'description',
    'status',
    'is_verified',
    'has_warranty',
    'booking_is_safe',
    'free_insurance',
    'home_test_drive',
    'free_maintenance',
    'down_payment',
    'installment_monthly',
    'installment_term',
    'discount_amount',
    'posted_at',
    'sold_at',
    'source',
    'source_reference',
])]
#[RouteKey('slug')]
class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'price' => 'integer',
            'mileage_min' => 'integer',
            'mileage_max' => 'integer',
            'transmission' => Transmission::class,
            'drivetrain' => Drivetrain::class,
            'fuel' => FuelType::class,
            'condition' => CarCondition::class,
            'status' => CarStatus::class,
            'is_verified' => 'boolean',
            'has_warranty' => 'boolean',
            'booking_is_safe' => 'boolean',
            'free_insurance' => 'boolean',
            'home_test_drive' => 'boolean',
            'free_maintenance' => 'boolean',
            'down_payment' => 'integer',
            'installment_monthly' => 'integer',
            'installment_term' => 'integer',
            'discount_amount' => 'integer',
            'view_count' => 'integer',
            'posted_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    /**
     * A seller-independent car always has a usable mileage midpoint, which the
     * catalogue and the semantic graph both display.
     */
    protected function mileageKm(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->mileage_min === null && $this->mileage_max === null) {
                return null;
            }

            return (int) round(($this->mileage_min ?? 0) / 2 + ($this->mileage_max ?? 0) / 2);
        });
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            $name = trim($this->brand.' '.$this->model);

            return $this->variant ? $name.' '.$this->variant : $name;
        })->shouldCache();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(CarImage::class)->orderBy('id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(CarInquiry::class)->latest();
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * The stable global identifier used in the RDF graph and JSON-LD output.
     */
    public function uri(): string
    {
        return $this->offerUri();
    }

    public function offerUri(): string
    {
        return rtrim(config('app.url'), '/').'/mobil/'.$this->slug;
    }

    /**
     * Whether this car is in a buyer's saved list. The relation must already be
     * eager loaded; callers that want the answer for many cars should load
     * favoritedBy rather than calling this per row.
     */
    public function isFavoritedBy(?User $user): bool
    {
        return $user !== null
            && $this->relationLoaded('favoritedBy')
            && $this->favoritedBy->contains($user->id);
    }

    #[Scope]
    protected function available(Builder $query): Builder
    {
        return $query->where('status', CarStatus::Available->value);
    }

    #[Scope]
    protected function visibleToPublic(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CarStatus::Available->value,
            CarStatus::Sold->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filtered(Builder $query, array $filters): Builder
    {
        if ($term = $filters['q'] ?? null) {
            $query->where(function (Builder $inner) use ($term): void {
                $like = '%'.mb_strtolower($term).'%';

                $inner->whereRaw('LOWER(cars.brand) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(cars.model) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(cars.variant) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(cars.location) LIKE ?', [$like]);
            });
        }

        if ($brands = $filters['brand'] ?? []) {
            $query->whereIn('cars.brand', $brands);
        }

        if ($cities = $filters['city'] ?? []) {
            $query->whereIn('cars.city', $cities);
        }

        if (isset($filters['min_price'])) {
            $query->where('cars.price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('cars.price', '<=', $filters['max_price']);
        }

        if (isset($filters['min_year'])) {
            $query->where('cars.year', '>=', $filters['min_year']);
        }

        if (isset($filters['max_mileage'])) {
            $query->where(function (Builder $inner) use ($filters): void {
                $inner->where('cars.mileage_max', '<=', $filters['max_mileage'])
                    ->orWhereNull('cars.mileage_max');
            });
        }

        $transmissions = $filters['transmission'] ?? [];

        if ($transmissions !== []) {
            $query->whereIn('cars.transmission', $transmissions);
        }

        $drivetrains = $filters['drivetrain'] ?? [];

        if ($drivetrains !== []) {
            $query->whereIn('cars.drivetrain', $drivetrains);
        }

        $fuels = $filters['fuel'] ?? [];

        if ($fuels !== []) {
            $query->whereIn('cars.fuel', $fuels);
        }

        if ($filters['verified_only'] ?? false) {
            $query->where('cars.is_verified', true);
        }

        if ($filters['with_financing'] ?? false) {
            $query->whereNotNull('cars.installment_monthly');
        }

        return $query;
    }
}
