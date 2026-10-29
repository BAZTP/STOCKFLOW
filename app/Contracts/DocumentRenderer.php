<?php

namespace App\Contracts;

use App\Models\Document;

interface DocumentRenderer
{
    public function render(Document $document): string;
}
