<x-filament::badge color="success">
   <span wire:poll.2s>{{ \App\Filament\Resources\ProductResource::getImportedCount() }}</span>
</x-filament::badge>
