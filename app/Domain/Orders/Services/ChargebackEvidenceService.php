<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\ChargebackCase;
use App\Domain\Orders\Models\ChargebackEvidenceBundle;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ChargebackEvidenceService
{
    /**
     * Generate a text-based evidence bundle summary.
     */
    public function generateSummary(ChargebackCase $case): string
    {
        $summary = $this->buildSummaryHeader($case);
        $summary .= $this->buildOrderDetails($case);
        $summary .= $this->buildChargebackDetails($case);
        $summary .= $this->buildEvidenceSection($case);
        $summary .= $this->buildFooter($case);

        return $summary;
    }

    /**
     * Export evidence as text file.
     */
    public function exportAsText(ChargebackCase $case): ChargebackEvidenceBundle
    {
        $summary = $this->generateSummary($case);
        $filename = sprintf(
            'chargebacks/case_%s_%s.txt',
            $case->case_number,
            now()->format('Y-m-d-His')
        );

        Storage::disk('local')->put($filename, $summary);

        return ChargebackEvidenceBundle::create([
            'chargeback_case_id' => $case->id,
            'format' => 'text',
            'file_path' => $filename,
            'summary' => $summary,
            'created_by' => auth()?->id(),
        ]);
    }

    /**
     * Export evidence as PDF (requires barryvdh/laravel-dompdf).
     * This is a stub - implement PDF generation as needed.
     */
    public function exportAsPdf(ChargebackCase $case): ChargebackEvidenceBundle
    {
        $summary = $this->generateSummary($case);
        
        // If you have PDF library installed:
        // $pdf = \PDF::loadHTML($summary);
        // $filename = sprintf('chargebacks/case_%s_%s.pdf', $case->case_number, now()->format('Y-m-d-His'));
        // Storage::disk('local')->put($filename, $pdf->output());
        
        // For now, create as text
        $filename = sprintf(
            'chargebacks/case_%s_%s.pdf',
            $case->case_number,
            now()->format('Y-m-d-His')
        );

        Storage::disk('local')->put($filename, $summary);

        return ChargebackEvidenceBundle::create([
            'chargeback_case_id' => $case->id,
            'format' => 'pdf',
            'file_path' => $filename,
            'summary' => $summary,
            'created_by' => auth()?->id(),
        ]);
    }

    /**
     * Mark bundle as submitted to issuer.
     */
    public function markAsSubmitted(ChargebackEvidenceBundle $bundle): void
    {
        $bundle->update(['submitted_to_issuer_at' => now()]);
    }

    /**
     * Get download URL for evidence bundle.
     */
    public function getDownloadUrl(ChargebackEvidenceBundle $bundle): string
    {
        return route('admin.chargebacks.evidence.download', ['bundle' => $bundle->id]);
    }

    /**
     * Get all evidence files for case.
     */
    public function getAllEvidenceFiles(ChargebackCase $case): \Illuminate\Support\Collection
    {
        return $case->evidence()
            ->whereNotNull('file_path')
            ->orderBy('type')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get evidence by type.
     */
    public function getEvidenceByType(ChargebackCase $case, string $type): \Illuminate\Database\Eloquent\Collection
    {
        return $case->getEvidenceByType($type);
    }

    /**
     * Calculate total evidence submitted to issuer.
     */
    public function getSubmittedEvidenceCount(ChargebackCase $case): int
    {
        return $case->evidence()
            ->whereNotNull('submitted_to_issuer_at')
            ->count();
    }

    /**
     * Get evidence statistics.
     */
    public function getEvidenceStats(ChargebackCase $case): array
    {
        $evidence = $case->evidence;

        return [
            'total' => $evidence->count(),
            'submitted' => $evidence->whereNotNull('submitted_to_issuer_at')->count(),
            'by_type' => $evidence->groupBy('type')
                ->map(fn ($items) => $items->count())
                ->toArray(),
            'total_size' => $case->getTotalEvidenceSize(),
        ];
    }

    // Private helper methods

    private function buildSummaryHeader(ChargebackCase $case): string
    {
        return <<<EOT
================================================================================
                      CHARGEBACK EVIDENCE SUMMARY
================================================================================

Generated: {$this->formatDateTime(now())}
Case Number: {$case->case_number}
Status: {$case->status->label()}

================================================================================

EOT;
    }

    private function buildOrderDetails(ChargebackCase $case): string
    {
        $order = $case->order;
        $daysUntilDue = $case->daysUntilDue();
        $dueStatus = $daysUntilDue === null 
            ? 'N/A' 
            : ($daysUntilDue < 0 ? "OVERDUE ({$daysUntilDue} days)" : "Due in {$daysUntilDue} days");

        $resolvedAt = $case->resolved_at ? $this->formatDateTime($case->resolved_at) : 'Pending';
        $policiesVersion = $order->policies_version ?? 'N/A';
        $policiesHash = $order->policies_hash ? substr($order->policies_hash, 0, 16) . '...' : 'N/A';

        return <<<EOT
ORDER INFORMATION
================================================================================
Order Number:          {$order->number}
Order ID:              {$order->id}
Order Date:            {$this->formatDateTime($order->placed_at)}
Order Amount:          {$this->formatCurrency($order->grand_total, $order->currency)}
Payment Reference:     {$case->payment_reference}

CHARGEBACK INFORMATION
================================================================================
Case Number:           {$case->case_number}
Status:                {$case->status->label()}
Amount:                {$this->formatCurrency($case->amount)}
Reason Code:           {$case->reason_code}
Reason:                {$case->reason_description}
Card (Last 4):         {$case->card_last_four}
Transaction Date:      {$this->formatDate($case->transaction_date)}
Chargeback Date:       {$this->formatDate($case->chargeback_date)}
Due Date:              {$this->formatDate($case->due_date)} ({$dueStatus})
Opened At:             {$this->formatDateTime($case->opened_at)}
Resolved At:           {$resolvedAt}

Policies Version:      {$policiesVersion}
Policies Hash:         {$policiesHash}

================================================================================

EOT;
    }

    private function buildChargebackDetails(ChargebackCase $case): string
    {
        $customerStatement = $case->customer_statement ?? 'Not provided';
        $merchantResponse = $case->merchant_response ?? 'Not yet provided';
        $resolutionNotes = $case->resolution_notes ?? 'Case not yet resolved';

        return <<<EOT
STATEMENTS & RESPONSES
================================================================================

CUSTOMER CLAIM:
{$this->wrapText($customerStatement, 80, 2)}

OUR RESPONSE:
{$this->wrapText($merchantResponse, 80, 2)}

RESOLUTION NOTES:
{$this->wrapText($resolutionNotes, 80, 2)}

================================================================================

EOT;
    }

    private function buildEvidenceSection(ChargebackCase $case): string
    {
        $evidence = $case->evidence()->orderBy('type')->orderByDesc('created_at')->get();
        
        if ($evidence->isEmpty()) {
            return <<<EOT
EVIDENCE
================================================================================
No evidence items on file.

================================================================================

EOT;
        }

        $section = <<<EOT
EVIDENCE
================================================================================

EOT;

        $currentType = null;
        foreach ($evidence as $item) {
            $typeName = $item->type->label();
            
            if ($typeName !== $currentType) {
                $currentType = $typeName;
                $section .= "\n{$typeName}:\n";
                $section .= str_repeat("-", 80) . "\n";
            }

            $submitted = $item->submitted_to_issuer_at 
                ? "✓ Submitted: {$this->formatDateTime($item->submitted_to_issuer_at)}"
                : "✗ Not submitted";

            $section .= "\n[{$item->id}] {$item->title}\n";
            $section .= "  Uploaded: {$this->formatDateTime($item->created_at)}\n";
            $section .= "  Status: {$submitted}\n";

            if ($item->description) {
                $section .= "  Description:\n";
                $section .= $this->wrapText($item->description, 80, 4);
            }

            if ($item->isText()) {
                $section .= "  Content:\n";
                $section .= $this->wrapText(substr($item->content, 0, 200), 80, 4);
                if (strlen($item->content) > 200) {
                    $section .= $this->wrapText("... (truncated)", 80, 4);
                }
            } elseif ($item->isFile()) {
                $section .= "  File: " . basename($item->file_path) . " ({$item->getFileSize()})\n";
            } elseif ($item->isUrl()) {
                $section .= "  URL: {$item->url}\n";
            }

            $section .= "\n";
        }

        $section .= str_repeat("=", 80) . "\n\n";

        return $section;
    }

    private function buildFooter(ChargebackCase $case): string
    {
        $stats = $this->getEvidenceStats($case);

        return <<<EOT
EVIDENCE SUMMARY
================================================================================
Total Evidence Items:  {$stats['total']}
Submitted to Issuer:   {$stats['submitted']}
Total Evidence Size:   {$this->formatBytes($stats['total_size'])}

Bundle Generated:      {$this->formatDateTime(now())}
Generated By:          {auth()->user()?->name ?? 'System'}

================================================================================

CONFIDENTIAL - FOR DISPUTE RESOLUTION ONLY
This document contains evidence compiled to support the merchant's response
to a chargeback claim. Distribution should be limited to authorized personnel
and the applicable payment processor.

================================================================================
EOT;
    }

    // Utility methods

    private function formatDateTime($date): string
    {
        if (!$date) return 'N/A';
        return $date instanceof Carbon 
            ? $date->format('Y-m-d H:i:s')
            : Carbon::parse($date)->format('Y-m-d H:i:s');
    }

    private function formatDate($date): string
    {
        if (!$date) return 'N/A';
        return $date instanceof Carbon 
            ? $date->format('Y-m-d')
            : Carbon::parse($date)->format('Y-m-d');
    }

    private function formatCurrency(string|int|float $amount, string $currency = 'USD'): string
    {
        $symbol = match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            default => $currency . ' ',
        };

        return $symbol . number_format((float)$amount, 2);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1 << (10 * $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function wrapText(string $text, int $width = 80, int $indent = 0): string
    {
        $prefix = str_repeat(' ', $indent);
        $wrapped = wordwrap($text, $width - $indent, "\n", true);
        return $prefix . str_replace("\n", "\n" . $prefix, $wrapped) . "\n";
    }
}
