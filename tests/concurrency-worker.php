<?php

use App\Models\User;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script,$userId,$customerId,$productId,$key,$barrier] = $argv;
$ready = $barrier.'.'.getmypid();
touch($ready);
$deadline = microtime(true) + 10;
while (count(glob($barrier.'.*')) < 2) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Barrier timeout\n");
        exit(3);
    }usleep(10000);
}
try {
    $sale = app(SaleService::class)->confirm(['customer_id' => $customerId, 'idempotency_key' => $key, 'items' => [['product_id' => $productId, 'quantity' => 1]], 'discount' => '0', 'method' => 'cash', 'tendered' => '11.50'], User::findOrFail($userId));
    echo json_encode(['sale' => $sale->id]);
    exit(0);
} catch (ValidationException $e) {
    echo json_encode(['errors' => array_keys($e->errors())]);
    exit(2);
}
