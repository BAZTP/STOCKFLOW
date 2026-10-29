<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['customer_id', 'user_id', 'idempotency_key', 'status', 'subtotal', 'discount', 'tax', 'total', 'cost', 'cancellation_reason'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function document()
    {
        return $this->hasOne(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
