<?php

namespace App\Filament\Resources\ChargebackCaseResource\Pages;

use App\Domain\Orders\Models\ChargebackCase;
use App\Domain\Orders\Models\ChargebackEvidence;
use App\Domain\Orders\Services\ChargebackEvidenceService;
use App\Enums\ChargebackEvidenceType;
use App\Filament\Resources\ChargebackCaseResource;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions\Action;

class ManageChargebackEvidence extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = ChargebackCaseResource::class;

    protected string $view = 'filament.resources.chargeback-case-resource.pages.manage-chargeback-evidence';

    public ChargebackCase $record;

    public function mount(ChargebackCase $record): void
    {
        $this->record = $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_evidence')
                ->label('Add Evidence')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\Select::make('type')
                        ->options(ChargebackEvidenceType::labels())
                        ->required()
                        ->reactive(),

                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Textarea::make('description')
                        ->rows(2),

                    Forms\Components\Select::make('evidence_method')
                        ->options([
                            'text' => 'Text Content',
                            'file' => 'File Upload',
                            'url' => 'URL Reference',
                        ])
                        ->default('text')
                        ->required()
                        ->reactive(),

                    Forms\Components\Textarea::make('content')
                        ->label('Content')
                        ->rows(5)
                        ->visible(fn ($get) => $get('evidence_method') === 'text')
                        ->required(fn ($get) => $get('evidence_method') === 'text'),

                    Forms\Components\FileUpload::make('file')
                        ->label('File')
                        ->visible(fn ($get) => $get('evidence_method') === 'file')
                        ->required(fn ($get) => $get('evidence_method') === 'file')
                        ->disk('local')
                        ->directory('chargebacks/evidence'),

                    Forms\Components\TextInput::make('url')
                        ->url()
                        ->visible(fn ($get) => $get('evidence_method') === 'url')
                        ->required(fn ($get) => $get('evidence_method') === 'url'),
                ])
                ->action(fn ($data) => $this->addEvidence($data)),

            Action::make('export_text')
                ->label('Export as Text')
                ->icon('heroicon-o-document-text')
                ->action(fn () => $this->exportEvidence('text')),

            Action::make('export_pdf')
                ->label('Export as PDF')
                ->icon('heroicon-o-document')
                ->action(fn () => $this->exportEvidence('pdf')),

            Action::make('back')
                ->url(ChargebackCaseResource::getUrl('view', ['record' => $this->record]))
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(ChargebackEvidence::where('chargeback_case_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ChargebackEvidenceType::from($state)->label()),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50),

                Tables\Columns\IconColumn::make('submitted_to_issuer_at')
                    ->label('Submitted')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(ChargebackEvidenceType::labels()),

                Tables\Filters\Filter::make('submitted')
                    ->label('Submitted Only')
                    ->query(fn ($query) => $query->whereNotNull('submitted_to_issuer_at')),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->modal()
                    ->modalContent(fn ($record) => view(
                        'components.chargeback-evidence-detail',
                        ['evidence' => $record]
                    )),

                Tables\Actions\Action::make('mark_submitted')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => !$record->isSubmitted())
                    ->action(fn ($record) => $this->markAsSubmitted($record)),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function addEvidence(array $data): void
    {
        try {
            $evidence = ChargebackEvidence::create([
                'chargeback_case_id' => $this->record->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'content' => $data['evidence_method'] === 'text' ? $data['content'] : null,
                'file_path' => $data['evidence_method'] === 'file' ? $data['file'] : null,
                'url' => $data['evidence_method'] === 'url' ? $data['url'] : null,
                'uploaded_by' => auth()->id(),
            ]);

            Notification::make()
                ->title('Evidence Added')
                ->success()
                ->send();

            $this->table()->resetPageTable();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error adding evidence')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function markAsSubmitted(ChargebackEvidence $evidence): void
    {
        $evidence->update(['submitted_to_issuer_at' => now()]);

        Notification::make()
            ->title('Evidence Marked as Submitted')
            ->success()
            ->send();

        $this->table()->resetPageTable();
    }

    private function exportEvidence(string $format): void
    {
        try {
            $service = app(ChargebackEvidenceService::class);
            $bundle = $format === 'pdf' 
                ? $service->exportAsPdf($this->record)
                : $service->exportAsText($this->record);

            Notification::make()
                ->title('Evidence Bundle Generated')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error generating bundle')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTitle(): string
    {
        return 'Manage Evidence';
    }
}
