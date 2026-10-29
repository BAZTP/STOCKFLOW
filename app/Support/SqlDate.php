<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class SqlDate
{
    // Column names are internal constants, never request input.
    public static function day(string $column): string
    {
        return DB::getDriverName() === 'sqlsrv'
            ? "CONVERT(char(10), {$column}, 23)"
            : "DATE({$column})";
    }

    public static function month(string $column): string
    {
        return DB::getDriverName() === 'sqlsrv'
            ? "CONVERT(char(7), {$column}, 120)"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
