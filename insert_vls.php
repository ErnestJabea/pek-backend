<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\ProductVl;

$data = [
    ['date' => '2026-01-02', 'vl' => 10965.15],
    ['date' => '2026-01-09', 'vl' => 10968.73],
    ['date' => '2026-01-16', 'vl' => 10973.01],
    ['date' => '2026-01-23', 'vl' => 10976.22],
    ['date' => '2026-01-30', 'vl' => 10979.81],
    ['date' => '2026-02-06', 'vl' => 10986.12],
    ['date' => '2026-02-13', 'vl' => 10987.61],
    ['date' => '2026-02-20', 'vl' => 11042.20],
    ['date' => '2026-02-27', 'vl' => 11290.68],
    ['date' => '2026-03-06', 'vl' => 11293.51],
    ['date' => '2026-03-13', 'vl' => 11300.05],
    ['date' => '2026-03-20', 'vl' => 11303.62],
    ['date' => '2026-03-27', 'vl' => 11308.58],
    ['date' => '2026-04-03', 'vl' => 11330.05],
    ['date' => '2026-04-10', 'vl' => 11332.83],
    ['date' => '2026-04-17', 'vl' => 11352.88],
    ['date' => '2026-04-24', 'vl' => 11357.49],
    ['date' => '2026-05-01', 'vl' => 11364.41],
];

ProductVl::truncate();

foreach (Product::all() as $product) {
    foreach ($data as $item) {
        // We vary the VL slightly for other products so the chart doesn't look completely identical if plotted together
        $multiplier = 1;
        if ($product->id == 2) $multiplier = 1.2;
        if ($product->id == 3) $multiplier = 1.5;

        ProductVl::create([
            'product_id' => $product->id,
            'date_vl' => $item['date'],
            'vl' => $item['vl'] * $multiplier
        ]);
    }
}

echo "Successfully inserted " . (count($data) * Product::count()) . " VL records.\n";
