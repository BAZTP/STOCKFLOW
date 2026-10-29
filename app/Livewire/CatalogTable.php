<?php

namespace App\Livewire;

use App\Models\Category;
use App\Support\Catalog;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogTable extends Component
{
    use WithPagination;

    #[Locked]
    public string $resource;

    public string $search = '';

    public string $category = '';

    public string $state = '';

    public function mount(string $resource)
    {
        $this->resource = $resource;
        Gate::authorize('viewAny', Catalog::definition($resource)['model']);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategory()
    {
        $this->resetPage();
    }

    public function updatedState()
    {
        $this->resetPage();
    }

    public function render()
    {
        $def = Catalog::definition($this->resource);
        Gate::authorize('viewAny', $def['model']);
        $query = $def['model']::query();
        if ($this->search !== '') {
            $term = '%'.mb_substr($this->search, 0, 120).'%';
            $resource = $this->resource;
            $query->where(function ($q) use ($term, $resource) {
                $q->where('name', 'like', $term);
                if ($resource === 'products') {
                    $q->orWhere('sku', 'like', $term)->orWhere('barcode', 'like', $term);
                }if (in_array($resource, ['customers', 'suppliers'])) {
                    $q->orWhere('identification', 'like', $term);
                }
            });
        }
        if ($this->resource === 'products') {
            if ($this->category !== '') {
                $query->where('category_id', $this->category);
            }$query->with('category');
        }
        if ($this->state !== '' && $this->resource !== 'categories') {
            $query->where('active', $this->state === '1');
        }

        return view('livewire.catalog-table', ['rows' => $query->latest('id')->paginate(12), 'def' => $def, 'categories' => Category::orderBy('name')->get()]);
    }
}
