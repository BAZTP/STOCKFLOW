<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['company_snapshot' => 'array', 'customer_snapshot' => 'array'];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
