<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['sku', 'barcode', 'name', 'description', 'category_id', 'supplier_id', 'purchase_price', 'sale_price', 'tax_basis_points', 'minimum_stock', 'unit', 'image_path', 'active'];

    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected function casts(): array
    {
        return ['purchase_price' => 'decimal:2', 'sale_price' => 'decimal:2', 'active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
