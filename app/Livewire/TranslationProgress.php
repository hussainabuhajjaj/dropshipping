<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;
use Livewire\Attributes\On;

class TranslationProgress extends Component
{
    public $products = [];
    public $isOpen = false;

    public function mount()
    {
        // Load products on component mount
        $this->loadProducts();
        // Auto-open on product list page
        if (request()->routeIs('filament.admin.resources.products.index')) {
            $this->isOpen = true;
        }
    }

    #[On('open-translation-progress')]
    public function open()
    {
        $this->isOpen = true;
        $this->loadProducts();
    }

    public function close()
    {
        $this->isOpen = false;
    }

    public function loadProducts()
    {
        $this->products = Product::query()
            ->whereIn('translation_status', ['in_progress', 'pending'])
            ->orWhere('last_translation_at', '>=', now()->subMinutes(5))
            ->orderByDesc('last_translation_at')
            ->take(20)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->translation_status ?? 'pending',
                'locales' => $p->translated_locales ?? [],
                'last_translated_at' => $p->last_translation_at?->diffForHumans(),
            ])
            ->toArray();
    }

    #[\Livewire\Attributes\Computed]
    public function completedCount()
    {
        return collect($this->products)->where('status', 'completed')->count();
    }

    #[\Livewire\Attributes\Computed]
    public function inProgressCount()
    {
        return collect($this->products)->where('status', 'in_progress')->count();
    }

    #[\Livewire\Attributes\Computed]
    public function failedCount()
    {
        return collect($this->products)->where('status', 'failed')->count();
    }

    public function render()
    {
        return view('livewire.translation-progress');
    }
}
