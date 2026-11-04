<?php

declare(strict_types=1);

use coworkingview\coworkingview\services\MarketRatesParser;
use coworkingview\coworkingview\services\Slug;

require dirname(__DIR__) . '/vendor/autoload.php';

$payload = json_decode((string)file_get_contents(__DIR__ . '/fixtures/market-rates.json'), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Fixture did not decode\n");
    exit(1);
}

$parser = new MarketRatesParser();
$rates = $parser->rates($payload);
$cities = $parser->cities($payload);

$failures = 0;
$check = static function(bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures++;
        fwrite(STDERR, "FAIL {$message}\n");
    }
};

$check($rates->attribution === 'Source: CoworkingView Price Index', 'rates attribution');
$check($rates->license === 'https://creativecommons.org/licenses/by/4.0/', 'rates license');
$check($rates->asOf === '2026-11-04T10:16:56.538Z', 'rates asOf');
$check(count($rates) === 8, 'rates row count ' . count($rates));

$expected = [
    ['aarhus', 'flex_desk', 'month', 150, null, null, 8, null, null, true],
    ['berlin', 'flex_desk', 'month', 209, 199, 290, 73, 67, 38, false],
    ['berlin', 'fix_desk', 'month', 310, 290, 421, 60, 56, 37, false],
    ['berlin', 'private_office', 'month', 480, 399, 550, 59, 38, 20, false],
    ['berlin', 'day_pass', 'day', 35, null, null, 59, 55, 27, false],
    ['berlin', 'meeting_room', 'hour', 32.5, null, null, 4, 4, 3, true],
    ['berlin', 'event_space', 'hour', 120, 90, 180, 12, null, null, false],
    ['berlin', 'virtual_office', 'month', 40, null, null, 11, null, null, false],
];

foreach ($rates->rows as $index => $row) {
    $want = $expected[$index] ?? null;
    $check($want !== null, "unexpected extra row {$index}");
    if ($want === null) {
        continue;
    }
    $check($row['city_slug'] === $want[0], "row {$index} city");
    $check($row['product'] === $want[1], "row {$index} product {$row['product']}");
    $check($row['unit'] === $want[2], "row {$index} unit");
    $check($row['median'] === $want[3], "row {$index} median");
    $check($row['p25'] === $want[4], "row {$index} p25");
    $check($row['p75'] === $want[5], "row {$index} p75");
    $check($row['sample_size'] === $want[6], "row {$index} sample");
    $check($row['property_count'] === $want[7], "row {$index} properties");
    $check($row['operator_count'] === $want[8], "row {$index} operators");
    $check($row['low_confidence'] === $want[9], "row {$index} confidence");
    $check($row['as_of'] === '2026-11-04T10:16:56.538Z', "row {$index} as_of");
}

$check($rates->rows[1]['city_name'] === 'Berlin', 'berlin name');
$check($rates->rows[1]['country_code'] === 'DE', 'berlin country');
$check($rates->rows[1]['currency'] === 'EUR', 'berlin currency');

$cityRows = $cities->rows;
$check(count($cityRows) === 3, 'city count');
$check($cityRows[0]['city_slug'] === 'aarhus' && $cityRows[0]['published'] === true, 'aarhus first');
$check($cityRows[0]['entry_median'] === 140 && $cityRows[0]['sample_size'] === 8, 'aarhus entry');
$check($cityRows[1]['city_slug'] === 'berlin' && $cityRows[1]['entry_median'] === 259, 'berlin entry');
$check($cityRows[1]['entry_min'] === 80 && $cityRows[1]['entry_max'] === 2100, 'berlin entry range');
$check($cityRows[1]['p25'] === 219 && $cityRows[1]['p75'] === 450, 'berlin quartiles');
$check($cityRows[2]['city_slug'] === 'tiny-town' && $cityRows[2]['published'] === false, 'unpublished last');
$check($cityRows[2]['sample_size'] === null && $cityRows[2]['entry_median'] === null, 'unpublished has no figures');
$check($cities->attribution === $rates->attribution, 'cities attribution');

$emptyFigures = [
    'data' => [
        'asOf' => '2026-11-04T00:00:00Z',
        'attribution' => 'attr',
        'licenseUrl' => 'https://creativecommons.org/licenses/by/4.0/',
        'cities' => [[
            'city' => ['slug' => 'lyon', 'name' => 'Lyon'],
            'countryCode' => 'FR',
            'currency' => 'EUR',
            'figures' => [],
            'byType' => [[
                'optionType' => 'flex_desk',
                'median' => ['amount' => 180, 'currency' => 'EUR'],
                'sampleSize' => 6,
            ]],
        ]],
        'unpublished' => [],
    ],
];
$fallback = $parser->rates($emptyFigures);
$check(count($fallback) === 1 && $fallback->rows[0]['median'] === 180, 'empty figures fall back to byType');
$check($fallback->rows[0]['p25'] === null && $fallback->rows[0]['property_count'] === null, 'byType quartiles are null');

$check(Slug::city('Abu Dhabi') === 'abu-dhabi', 'slug spaces');
$check(Slug::city('Berlin') === 'berlin', 'slug case');
$check(Slug::country('de') === 'DE', 'country');
$check(Slug::country(null) === null && Slug::country('  ') === null, 'empty country');

$threw = false;
try {
    Slug::city('!!!');
} catch (InvalidArgumentException) {
    $threw = true;
}
$check($threw, 'bad slug throws');

if ($failures > 0) {
    fwrite(STDERR, "{$failures} parser check(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "parser checks passed\n");
