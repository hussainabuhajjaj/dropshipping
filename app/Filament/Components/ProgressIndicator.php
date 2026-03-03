<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Field;
use Illuminate\Contracts\View\View;

class ProgressIndicator extends Field
{
    protected string $view = 'filament.components.progress-indicator';

    protected int $design = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->design(3);
    }

    public function design(int $design): static
    {
        $this->design = $design;

        return $this;
    }

    public function getDesign(): int
    {
        return $this->design;
    }

    public function getActiveImportTrackingKey(): ?string
    {
        $state = $this->getState();
        return $state['activeImportTrackingKey'] ?? null;
    }

    public function getImportPercent(): int
    {
        $state = $this->getState();
        return (int) ($state['importPercent'] ?? 0);
    }

    public function getImportTotal(): int
    {
        $state = $this->getState();
        return (int) ($state['importTotal'] ?? 0);
    }

    public function getImportProcessed(): int
    {
        $state = $this->getState();
        return (int) ($state['importProcessed'] ?? 0);
    }

    public function getImportFailed(): int
    {
        $state = $this->getState();
        return (int) ($state['importFailed'] ?? 0);
    }

    public function getImportStatusLabel(): string
    {
        $state = $this->getState();
        return $state['importStatusLabel'] ?? 'processing';
    }

    public function getImportStartedAt(): ?string
    {
        $state = $this->getState();
        return $state['importStartedAt'] ?? null;
    }

    public function getPollInterval(): int
    {
        $state = $this->getState();
        return (int) ($state['pollInterval'] ?? 3);
    }

    public function render(): View
    {
        // Debug: Log the current design
        \Log::info('ProgressIndicator rendering with design: ' . $this->design);
        
        return view($this->getView(), [
            'component' => $this,
        ]);
    }
}
