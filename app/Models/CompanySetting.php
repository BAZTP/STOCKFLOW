<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = ['name', 'legal_name', 'identification', 'address', 'phone', 'email', 'logo_path', 'tax_basis_points', 'minimum_stock', 'document_prefix'];
}
