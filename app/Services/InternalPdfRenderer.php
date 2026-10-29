<?php

namespace App\Services;

use App\Contracts\DocumentRenderer;
use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;

class InternalPdfRenderer implements DocumentRenderer
{
    public function render(Document $document): string
    {
        $document->load('sale.items', 'sale.payments', 'sale.user');

        return Pdf::loadView('documents.receipt', compact('document'))->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4')->output();
    }
}
