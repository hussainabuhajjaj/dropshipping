<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PricingAuditService
{
    private array $auditData = [];
    private string $sessionId;
    
    public function __construct()
    {
        $this->sessionId = uniqid('pricing_audit_', true);
    }
    
    /**
     * Log pricing operation with strict validation
     */
    public function logPricingOperation(array $data): void
    {
        $this->validateAuditData($data);
        
        $auditEntry = [
            'session_id' => $this->sessionId,
            'timestamp' => now()->toISOString(),
            'operation_type' => $data['operation_type'] ?? 'unknown',
            'product_id' => $data['product_id'] ?? null,
            'variant_id' => $data['variant_id'] ?? null,
            'user_id' => auth()->id(),
            'user_type' => $this->getUserType(),
            'old_values' => $this->sanitizeValues($data['old_values'] ?? []),
            'new_values' => $this->sanitizeValues($data['new_values'] ?? []),
            'source' => $data['source'] ?? 'manual',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'validation_errors' => $data['validation_errors'] ?? [],
            'business_rules_violated' => $data['business_rules_violated'] ?? [],
            'profit_impact' => $this->calculateProfitImpact($data),
            'compliance_flags' => $this->checkCompliance($data),
        ];
        
        $this->auditData[] = $auditEntry;
        
        // Log to system logger for immediate visibility
        Log::info('Pricing operation audited', $auditEntry);
        
        // Store in database for long-term retention
        $this->storeAuditEntry($auditEntry);
    }
    
    /**
     * Validate audit data integrity
     */
    private function validateAuditData(array $data): void
    {
        $required = ['operation_type'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Required audit field '{$field}' is missing");
            }
        }
        
        // Validate product/variant IDs
        if (isset($data['product_id']) && !is_numeric($data['product_id'])) {
            throw new \InvalidArgumentException('Product ID must be numeric');
        }
        
        if (isset($data['variant_id']) && !is_numeric($data['variant_id'])) {
            throw new \InvalidArgumentException('Variant ID must be numeric');
        }
    }
    
    /**
     * Sanitize values to prevent injection
     */
    private function sanitizeValues(array $values): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            return $value;
        }, $values);
    }
    
    /**
     * Calculate profit impact of pricing changes
     */
    private function calculateProfitImpact(array $data): array
    {
        $impact = [
            'old_profit' => 0,
            'new_profit' => 0,
            'profit_change' => 0,
            'margin_change' => 0,
        ];
        
        $oldValues = $data['old_values'] ?? [];
        $newValues = $data['new_values'] ?? [];
        
        if (isset($oldValues['cost_price'], $oldValues['selling_price'])) {
            $impact['old_profit'] = $oldValues['selling_price'] - $oldValues['cost_price'];
        }
        
        if (isset($newValues['cost_price'], $newValues['selling_price'])) {
            $impact['new_profit'] = $newValues['selling_price'] - $newValues['cost_price'];
        }
        
        $impact['profit_change'] = $impact['new_profit'] - $impact['old_profit'];
        
        if (isset($oldValues['cost_price']) && $oldValues['cost_price'] > 0) {
            $oldMargin = ($impact['old_profit'] / $oldValues['cost_price']) * 100;
            $newMargin = ($impact['new_profit'] / $oldValues['cost_price']) * 100;
            $impact['margin_change'] = $newMargin - $oldMargin;
        }
        
        return $impact;
    }
    
    /**
     * Check compliance with business rules
     */
    private function checkCompliance(array $data): array
    {
        $flags = [];
        
        $newValues = $data['new_values'] ?? [];
        
        // Check for negative margins
        if (isset($newValues['cost_price'], $newValues['selling_price'])) {
            $margin = $newValues['selling_price'] - $newValues['cost_price'];
            if ($margin < 0) {
                $flags[] = 'negative_margin';
            }
            
            $marginPercent = ($margin / $newValues['cost_price']) * 100;
            if ($marginPercent > config('pricing.maximum_margin_percent', 1000)) {
                $flags[] = 'excessive_margin';
            }
            
            if ($marginPercent < config('pricing.minimum_margin_percent', 10)) {
                $flags[] = 'below_minimum_margin';
            }
        }
        
        // Check for price changes beyond thresholds
        $oldValues = $data['old_values'] ?? [];
        if (isset($oldValues['selling_price'], $newValues['selling_price'])) {
            $priceChange = abs($newValues['selling_price'] - $oldValues['selling_price']);
            $changePercent = ($priceChange / $oldValues['selling_price']) * 100;
            
            if ($changePercent > config('pricing.max_price_change_percent', 50)) {
                $flags[] = 'excessive_price_change';
            }
        }
        
        return $flags;
    }
    
    /**
     * Get current user type
     */
    private function getUserType(): string
    {
        if (auth()->check()) {
            if (auth()->user()->hasRole('admin')) {
                return 'admin';
            } elseif (auth()->user()->hasRole('manager')) {
                return 'manager';
            }
            return 'user';
        }
        
        return 'system';
    }
    
    /**
     * Store audit entry in database
     */
    private function storeAuditEntry(array $entry): void
    {
        try {
            DB::table('pricing_audit_log')->insert([
                'session_id' => $entry['session_id'],
                'timestamp' => $entry['timestamp'],
                'operation_type' => $entry['operation_type'],
                'product_id' => $entry['product_id'],
                'variant_id' => $entry['variant_id'],
                'user_id' => $entry['user_id'],
                'user_type' => $entry['user_type'],
                'old_values' => json_encode($entry['old_values']),
                'new_values' => json_encode($entry['new_values']),
                'source' => $entry['source'],
                'ip_address' => $entry['ip_address'],
                'user_agent' => $entry['user_agent'],
                'validation_errors' => json_encode($entry['validation_errors']),
                'business_rules_violated' => json_encode($entry['business_rules_violated']),
                'profit_impact' => json_encode($entry['profit_impact']),
                'compliance_flags' => json_encode($entry['compliance_flags']),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store pricing audit entry', [
                'error' => $e->getMessage(),
                'entry' => $entry
            ]);
        }
    }
    
    /**
     * Get audit summary for session
     */
    public function getSessionSummary(): array
    {
        return [
            'session_id' => $this->sessionId,
            'total_operations' => count($this->auditData),
            'operations_by_type' => $this->groupOperationsByType(),
            'compliance_issues' => $this->countComplianceIssues(),
            'total_profit_impact' => $this->calculateTotalProfitImpact(),
        ];
    }
    
    private function groupOperationsByType(): array
    {
        $grouped = [];
        foreach ($this->auditData as $entry) {
            $type = $entry['operation_type'];
            $grouped[$type] = ($grouped[$type] ?? 0) + 1;
        }
        return $grouped;
    }
    
    private function countComplianceIssues(): array
    {
        $issues = [];
        foreach ($this->auditData as $entry) {
            foreach ($entry['compliance_flags'] as $flag) {
                $issues[$flag] = ($issues[$flag] ?? 0) + 1;
            }
        }
        return $issues;
    }
    
    private function calculateTotalProfitImpact(): float
    {
        $total = 0;
        foreach ($this->auditData as $entry) {
            $total += $entry['profit_impact']['profit_change'] ?? 0;
        }
        return $total;
    }
}
