<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentRenderer;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function index(Request $r)
    {
        Gate::authorize('documents.view');
        $r->validate(['search' => 'nullable|string|max:100']);
        $q = Document::with('sale.customer')->latest();
        if ($r->filled('search')) {
            $q->where('number', 'like', '%'.$r->search.'%');
        }

        return view('documents.index', ['documents' => $q->paginate(20)->withQueryString()]);
    }

    public function show(Document $document)
    {
        Gate::authorize('documents.view');
        $document->load('sale.items', 'sale.payments', 'sale.user');

        return view('documents.show', compact('document'));
    }

    public function pdf(Document $document, DocumentRenderer $renderer)
    {
        Gate::authorize('documents.view');

        return response($renderer->render($document))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="'.$document->number.'.pdf"');
    }
}
