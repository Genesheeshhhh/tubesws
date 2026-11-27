<?php

namespace App\Services\Chatbot;

use App\Services\Catalog\CarCatalog;
use App\Services\Catalog\CarListing;
use Illuminate\Support\Collection;

/**
 * A rule-based assistant that answers catalogue questions without calling any
 * external service. Intent parsing happens against the vocabulary that is
 * actually present in the graph, so the assistant only offers filters the
 * catalogue can satisfy.
 */
class CarChatbot
{
    /** @var list<string> */
    private const SUGGESTIONS = [
        'Mobil di bawah 150 juta',
        'SUV matic bisa dicicil',
        'Mobil termurah',
        'Toyota Avanza',
        'Regex: Toyota|Brio',
        'Ada berapa mobil?',
    ];

    /** @var list<string> */
    private const GREETINGS = [
        'halo', 'hallo', 'hai', 'hi', 'hello', 'hey', 'pagi', 'siang', 'sore',
        'malam', 'assalamualaikum', 'assalamu', 'alaikum', 'warahmatullahi',
        'wabarakatuh', 'semuanya',
    ];

    /** @var list<string> */
    private const THANKS = [
        'terima kasih', 'terimakasih', 'makasih', 'thanks', 'thank you', 'tq',
    ];

    /** @var list<string> */
    private const HELP = [
        'bantuan', 'help', 'bisa apa', 'bisa bantu', 'cara pakai', 'cara pesan',
        'apa yang bisa', 'fitur',
    ];

    /** @var list<string> */
    private const COUNT = [
        'berapa mobil', 'berapa jumlah', 'ada berapa', 'jumlah mobil',
        'total mobil', 'berapa iklan',
    ];

    /** @var list<string> */
    private const FINANCING = [
        'cicilan', 'dicicil', ' bisa cicil', 'cicil', 'kredit', 'dp',
        'uang muka', 'down payment',
    ];

    /** @var array<string, list<string>> */
    private const SORTS = [
        'harga_terendah' => ['termurah', 'paling murah', 'harga terendah', 'murah'],
        'harga_tertinggi' => ['termahal', 'paling mahal', 'harga tertinggi'],
        'tahun_terbaru' => ['tahun terbaru', 'paling baru', 'terbaru'],
    ];

    /** @var array<string, list<string>> */
    private const BRAND_ALIASES = [
        'mercedes-benz' => ['mercy', 'mercedez', 'mercedes'],
        'volkswagen' => ['vw'],
        'chevrolet' => ['chevy'],
        'mitsubishi' => ['mitsu'],
    ];

    /** @var array<string, list<string>> */
    private const TRANSMISSION_ALIASES = [
        'matic' => ['automatic', 'otomatis', 'matik'],
        'manual' => ['mt'],
    ];

    /** @var array<string, list<string>> */
    private const DRIVETRAIN_ALIASES = [
        'fwd' => ['front wheel drive'],
        'rwd' => ['rear wheel drive'],
        'awd' => ['4wd', 'all wheel drive'],
    ];

    /** @var array<string, list<string>> */
    private const BODY_TYPE_ALIASES = [
        'city car' => ['citycar'],
        'pick up' => ['pickup'],
        'pickup' => ['pick up'],
    ];

    /** @var array<string, list<string>> */
    private const FUEL_ALIASES = [
        'diesel' => ['solar'],
        'listrik' => ['elektrik', 'electric'],
        'bensin' => ['petrol', 'gasoline'],
    ];

    public function __construct(private readonly CarCatalog $catalog) {}

    /**
     * @return array{reply: string, cars: list<array<string, mixed>>, filters: array<string, mixed>, url: ?string, suggestions: list<string>}
     */
    public function reply(string $message): array
    {
        [$withoutRegex, $regexPattern] = $this->extractRegex($message);
        $text = $this->normalize($withoutRegex);

        if ($text === '' && $regexPattern === null) {
            return $this->helpReply();
        }

        if ($this->isGreeting($text)) {
            return $this->greetingReply();
        }

        if ($this->containsAny($text, self::THANKS)) {
            return $this->thanksReply();
        }

        if ($this->containsAny($text, self::HELP)) {
            return $this->helpReply();
        }

        if ($this->containsAny($text, self::COUNT)) {
            return $this->statisticsReply();
        }

        return $this->searchReply($text, $regexPattern);
    }

    /**
     * Build the answer payload from filters that have been validated by
     * CarCatalog::filtersFrom(), so no filter can bypass the catalogue's own
     * normalisation and search.
     *
     * @param  array<string, mixed>  $filters
     * @return array{reply: string, cars: list<array<string, mixed>>, filters: array<string, mixed>, url: ?string, suggestions: list<string>}
     */
    public function replyFromFilters(array $filters, string $answer = ''): array
    {
        $filters = $this->catalog->filtersFrom($filters);

        if (($filters['regex_error'] ?? false) === true) {
            return $this->payload(
                'Pola regex tidak valid. Coba contoh: "regex Toyota|Brio". Pencarian kata kunci dilakukan tanpa regex.',
                [],
                $filters,
            );
        }

        // The search bar explicitly uses resolveSearch() to show its correction.
        // Do the same here so the chatbot's q, reply and catalogue link all use
        // the nearest known vocabulary token instead of the visitor's typo.
        $correctionNote = '';

        if (filled($filters['q']) && ($filters['regex'] ?? false) !== true) {
            $original = (string) $filters['q'];
            $resolved = $this->catalog->resolveSearch($original);

            if ($resolved['corrected'] === true && $resolved['resolved'] !== null) {
                $filters['q'] = $resolved['resolved'];
                $correctionNote = 'Saya artikan "'.$original.'" sebagai "'.$resolved['resolved'].'". ';
            }
        }

        $matches = $this->catalog->search($filters);
        $prefix = trim($answer) === '' ? '' : rtrim(trim($answer), " \t\n\r\0\x0B.").'. ';

        if ($matches->isEmpty()) {
            // Do not reuse generated prose here: there are no facts to ground it.
            return $this->payload(
                $correctionNote.'Belum ada mobil yang cocok dengan permintaan itu. Coba naikkan budget, ganti transmisi, atau hapus salah satu filter.',
                filters: $filters,
            );
        }

        $top = $matches->take(5)->values();
        $description = $this->describe($filters);
        $reply = $prefix.$correctionNote.'Saya menemukan '.number_format($matches->count(), 0, ',', '.').' mobil'
            .($description !== '' ? ' ('.$description.')' : '').'.';

        if ($matches->count() > $top->count()) {
            $reply .= ' Berikut '.$top->count().' pilihan teratas.';
        }

        return $this->payload(
            $reply,
            $top->map(fn (CarListing $car): array => $this->carPayload($car))->all(),
            $filters,
            route('mobil.index', $this->queryParams($filters)),
        );
    }

    /**
     * A short, fact-free answer (greeting or clarification) that still uses
     * the normal response shape and suggestion list.
     */
    public function answerOnly(string $reply): array
    {
        return $this->payload(
            $reply === '' ? 'Saya belum bisa menjawab itu. Coba sebutkan budget atau merek mobil.' : $reply,
            [],
            $this->emptyFilters(),
        );
    }

    /**
     * @return array{reply: string, cars: list<array<string, mixed>>, filters: array<string, mixed>, url: ?string, suggestions: list<string>}
     */
    private function searchReply(string $text, ?string $regexPattern = null): array
    {
        $cars = $this->catalog->all();
        $filters = $this->emptyFilters();
        $consumed = [];

        if ($regexPattern !== null) {
            $filters['regex'] = true;
        }

        $sort = $this->detectSort($text);

        if ($sort !== null) {
            $filters['sort'] = $sort;
            $consumed = array_merge($consumed, $this->matchedWords($text, self::SORTS[$sort]));
        }

        if ($this->containsAny($text, self::FINANCING)) {
            $filters['with_financing'] = true;
            $consumed = array_merge($consumed, $this->matchedWords($text, self::FINANCING));
        }

        [$minPrice, $maxPrice, $budgetWords] = $this->budget($text);
        $filters['min_price'] = $minPrice;
        $filters['max_price'] = $maxPrice;
        $consumed = array_merge($consumed, $budgetWords);

        if (preg_match('/\b(19|20)\d{2}\b/', $text, $year) === 1) {
            $filters['min_year'] = (int) $year[0];
            $consumed[] = $year[0];
        }

        [$maxMileage, $mileageWords] = $this->mileage($text);
        $filters['max_mileage'] = $maxMileage;
        $consumed = array_merge($consumed, $mileageWords);

        $brand = $this->detectOption($text, $cars->pluck('brand'), self::BRAND_ALIASES);

        if ($brand !== null) {
            $filters['brand'] = [$brand];
            $consumed[] = $brand;
        }

        $bodyType = $this->detectOption($text, $cars->pluck('bodyType'), self::BODY_TYPE_ALIASES);

        if ($bodyType !== null) {
            $filters['body_type'] = [$bodyType];
            $consumed[] = $bodyType;
        }

        $transmission = $this->detectOption($text, $cars->pluck('transmission'), self::TRANSMISSION_ALIASES);

        if ($transmission !== null) {
            $filters['transmission'] = [$transmission];
            $consumed[] = $transmission;
        }

        $drivetrain = $this->detectOption($text, $cars->pluck('drivetrain'), self::DRIVETRAIN_ALIASES);

        if ($drivetrain !== null) {
            $filters['drivetrain'] = [$drivetrain];
            $consumed[] = $drivetrain;
        }

        $fuel = $this->detectOption($text, $cars->pluck('fuel'), self::FUEL_ALIASES);

        if ($fuel !== null) {
            $filters['fuel'] = [$fuel];
            $consumed[] = $fuel;
        }

        $location = $this->detectLocation($text, $cars->pluck('location'));

        if ($location !== null) {
            $filters['location'] = [$location];
            $consumed[] = $location;
        }

        // An explicit regex pattern replaces free-text keywords: it is already
        // the exact search term the catalogue should hand to Fuseki REGEX.
        $filters['q'] = $regexPattern ?? $this->keywords($text, $consumed, $this->hasStructuredFilters($filters), $cars);

        return $this->replyFromFilters($filters);
    }

    /**
     * @return array{reply: string, cars: list<array<string, mixed>>, filters: array<string, mixed>, url: ?string, suggestions: list<string>}
     */
    private function greetingReply(): array
    {
        return $this->payload('Halo! Saya asisten katalog mobil bekas Medan. Mau cari mobil dengan budget berapa, atau ada merek/tipe favorit?');
    }

    /**
     * @return array{reply: string, cars: list<array<string, mixed>>, filters: array<string, mixed>, url: ?string, suggestions: list<string>}
     */
    private function helpReply(): array
    {
        return $this->payload('Saya asisten offline katalog Mobil Bekas Medan. Tanyakan hal seperti "mobil di bawah 150 juta", "SUV matic bisa dicicil", "Toyota Avanza", atau "mobil termurah". Saya menjawab dari katalog lokal tanpa internet.');
    }

    /**
     * @return array{reply: string, cars: list<array<string, mixed>>, filters: array<string, mixed>, url: ?string, suggestions: list<string>}
     */
    private function thanksReply(): array
    {
        return $this->payload('Sama-sama! Kalau mau, saya bisa bantu cari mobil lain berdasarkan budget, merek, transmisi, atau lokasi.');
    }

    /**
     * @return array{reply: string, cars: list<array<string, mixed>>, filters: array<string, mixed>, url: ?string, suggestions: list<string>}
     */
    private function statisticsReply(): array
    {
        $statistics = $this->catalog->statistics();

        if ($statistics['total'] === 0) {
            return $this->payload('Belum ada iklan mobil di katalog saat ini.');
        }

        return $this->payload(sprintf(
            'Saat ini ada %s iklan mobil bekas dari %d merek di %d lokasi. Harga mulai %s sampai %s.',
            number_format($statistics['total'], 0, ',', '.'),
            $statistics['brands'],
            $statistics['locations'],
            $this->rupiah($statistics['lowest']),
            $this->rupiah($statistics['highest']),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $cars
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $suggestions
     * @return array{reply: string, cars: list<array<string, mixed>>, filters: array<string, mixed>, url: ?string, suggestions: list<string>}
     */
    private function payload(string $reply, array $cars = [], array $filters = [], ?string $url = null, ?array $suggestions = null): array
    {
        return [
            'reply' => $reply,
            'cars' => $cars,
            'filters' => $filters,
            'url' => $url,
            'suggestions' => $suggestions ?? self::SUGGESTIONS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function carPayload(CarListing $car): array
    {
        return [
            'id' => $car->id,
            'label' => $car->label,
            'price' => $car->priceLabel(),
            'summary' => $car->summary(),
            'location' => $car->location,
            'installment' => $car->installmentLabel(),
            'image' => filled($car->imageUrl) ? $car->imageUrl : asset('images/car-placeholder.svg'),
            'url' => route('mobil.show', ['car' => $car->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFilters(): array
    {
        return [
            'q' => null,
            'brand' => [],
            'body_type' => [],
            'transmission' => [],
            'drivetrain' => [],
            'fuel' => [],
            'location' => [],
            'min_price' => null,
            'max_price' => null,
            'min_year' => null,
            'max_mileage' => null,
            'with_financing' => false,
            'with_image' => false,
            'regex' => false,
            'regex_error' => false,
            'sort' => 'terbaru',
        ];
    }

    /**
     * Pull an explicit regex pattern out of the raw message before normalize()
     * removes metacharacters such as | ( ) [ ] ^ $.
     *
     * Accepted forms: "regex Toyota|Avanza", 'regex: "Toyota|Avanza"',
     * "pola Toyota", "pattern: Brio".
     *
     * @return array{string, string|null}
     */
    private function extractRegex(string $message): array
    {
        if (preg_match(
            '/(?:regexp?|regular expression|pola|pattern)\s*[:=]?\s*(?:"([^"]+)"|\'([^\']+)\'|`([^`]+)`|([^\s]+))/iu',
            $message,
            $matches,
            PREG_OFFSET_CAPTURE,
        ) !== 1) {
            return [$message, null];
        }

        $fullMatch = (string) $matches[0][0];
        $offset = (int) $matches[0][1];
        $pattern = '';

        foreach (array_slice($matches, 1) as $group) {
            if (isset($group[0]) && $group[0] !== '') {
                $pattern = trim((string) $group[0]);
                break;
            }
        }

        $pattern = rtrim($pattern, " \t\n\r\0\x0B?.,;:!\"'");
        $rest = substr($message, 0, $offset).substr($message, $offset + strlen($fullMatch));

        return [$rest, $pattern === '' ? null : $pattern];
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s.,\-]/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        return trim($text);
    }

    private function isGreeting(string $text): bool
    {
        $words = preg_split('/\s+/', $text) ?: [];

        if ($words === []) {
            return false;
        }

        foreach ($words as $word) {
            if (! in_array($word, self::GREETINGS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function matchedWords(string $text, array $needles): array
    {
        $words = [];

        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                $words = array_merge($words, preg_split('/\s+/', trim($needle)) ?: []);
            }
        }

        return array_values(array_unique(array_filter($words, fn (string $word): bool => $word !== '')));
    }

    private function detectSort(string $text): ?string
    {
        foreach (self::SORTS as $sort => $needles) {
            if ($this->containsAny($text, $needles)) {
                return $sort;
            }
        }

        return null;
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: list<string>}
     */
    private function budget(string $text): array
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:-|sampai|s\/d)\s*(\d+(?:[.,]\d+)?)\s*(juta|jt|jtan|miliar|m)\b/u', $text, $match) === 1) {
            return [
                $this->toRupiah($match[1], $match[3]),
                $this->toRupiah($match[2], $match[3]),
                [$match[1], $match[2], $match[3]],
            ];
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(juta|jt|jtan|miliar|m)\b/u', $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [null, null, []];
        }

        $amount = $this->toRupiah($match[1][0], $match[2][0]);
        $before = trim(substr($text, 0, (int) $match[0][1]));
        $words = [$match[1][0], $match[2][0]];

        if (preg_match('/(di atas|diatas|minimal|min|mulai|lebih dari|ke atas)\s*$/u', $before) === 1) {
            return [$amount, null, $words];
        }

        return [null, $amount, $words];
    }

    private function toRupiah(string $number, string $unit): int
    {
        $value = (float) str_replace(',', '.', $number);
        $multiplier = $unit === 'miliar' || $unit === 'm' ? 1_000_000_000 : 1_000_000;

        return (int) round($value * $multiplier);
    }

    /**
     * @return array{0: ?int, 1: list<string>}
     */
    private function mileage(string $text): array
    {
        if (preg_match('/(\d[\d\.]*)\s*(ribu|rb|km|kilometer)\b/u', $text, $match) !== 1) {
            return [null, []];
        }

        $value = (int) str_replace('.', '', $match[1]);

        if (in_array($match[2], ['ribu', 'rb'], true)) {
            $value *= 1_000;
        }

        return [$value, [$match[1], $match[2], 'km', 'kilometer', 'ribu', 'rb']];
    }

    /**
     * @param  Collection<int, string>  $values
     * @param  array<string, list<string>>  $aliases
     */
    private function detectOption(string $text, Collection $values, array $aliases = []): ?string
    {
        $values = $values
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        foreach ($values as $value) {
            if (str_contains($text, mb_strtolower($value))) {
                return $value;
            }
        }

        foreach ($aliases as $canonical => $needles) {
            if (! $this->containsAny($text, $needles)) {
                continue;
            }

            $match = $values->first(fn (string $value): bool => str_contains(mb_strtolower($value), $canonical));

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, string>  $locations
     */
    private function detectLocation(string $text, Collection $locations): ?string
    {
        foreach ($locations as $location) {
            $location = trim((string) $location);
            $lowercase = mb_strtolower($location);

            if ($location === '' || $lowercase === '') {
                continue;
            }

            if (str_contains($text, $lowercase)) {
                return $location;
            }

            foreach (preg_split('/\s+/', $lowercase) ?: [] as $word) {
                if (mb_strlen($word) >= 5 && $word !== 'medan' && str_contains($text, $word)) {
                    return $location;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $consumed
     * @param  Collection<int, CarListing>  $cars
     */
    private function keywords(string $text, array $consumed, bool $hasStructuredFilters, Collection $cars): ?string
    {
        $tokens = preg_split('/[^a-z0-9]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopwords = $this->stopwords();
        $consumedWords = [];

        foreach ($consumed as $word) {
            foreach (preg_split('/[^a-z0-9]+/u', mb_strtolower($word), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $consumedWords[$token] = true;
            }
        }

        $tokens = array_values(array_filter(
            $tokens,
            function (string $token) use ($stopwords, $consumedWords, $hasStructuredFilters, $cars): bool {
                if (isset($stopwords[$token]) || isset($consumedWords[$token])) {
                    return false;
                }

                if (mb_strlen($token) < 2) {
                    return false;
                }

                return ! $hasStructuredFilters || $this->appearsInCatalogue($token, $cars);
            },
        ));

        return $tokens === [] ? null : implode(' ', $tokens);
    }

    /**
     * @param  Collection<int, CarListing>  $cars
     */
    private function appearsInCatalogue(string $token, Collection $cars): bool
    {
        return $cars->contains(fn (CarListing $car): bool => str_contains($car->searchText(), $token));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasStructuredFilters(array $filters): bool
    {
        return $filters['brand'] !== []
            || $filters['body_type'] !== []
            || $filters['transmission'] !== []
            || $filters['drivetrain'] !== []
            || $filters['fuel'] !== []
            || $filters['location'] !== []
            || $filters['min_price'] !== null
            || $filters['max_price'] !== null
            || $filters['min_year'] !== null
            || $filters['max_mileage'] !== null
            || $filters['with_financing'] === true
            || $filters['with_image'] === true;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function queryParams(array $filters): array
    {
        $params = [];

        if (filled($filters['q'])) {
            $params['q'] = $filters['q'];
        }

        foreach (['brand', 'body_type', 'transmission', 'drivetrain', 'fuel', 'location'] as $key) {
            if ($filters[$key] !== []) {
                $params[$key] = $filters[$key];
            }
        }

        foreach (['min_price', 'max_price', 'min_year', 'max_mileage'] as $key) {
            if ($filters[$key] !== null) {
                $params[$key] = $filters[$key];
            }
        }

        if ($filters['with_financing'] === true) {
            $params['with_financing'] = 1;
        }

        if ($filters['with_image'] === true) {
            $params['with_image'] = 1;
        }

        if (($filters['regex'] ?? false) === true) {
            $params['regex'] = 1;
        }

        if ($filters['sort'] !== 'terbaru') {
            $params['sort'] = $filters['sort'];
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function describe(array $filters): string
    {
        $parts = [];

        if (filled($filters['q'])) {
            $parts[] = (($filters['regex'] ?? false) === true ? 'pola regex' : 'kata kunci').' "'.$filters['q'].'"';
        }

        if ($filters['brand'] !== []) {
            $parts[] = 'merek '.implode(' / ', $filters['brand']);
        }

        if ($filters['body_type'] !== []) {
            $parts[] = 'jenis '.implode(' / ', $filters['body_type']);
        }

        if ($filters['transmission'] !== []) {
            $parts[] = 'transmisi '.implode(' / ', $filters['transmission']);
        }

        if ($filters['drivetrain'] !== []) {
            $parts[] = 'penggerak '.implode(' / ', $filters['drivetrain']);
        }

        if ($filters['fuel'] !== []) {
            $parts[] = 'bahan bakar '.implode(' / ', $filters['fuel']);
        }

        if ($filters['location'] !== []) {
            $parts[] = 'lokasi '.implode(' / ', $filters['location']);
        }

        if ($filters['min_price'] !== null && $filters['max_price'] !== null) {
            $parts[] = 'harga '.$this->rupiah((int) $filters['min_price']).'–'.$this->rupiah((int) $filters['max_price']);
        } elseif ($filters['max_price'] !== null) {
            $parts[] = 'harga maksimal '.$this->rupiah((int) $filters['max_price']);
        } elseif ($filters['min_price'] !== null) {
            $parts[] = 'harga minimal '.$this->rupiah((int) $filters['min_price']);
        }

        if ($filters['min_year'] !== null) {
            $parts[] = 'tahun minimal '.$filters['min_year'];
        }

        if ($filters['max_mileage'] !== null) {
            $parts[] = 'kilometer maksimal '.number_format((int) $filters['max_mileage'], 0, ',', '.').' km';
        }

        if ($filters['with_financing'] === true) {
            $parts[] = 'bisa dicicil';
        }

        $sortLabels = [
            'harga_terendah' => 'urut harga terendah',
            'harga_tertinggi' => 'urut harga tertinggi',
            'tahun_terbaru' => 'urut tahun terbaru',
        ];

        if (isset($sortLabels[$filters['sort']])) {
            $parts[] = $sortLabels[$filters['sort']];
        }

        return implode(', ', $parts);
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * @return array<string, true>
     */
    private function stopwords(): array
    {
        static $stopwords = null;

        return $stopwords ??= array_fill_keys([
            'mobil', 'mobilnya', 'bekas', 'second', 'used', 'baru', 'bagus',
            'baik', 'recommended', 'rekomendasi', 'cari', 'mencari', 'carikan',
            'cariin', 'butuh', 'ingin', 'mau', 'pengen', 'saya', 'aku', 'kami',
            'kita', 'dong', 'ya', 'nih', 'sih', 'deh', 'aja', 'saja', 'yang',
            'dan', 'atau', 'di', 'ke', 'dari', 'untuk', 'dengan', 'tanpa',
            'ada', 'apakah', 'apa', 'tolong', 'bantu', 'bisa', 'cocok', 'buat',
            'punya', 'dana', 'budget', 'bujet', 'anggaran', 'harga', 'kisaran',
            'sekitar', 'kira', 'pas', 'bawah', 'dibawah', 'atas', 'diatas',
            'maksimal', 'maks', 'max', 'minimal', 'min', 'kurang', 'lebih',
            'sampai', 'mulai', 'juta', 'jt', 'jtan', 'miliar', 'm', 'ribu',
            'rb', 'km', 'kilometer', 'tahun', 'unit', 'stok', 'tersedia',
            'pilihan', 'terbaik', 'termurah', 'termahal', 'terbaru', 'paling',
            'tampilkan', 'lihat', 'mohon', 'hallo', 'halo', 'hai', 'hi',
            'regex', 'regexp', 'pola', 'pattern', 'pakai', 'menggunakan',
            'keluarga', 'harian', 'hemat', 'irit', 'nyaman', 'aman', 'awet',
        ], true);
    }
}
