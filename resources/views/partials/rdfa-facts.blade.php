{{-- RDFa facts shared by every page that annotates a listing. --}}
<meta property="name" content="{{ $car->label }}">
<div class="sr-only" property="brand" typeof="Brand">
    <meta property="name" content="{{ $car->brand }}">
</div>
<meta property="model" content="{{ $car->model }}">
<meta property="vehicleModelDate" content="{{ $car->year }}" datatype="xsd:integer">
<meta property="bodyType" content="{{ $car->bodyType }}">
<meta property="vehicleTransmission" content="{{ $car->transmission }}">
<meta property="driveWheelConfiguration" content="{{ $car->drivetrain }}">
<meta property="fuelType" content="{{ $car->fuel }}">
@if (filled($car->imageUrl))
    <meta property="image" content="{{ $car->imageUrl }}">
@endif
<div class="sr-only" property="offers" typeof="Offer">
    <meta property="price" content="{{ $car->price }}" datatype="xsd:integer">
    <meta property="priceCurrency" content="{{ config('semantic.currency') }}">
    <meta property="availability" content="https://schema.org/InStock">
    <meta property="areaServed" content="{{ $car->location }}">
</div>
