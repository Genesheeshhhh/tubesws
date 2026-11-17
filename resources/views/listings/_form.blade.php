@php
    use App\Enums\CarBrand;
    use App\Enums\CarCondition;
    use App\Enums\CarStatus;
    use App\Enums\Drivetrain;
    use App\Enums\FuelType;
    use App\Enums\Transmission;
@endphp

<div class="card p-6 sm:p-8">
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="field-brand" class="label">
                Merek <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">(wajib diisi)</span>
            </label>
            <select id="field-brand" name="brand" required class="field @error('brand') border-rose-300 @enderror">
                <option value="">Pilih merek</option>
                @foreach (CarBrand::options() as $brand)
                    <option value="{{ $brand }}" @selected(old('brand', $car->brand) === $brand)>{{ $brand }}</option>
                @endforeach
            </select>
            @error('brand')
                <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
            @else
                <span class="mt-1 block text-xs text-slate-500">Merek harus salah satu dari {{ count(CarBrand::options()) }} merek terdaftar.</span>
            @enderror
        </div>

        <x-field name="model" label="Tipe mobil" :value="$car->model" required placeholder="Avanza" />
        <x-field name="variant" label="Varian" :value="$car->variant" placeholder="1.3 G" />

        <x-field name="year" label="Tahun pembuatan" type="number" :value="$car->year" required />
        <x-field name="price" label="Harga (rupiah)" type="number" :value="$car->price" required
            hint="Tanpa titik atau tanda rupiah, contoh 150000000." />
    </div>

    <div class="mt-8 border-t border-slate-100 pt-6">
        <h2 class="text-sm font-bold text-slate-900">Kondisi dan spesifikasi</h2>

        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <div>
                <label for="field-condition" class="label">Kondisi</label>
                <select id="field-condition" name="condition" required class="field @error('condition') border-rose-300 @enderror">
                    @foreach (CarCondition::cases() as $condition)
                        <option value="{{ $condition->value }}" @selected(old('condition', $condition->value) === $condition->value)>
                            {{ $condition->label() }}
                        </option>
                    @endforeach
                </select>
                @error('condition')
                    <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="field-status" class="label">Status</label>
                <select id="field-status" name="status" required class="field">
                    @foreach (CarStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $status->value) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="field-transmission" class="label">Transmisi <span class="font-normal text-slate-400">(opsional)</span></label>
                <select id="field-transmission" name="transmission" class="field">
                    <option value="">Tidak dicantumkan</option>
                    @foreach (Transmission::cases() as $transmissionCase)
                        <option value="{{ $transmissionCase->value }}" @selected(old('transmission', $car->transmission?->value) === $transmissionCase->value)>
                            {{ $transmissionCase->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="field-drivetrain" class="label">Sistem penggerak <span class="font-normal text-slate-400">(opsional)</span></label>
                <select id="field-drivetrain" name="drivetrain" class="field @error('drivetrain') border-rose-300 @enderror">
                    <option value="">Tidak dicantumkan</option>
                    @foreach (Drivetrain::cases() as $drivetrainCase)
                        <option value="{{ $drivetrainCase->value }}" @selected(old('drivetrain', $car->drivetrain?->value) === $drivetrainCase->value)>
                            {{ $drivetrainCase->label() }}
                        </option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-slate-500">Pilih sesuai spesifikasi model dan varian; kosongkan jika belum yakin.</span>
                @error('drivetrain')
                    <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="field-fuel" class="label">Bahan bakar <span class="font-normal text-slate-400">(opsional)</span></label>
                <select id="field-fuel" name="fuel" class="field">
                    <option value="">Tidak dicantumkan</option>
                    @foreach (FuelType::cases() as $fuelCase)
                        <option value="{{ $fuelCase->value }}" @selected(old('fuel', $car->fuel?->value) === $fuelCase->value)>
                            {{ $fuelCase->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <x-field name="color" label="Warna" :value="$car->color" placeholder="Hitam" />
            <x-field name="body_type" label="Tipe bodi" :value="$car->body_type" placeholder="MPV" />
        </div>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <x-field name="mileage_min" label="Kilometer minimum" type="number" :value="$car->mileage_min" />
            <x-field name="mileage_max" label="Kilometer maksimum" type="number" :value="$car->mileage_max" />
        </div>
    </div>

    <div class="mt-8 border-t border-slate-100 pt-6">
        <h2 class="text-sm font-bold text-slate-900">Lokasi</h2>

        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <div>
                <label for="field-city" class="label">Kota</label>
                <input id="field-city" name="city" type="text" required list="kota-iklan" value="{{ old('city', $car->city) }}"
                    class="field @error('city') border-rose-300 @enderror">
                <datalist id="kota-iklan">
                    @foreach ($cities as $city)
                        <option value="{{ $city }}"></option>
                    @endforeach
                </datalist>
                @error('city')
                    <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </div>

            <x-field name="location" label="Alamat lengkap" :value="$car->location" required
                placeholder="Medan Timur, Jalan Bakaran Batu" />
        </div>
    </div>

    <div class="mt-8 border-t border-slate-100 pt-6">
        <h2 class="text-sm font-bold text-slate-900">Keterangan</h2>

        <div class="mt-4">
            <label for="field-description" class="label">Deskripsi</label>
            <textarea id="field-description" name="description" rows="6"
                class="field @error('description') border-rose-300 @enderror"
                placeholder="Ceritakan kondisi unit, riwayat servis, dan alasan dijual.">{{ old('description', $car->description) }}</textarea>
            @error('description')
                <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
            @enderror
        </div>

        <fieldset class="mt-5">
            <legend class="label">Keunggulan yang ditawarkan</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ([
                    'has_warranty' => 'Garansi',
                    'booking_is_safe' => 'Booking aman',
                    'free_insurance' => 'Asuransi gratis',
                    'home_test_drive' => 'Test drive ke rumah',
                    'free_maintenance' => 'Servis gratis',
                    'is_verified' => 'Penjual terverifikasi',
                ] as $name => $label)
                    <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-700 hover:border-brand-300">
                        <input type="checkbox" name="{{ $name }}" value="1"
                            @checked(old($name, $car->{$name}))
                            class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>
    </div>

    <div class="mt-8 border-t border-slate-100 pt-6">
        <h2 class="text-sm font-bold text-slate-900">Skema cicilan <span class="font-normal text-slate-400">(opsional)</span></h2>
        <p class="prose-note mt-1">Bila diisi, informasi cicilan akan ditampilkan pada halaman iklan.</p>

        <div class="mt-4 grid gap-5 sm:grid-cols-3">
            <x-field name="down_payment" label="Uang muka (Rp)" type="number" :value="$car->down_payment" />
            <x-field name="installment_monthly" label="Cicilan per bulan (Rp)" type="number" :value="$car->installment_monthly" />
            <x-field name="installment_term" label="Tenor (bulan)" type="number" :value="$car->installment_term" />
        </div>

        <div class="mt-5">
            <x-field name="discount_amount" label="Nominal diskon (Rp)" type="number" :value="$car->discount_amount" />
        </div>
    </div>
</div>
