<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Generate a schema-only SSMS script from the same migrations used by Artisan.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlsrv']);
$connection = DB::connection('sqlsrv');
$files = glob(database_path('migrations/*.php'));
sort($files);
$queries = $connection->pretend(function () use ($files) {
    Schema::create('migrations', function ($table) {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });
    foreach ($files as $file) {
        (require $file)->up();
    }
});
$output = "-- StockFlow ERP: SQL Server 2022 / SSMS. Schema only; no passwords or business data.\n";
$output .= "-- Create/select an EMPTY database in SSMS before running this entire script.\n";
$output .= "SET QUOTED_IDENTIFIER ON;\nSET ANSI_NULLS ON;\nSET ANSI_PADDING ON;\nSET ANSI_WARNINGS ON;\nSET ARITHABORT ON;\nSET CONCAT_NULL_YIELDS_NULL ON;\nSET NUMERIC_ROUNDABORT OFF;\nSET XACT_ABORT ON;\nBEGIN TRY\nBEGIN TRANSACTION;\n";
$output .= "IF EXISTS (SELECT 1 FROM sys.tables WHERE is_ms_shipped = 0) THROW 50001, 'Use an empty database for this script.', 1;\n";
foreach ($queries as $query) {
    if ($query['bindings']) {
        throw new RuntimeException('Unexpected schema bindings; review exporter.');
    }
    $output .= $query['query'].";\n";
}
foreach ($files as $file) {
    $name = basename($file, '.php');
    $output .= "INSERT INTO [migrations] ([migration], [batch]) VALUES (N'$name', 1);\n";
}
$output .= "COMMIT TRANSACTION;\nEND TRY\nBEGIN CATCH\nIF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;\nTHROW;\nEND CATCH;\nGO\n";
file_put_contents(database_path('sql/stockflow-sqlserver.sql'), $output);
echo "SQL Server schema exported.\n";
