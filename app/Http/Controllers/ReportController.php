<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index(ReportRequest $r, ReportService $service)
    {
        [$from,$to] = $r->range();
        $type = $r->validated('type') ?? 'daily';
        $definition = $service->report($type, $from, $to);

        return view('reports.index', ['from' => $from, 'to' => $to, 'type' => $type, 'types' => ReportService::types(), 'columns' => $definition['columns'], 'rows' => $definition['query']->paginate(25)->withQueryString(), 'summary' => $service->summary($from, $to), 'charts' => $service->charts($from, $to)]);
    }

    public function csv(ReportRequest $r, ReportService $service)
    {
        [$from,$to] = $r->range();
        $type = $r->validated('type') ?? 'daily';
        $definition = $service->report($type, $from, $to);

        return response()->streamDownload(function () use ($definition) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, array_values($definition['columns']), ',', '"', '');
            foreach ($definition['query']->cursor() as $row) {
                $values = [];
                foreach ($definition['columns'] as $key => $label) {
                    $value = (string) ($row->$key ?? '');
                    $values[] = preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u', $value) ? "'".$value : $value;
                }fputcsv($h, $values, ',', '"', '');
            }fclose($h);
        }, 'reporte-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(ReportRequest $r, ReportService $service)
    {
        [$from,$to] = $r->range();
        $type = $r->validated('type') ?? 'daily';
        $definition = $service->report($type, $from, $to);
        $rows = $definition['query']->limit(1001)->get();
        if ($rows->count() > 1000) {
            throw ValidationException::withMessages(['report' => 'El PDF admite hasta 1000 filas. Reduce el período o utiliza CSV para exportar todos los registros.']);
        }

        return Pdf::loadView('reports.pdf', ['from' => $from, 'to' => $to, 'title' => ReportService::types()[$type], 'columns' => $definition['columns'], 'rows' => $rows, 'summary' => $service->summary($from, $to)])->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4', 'landscape')->download('reporte-'.$type.'.pdf');
    }
}
