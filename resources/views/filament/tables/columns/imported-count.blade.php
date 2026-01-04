<x-filament::badge color="success">
    Imported Products: <span wire:poll.2s>{{ \App\Filament\Resources\ProductResource::getImportedCount() }}</span>
</x-filament::badge>