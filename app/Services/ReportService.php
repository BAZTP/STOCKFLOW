<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public static function types(): array
    {
        return ['daily' => 'Ventas diarias', 'monthly' => 'Ventas mensuales', 'annual' => 'Ventas anuales', 'top-products' => 'Productos más vendidos', 'no-movement' => 'Productos sin movimiento', 'inventory' => 'Inventario actual', 'low-stock' => 'Productos con stock bajo', 'purchases' => 'Compras recibidas por proveedor', 'sellers' => 'Ventas por vendedor', 'customers' => 'Ventas por cliente', 'profit' => 'Ganancia bruta estimada'];
    }

    public static function monetary(): array
    {
        return ['gross', 'discount', 'tax', 'total', 'cost', 'profit', 'net', 'value', 'purchase_price', 'sale_price'];
    }

    private function sales(string $from, string $to): Builder
    {
        return DB::table('sales as s')->where('s.status', 'completed')->whereBetween('s.created_at', [$from.' 00:00:00', $to.' 23:59:59']);
    }

    private function totals(): string
    {
        return 'COUNT(*) as transactions, SUM(s.subtotal) as gross, SUM(s.discount) as discount, SUM(s.subtotal-s.discount) as net, SUM(s.tax) as tax, SUM(s.total) as total, SUM(s.cost) as cost, SUM(s.subtotal-s.discount-s.cost) as profit';
    }

    public function report(string $type, string $from, string $to): array
    {
        abort_unless(isset(self::types()[$type]), 404);
        $financial = ['period' => 'Período', 'transactions' => 'Ventas', 'gross' => 'Subtotal', 'discount' => 'Descuentos', 'net' => 'Ingreso neto', 'tax' => 'Impuestos', 'total' => 'Total con impuestos', 'cost' => 'Costo de venta', 'profit' => 'Ganancia bruta'];
        if (in_array($type, ['daily', 'monthly', 'annual', 'profit'])) {
            $expr = match ($type) {
                'monthly' => "DATE_FORMAT(s.created_at,'%Y-%m')",'annual' => 'YEAR(s.created_at)',default => 'DATE(s.created_at)'
            };
            $query = $this->sales($from, $to)->selectRaw($expr.' as period, '.$this->totals())->groupByRaw($expr)->orderBy('period');
            $columns = $financial;
        } elseif (in_array($type, ['sellers', 'customers'])) {
            $table = $type === 'sellers' ? 'users' : 'customers';
            $fk = $type === 'sellers' ? 'user_id' : 'customer_id';
            $query = $this->sales($from, $to)->join($table.' as person', 'person.id', '=', 's.'.$fk)->select('person.name')->selectRaw($this->totals())->groupBy('person.id', 'person.name')->orderByDesc('net');
            $columns = ['name' => $type === 'sellers' ? 'Vendedor' : 'Cliente'] + array_diff_key($financial, ['period' => true]);
        } elseif ($type === 'top-products') {
            $query = $this->sales($from, $to)->join('sale_items as item', 'item.sale_id', '=', 's.id')->join('products as p', 'p.id', '=', 'item.product_id')->select('p.sku', 'p.name')->selectRaw('SUM(item.quantity) as quantity,SUM(item.unit_price*item.quantity-item.discount) as net,SUM(item.unit_cost*item.quantity) as cost,SUM(item.unit_price*item.quantity-item.discount-item.unit_cost*item.quantity) as profit')->groupBy('p.id', 'p.sku', 'p.name')->orderByDesc('quantity');
            $columns = ['sku' => 'SKU', 'name' => 'Producto', 'quantity' => 'Unidades', 'net' => 'Ingreso neto', 'cost' => 'Costo de venta', 'profit' => 'Ganancia bruta'];
        } elseif ($type === 'purchases') {
            $query = DB::table('purchases as p')->join('suppliers as sp', 'sp.id', '=', 'p.supplier_id')->where('p.status', 'received')->whereBetween('p.updated_at', [$from.' 00:00:00', $to.' 23:59:59'])->select('sp.name')->selectRaw('COUNT(*) as transactions,SUM(p.subtotal-p.discount) as net,SUM(p.tax) as tax,SUM(p.total) as total')->groupBy('sp.id', 'sp.name')->orderByDesc('total');
            $columns = ['name' => 'Proveedor', 'transactions' => 'Recepciones', 'net' => 'Costo neto', 'tax' => 'Impuestos', 'total' => 'Total'];
        } else {
            $query = DB::table('products as p')->join('categories as c', 'c.id', '=', 'p.category_id')->whereNull('p.deleted_at')->select('p.sku', 'p.name', 'c.name as category', 'p.stock', 'p.minimum_stock', 'p.purchase_price', 'p.sale_price')->selectRaw('p.stock*p.purchase_price as value')->orderBy('p.name');
            if ($type === 'low-stock') {
                $query->whereColumn('p.stock', '<=', 'p.minimum_stock');
            }if ($type === 'no-movement') {
                $query->whereNotExists(function ($q) use ($from, $to) {
                    $q->selectRaw('1')->from('inventory_movements as m')->whereColumn('m.product_id', 'p.id')->whereBetween('m.created_at', [$from.' 00:00:00', $to.' 23:59:59']);
                });
            }$columns = ['sku' => 'SKU', 'name' => 'Producto', 'category' => 'Categoría', 'stock' => 'Disponible', 'minimum_stock' => 'Mínimo', 'purchase_price' => 'Costo unitario', 'sale_price' => 'Precio de venta', 'value' => 'Valor de inventario'];
        }

        return compact('query', 'columns');
    }

    public function summary(string $from, string $to): array
    {
        $row = $this->sales($from, $to)->selectRaw('COALESCE(SUM(s.subtotal-s.discount),0) as net,COALESCE(SUM(s.discount),0) as discount,COALESCE(SUM(s.tax),0) as tax,COALESCE(SUM(s.total),0) as total,COALESCE(SUM(s.cost),0) as cost,COALESCE(SUM(s.subtotal-s.discount-s.cost),0) as profit')->first();

        return (array) $row;
    }

    public function charts(string $from, string $to): array
    {
        $daily = $this->sales($from, $to)->selectRaw('DATE(s.created_at) as label,SUM(s.total) as value,SUM(s.subtotal-s.discount) as net')->groupByRaw('DATE(s.created_at)')->orderBy('label')->get();
        $monthly = $this->sales($from, $to)->selectRaw("DATE_FORMAT(s.created_at,'%Y-%m') as label,SUM(s.subtotal-s.discount) as value")->groupByRaw("DATE_FORMAT(s.created_at,'%Y-%m')")->orderBy('label')->get();
        $top = $this->report('top-products', $from, $to)['query']->limit(5)->get();
        $categories = $this->sales($from, $to)->join('sale_items as item', 'item.sale_id', '=', 's.id')->select('item.category_name as label')->selectRaw('SUM(item.unit_price*item.quantity-item.discount) as value')->groupBy('item.category_name')->orderByDesc('value')->limit(10)->get();
        $cumulative = [];
        $sum = 0;
        foreach ($daily as $row) {
            $sum += Money::cents($row->net);
            $cumulative[] = Money::decimal($sum);
        }

        return ['daily' => ['labels' => $daily->pluck('label'), 'values' => $daily->pluck('value'), 'label' => 'Ventas con impuestos USD'], 'monthly' => ['labels' => $monthly->pluck('label'), 'values' => $monthly->pluck('value'), 'label' => 'Ingreso neto USD'], 'top' => ['labels' => $top->pluck('name'), 'values' => $top->pluck('quantity'), 'label' => 'Unidades vendidas'], 'categories' => ['labels' => $categories->pluck('label'), 'values' => $categories->pluck('value'), 'label' => 'Ingreso neto USD'], 'trend' => ['labels' => $daily->pluck('label'), 'values' => $cumulative, 'label' => 'Ingreso neto acumulado USD']];
    }
}
