<?php

namespace App\Policies;

use App\Enums\CarStatus;
use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Car $car): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Car $car): bool
    {
        return $user->id === $car->seller_id;
    }

    public function delete(User $user, Car $car): bool
    {
        return $user->id === $car->seller_id && $car->status !== CarStatus::Sold;
    }
}
