<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function cents(string|int|float $value): int
    {
        $value = preg_replace('/^\./', '0.', (string) $value);
        $value = preg_replace('/(\.\d{2})0+$/', '$1', $value);
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['amount' => 'Importe inválido. Usa hasta dos decimales y ningún separador de miles.']);
        }[$whole,$fraction] = array_pad(explode('.', (string) $value), 2, '');
        $result = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        self::bounded($result);

        return $result;
    }

    public static function decimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function tax(int $base, int $rate): int
    {
        return intdiv($base, 10000) * $rate + intdiv(($base % 10000) * $rate + 5000, 10000);
    }

    public static function bounded(int $amount): void
    {
        if ($amount < 0 || $amount > 99999999999999) {
            throw ValidationException::withMessages(['amount' => 'El importe excede el límite permitido.']);
        }
    }

    public static function line(int $price, int $quantity, int $discount, int $rate): array
    {
        $gross = $price * $quantity;
        self::bounded($gross);
        if ($discount > $gross) {
            throw ValidationException::withMessages(['discount' => 'El descuento supera el importe de la línea.']);
        }$tax = self::tax($gross - $discount, $rate);
        self::bounded($gross - $discount + $tax);

        return ['gross' => $gross, 'discount' => $discount, 'tax' => $tax, 'total' => $gross - $discount + $tax];
    }

    /** Format exact decimal values without converting money to floating point. */
    public static function display(string|int $amount): string
    {
        $negative = str_starts_with((string) $amount, '-');
        $unsigned = ltrim((string) $amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned), 2, '');
        $whole = preg_replace('/\\B(?=(\\d{3})+(?!\\d))/', ',', $whole);

        return ($negative ? '-' : '').$whole.'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
