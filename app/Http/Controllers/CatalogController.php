<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogRequest;
use App\Models\AuditLog;
use App\Models\Product;
use App\Services\CatalogService;
use App\Support\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    private function definition(Request $r): array
    {
        return Catalog::definition($r->route('resource'));
    }

    public function index(Request $r)
    {
        $def = $this->definition($r);
        Gate::authorize('viewAny', $def['model']);

        return view('catalog.index', ['resource' => $r->route('resource'), 'def' => $def]);
    }

    public function create(Request $r)
    {
        $def = $this->definition($r);
        Gate::authorize('create', $def['model']);

        return view('catalog.form', ['resource' => $r->route('resource'), 'def' => $def, 'record' => new $def['model'], 'options' => Catalog::options()]);
    }

    public function edit(Request $r)
    {
        $def = $this->definition($r);
        $record = $def['model']::findOrFail($r->route('id'));
        Gate::authorize('update', $record);

        return view('catalog.form', ['resource' => $r->route('resource'), 'def' => $def, 'record' => $record, 'options' => Catalog::options()]);
    }

    public function store(CatalogRequest $r, CatalogService $service)
    {
        return $this->persist($r, $service);
    }

    public function update(CatalogRequest $r, CatalogService $service)
    {
        return $this->persist($r, $service);
    }

    private function persist(CatalogRequest $r, CatalogService $service)
    {
        $data = $r->validated();
        unset($data['image']);
        $image = null;
        if ($r->hasFile('image')) {
            $image = $r->file('image')->store('products', 'public');
            $data['image_path'] = $image;
        }try {
            $service->save($r->route('resource'), $data, $r->user()->id, $r->route('id') ? (int) $r->route('id') : null);
        } catch (\Throwable $e) {
            if ($image) {
                Storage::disk('public')->delete($image);
            }throw $e;
        }

        return redirect()->route($r->route('resource').'.index')->with('status', 'Registro guardado.');
    }

    public function show(Request $r)
    {
        $def = $this->definition($r);
        $record = $def['model']::findOrFail($r->route('id'));
        Gate::authorize('view', $record);
        $history = match ($r->route('resource')) {
            'products' => $record->movements()->with('user')->latest()->paginate(20),'customers' => $record->sales()->with('user')->latest()->paginate(20),'suppliers' => $record->purchases()->with('user')->latest()->paginate(20),default => null
        };

        return view('catalog.show', ['resource' => $r->route('resource'), 'def' => $def, 'record' => $record, 'history' => $history]);
    }

    public function destroy(Request $r, CatalogService $service)
    {
        $def = $this->definition($r);
        $record = $def['model']::findOrFail($r->route('id'));
        Gate::authorize('delete', $record);
        if ($r->route('resource') === 'categories') {
            if ($record->products()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['name' => 'No puedes eliminar una categoría que tiene productos.']);
            }$record->delete();
        } elseif ($r->route('resource') === 'users') {
            $service->save('users', ['active' => false, 'role_id' => $record->role_id], $r->user()->id, $record->id);
        } else {
            $record->active = false;
            $record->save();
        }AuditLog::create(['user_id' => $r->user()->id, 'action' => 'deactivated', 'subject_type' => $r->route('resource'), 'subject_id' => $record->id]);

        return back()->with('status', 'Registro desactivado o eliminado.');
    }

    public function export(Request $r)
    {
        Gate::authorize('viewAny', Product::class);

        return response()->streamDownload(function () {
            $h = fopen('php://output', 'w');
            $fields = Catalog::csvFields();
            fputcsv($h, $fields, ',', '"', '');
            Product::orderBy('id')->chunk(200, function ($rows) use ($h, $fields) {
                foreach ($rows as $row) {
                    fputcsv($h, array_map(fn ($f) => $this->safeCsv($f === 'active' ? ($row->active ? '1' : '0') : (string) $row->$f), $fields), ',', '"', '');
                }
            });
            fclose($h);
        }, 'productos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsv(string $v): string
    {
        return preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u', $v) ? "'".$v : $v;
    }

    public function import(Request $r, CatalogService $service)
    {
        Gate::authorize('create', Product::class);
        $r->validate(['csv' => 'required|file|mimes:csv,txt|max:2048']);
        $h = fopen($r->file('csv')->getRealPath(), 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        if (! $header || $header !== Catalog::csvFields()) {
            fclose($h);
            throw ValidationException::withMessages(['csv' => 'Encabezado incorrecto. Utiliza el formato de la exportación CSV.']);
        }$count = 0;
        try {
            DB::transaction(function () use ($h, $header, $r, $service, &$count) {
                while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
                    if ($row === [null]) {
                        continue;
                    }if (++$count > 1000 || count($row) !== count($header)) {
                        throw ValidationException::withMessages(['csv' => 'Límite de 1000 productos o fila incompleta: '.($count + 1)]);
                    }$data = array_combine($header, array_map(fn ($v) => $v === '' ? null : $v, $row));
                    $valid = Validator::make($data, Catalog::rules('products'));
                    if ($valid->fails()) {
                        throw ValidationException::withMessages(['csv' => 'Fila '.($count + 1).': '.implode(' ', $valid->errors()->all())]);
                    }$service->save('products', $valid->validated(), $r->user()->id);
                }
            });
        } finally {
            fclose($h);
        }

        return back()->with('status', "Importación completa: $count productos. El stock se registra en Inventario.");
    }
}
