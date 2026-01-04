<x-filament::badge color="info">
    Global Sync Status: {{ \App\Filament\Resources\ProductResource::getGlobalSyncStatus() ?? 'Idle' }}
</x-filament::badge>