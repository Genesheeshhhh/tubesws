<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$c = App\Models\Car::where('source','olx-import')->first();
var_dump($c->transmission?->value, $c->drivetrain?->value, $c->fuel?->value);
