<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SegmentEngine
{
    private const FIELD_MAP = [
        'locale' => ['field' => 'locale', 'type' => 'string'],
        'preferred_language' => ['field' => 'preferred_language', 'type' => 'string'],
        'country_code' => ['field' => 'country_code', 'type' => 'string'],
        'city' => ['field' => 'city', 'type' => 'string'],
        'region' => ['field' => 'region', 'type' => 'string'],
        'marketing_opt_in' => ['field' => 'marketing_opt_in', 'type' => 'boolean'],
        'created_at' => ['field' => 'created_at', 'type' => 'date'],
    ];

    private const AGGREGATE_FIELDS = [
        'total_spent',
        'order_count',
        'last_order_days',
        'avg_order_value',
        'max_order_value',
    ];

    public function apply(Builder $query, CustomerSegment $segment): Builder
    {
        if (! $segment->is_active) {
            return $query->whereRaw('0 = 1');
        }

        return $this->applyRules($query, $segment->rules);
    }

    public function count(CustomerSegment $segment): int
    {
        return $this->apply(Customer::query(), $segment)->count();
    }

    public function customers(CustomerSegment $segment, int $limit = 500): Collection
    {
        return $this->apply(Customer::query(), $segment)->limit($limit)->get();
    }

    public function customerIds(CustomerSegment $segment, int $limit = 500): array
    {
        return $this->apply(Customer::query()->select('id'), $segment)
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    public function customerIdQuery(CustomerSegment $segment): Builder
    {
        return $this->apply(Customer::query()->select('id'), $segment);
    }

    public function matches(Customer $customer, CustomerSegment $segment): bool
    {
        if (! $segment->is_active) {
            return false;
        }

        return $this->evaluateNode($segment->rules, $customer);
    }

    private function applyRules(Builder $query, ?array $rules): Builder
    {
        if (! $rules || ! isset($rules['conditions'])) {
            return $query->whereRaw('0 = 1');
        }

        $operator = strtolower($rules['operator'] ?? 'and');
        $conditions = $rules['conditions'] ?? [];

        if (empty($conditions)) {
            return $query->whereRaw('0 = 1');
        }

        $method = $operator === 'or' ? 'orWhere' : 'where';

        return $query->{$method}(function (Builder $q) use ($conditions, $operator): void {
            foreach ($conditions as $condition) {
                if (isset($condition['group'])) {
                    $nestedOp = strtolower($condition['group']['operator'] ?? 'and');
                    $nestedMethod = $operator === 'or' ? 'orWhere' : 'where';
                    $q->{$nestedMethod}(fn (Builder $sub) => $this->applyRulesNode($sub, $condition['group']['conditions'] ?? [], $nestedOp));
                } else {
                    $this->applyCondition($q, $condition, $operator);
                }
            }
        });
    }

    private function applyRulesNode(Builder $query, array $conditions, string $operator): Builder
    {
        $method = $operator === 'or' ? 'orWhere' : 'where';

        foreach ($conditions as $condition) {
            if (isset($condition['group'])) {
                $nestedOp = strtolower($condition['group']['operator'] ?? 'and');
                $nestedMethod = $method;
                $query->{$nestedMethod}(fn (Builder $sub) => $this->applyRulesNode($sub, $condition['group']['conditions'] ?? [], $nestedOp));
            } else {
                $this->applyCondition($query, $condition, $operator);
            }
        }

        return $query;
    }

    private function applyCondition(Builder $query, array $condition, string $parentOperator): void
    {
        $field = $condition['field'] ?? '';
        $op = strtolower($condition['operator'] ?? 'eq');
        $value = $condition['value'] ?? null;

        if (in_array($field, self::AGGREGATE_FIELDS, true)) {
            $this->applyAggregateCondition($query, $field, $op, $value, $parentOperator);
            return;
        }

        $col = self::FIELD_MAP[$field]['field'] ?? $field;

        if (! Schema::hasColumn('customers', $col)) {
            return;
        }

        $method = $parentOperator === 'or' ? 'orWhere' : 'where';

        match ($op) {
            'eq' => $query->{$method}($col, '=', $value),
            'neq' => $query->{$method}($col, '!=', $value),
            'gt' => $query->{$method}($col, '>', $value),
            'gte' => $query->{$method}($col, '>=', $value),
            'lt' => $query->{$method}($col, '<', $value),
            'lte' => $query->{$method}($col, '<=', $value),
            'in' => $query->{$method.'In'}($col, (array) $value),
            'not_in' => $query->{$method.'NotIn'}($col, (array) $value),
            'contains' => $query->{$method}($col, 'like', '%'.$value.'%'),
            'starts_with' => $query->{$method}($col, 'like', $value.'%'),
            'ends_with' => $query->{$method}($col, 'like', '%'.$value),
            'is_null' => $query->{$method.'Null'}($col),
            'is_not_null' => $query->{$method.'NotNull'}($col),
            default => null,
        };
    }

    private function applyAggregateCondition(Builder $query, string $field, string $op, mixed $value, string $parentOperator): void
    {
        $method = $parentOperator === 'or' ? 'orWhere' : 'where';
        $sqlOp = $this->resolveSqlOperator($op);

        if ($sqlOp === null) {
            return;
        }

        if ($field === 'last_order_days') {
            $this->applyLastOrderDaysCondition($query, $op, $value, $method);
            return;
        }

        $query->{$method}(function (Builder $q) use ($field, $sqlOp, $value): void {
            $havingExpr = match ($field) {
                'total_spent' => 'COALESCE(SUM(grand_total), 0) ' . $sqlOp . ' ?',
                'order_count' => 'COUNT(*) ' . $sqlOp . ' ?',
                'avg_order_value' => 'COALESCE(AVG(grand_total), 0) ' . $sqlOp . ' ?',
                'max_order_value' => 'COALESCE(MAX(grand_total), 0) ' . $sqlOp . ' ?',
                default => null,
            };

            if ($havingExpr === null) {
                return;
            }

            $q->whereExists(function (\Illuminate\Database\Query\Builder $sub) use ($havingExpr, $value): void {
                $sub->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.customer_id', 'customers.id')
                    ->where('orders.payment_status', 'paid')
                    ->groupBy('orders.customer_id')
                    ->havingRaw($havingExpr, [$value]);
            });
        });
    }

    private function applyLastOrderDaysCondition(Builder $query, string $op, mixed $value, string $method): void
    {
        $invertedOp = match ($op) {
            'gt' => '<',
            'gte' => '<=',
            'lt' => '>',
            'lte' => '>=',
            'eq' => '=',
            'neq' => '!=',
            default => null,
        };

        if ($invertedOp === null) {
            return;
        }

        $dateThreshold = now()->subDays((int) $value);

        $query->{$method}(function (Builder $q) use ($invertedOp, $dateThreshold): void {
            $q->whereExists(function (\Illuminate\Database\Query\Builder $sub) use ($invertedOp, $dateThreshold): void {
                $sub->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.customer_id', 'customers.id')
                    ->where('orders.payment_status', 'paid')
                    ->groupBy('orders.customer_id')
                    ->havingRaw('COALESCE(MAX(placed_at), ?) ' . $invertedOp . ' ?', [
                        '1970-01-01',
                        $dateThreshold,
                    ]);
            });
        });
    }

    private function resolveSqlOperator(string $op): ?string
    {
        return match ($op) {
            'eq' => '=',
            'neq' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            default => null,
        };
    }

    private function evaluateNode(?array $rules, Customer $customer): bool
    {
        if (! $rules || ! isset($rules['conditions'])) {
            return false;
        }

        $operator = strtolower($rules['operator'] ?? 'and');
        $results = [];

        foreach ($rules['conditions'] as $condition) {
            if (isset($condition['group'])) {
                $results[] = $this->evaluateNode($condition['group'], $customer);
            } else {
                $results[] = $this->evaluateCondition($condition, $customer);
            }
        }

        return match ($operator) {
            'or' => in_array(true, $results, true),
            'and' => ! in_array(false, $results, true),
            default => ! in_array(false, $results, true),
        };
    }

    private function evaluateCondition(array $condition, Customer $customer): bool
    {
        $field = $condition['field'] ?? '';
        $op = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? null;

        $actual = $this->getFieldValue($customer, $field);

        return match ($op) {
            'eq' => $actual === $value,
            'neq' => $actual !== $value,
            'gt' => $actual > $value,
            'gte' => $actual >= $value,
            'lt' => $actual < $value,
            'lte' => $actual <= $value,
            'in' => in_array($actual, (array) $value, true),
            'not_in' => ! in_array($actual, (array) $value, true),
            'contains' => is_string($actual) && str_contains($actual, (string) $value),
            'starts_with' => is_string($actual) && str_starts_with($actual, (string) $value),
            'ends_with' => is_string($actual) && str_ends_with($actual, (string) $value),
            'is_null' => is_null($actual),
            'is_not_null' => ! is_null($actual),
            default => false,
        };
    }

    private function getFieldValue(Customer $customer, string $field): mixed
    {
        if (in_array($field, self::AGGREGATE_FIELDS, true)) {
            return $this->getAggregateValue($customer, $field);
        }

        $col = self::FIELD_MAP[$field]['field'] ?? $field;

        return $customer->{$col} ?? null;
    }

    private function getAggregateValue(Customer $customer, string $field): mixed
    {
        if (! $customer->relationLoaded('orders')) {
            return match ($field) {
                'total_spent' => (float) $customer->orders()->where('payment_status', 'paid')->sum('grand_total'),
                'order_count' => $customer->orders()->where('payment_status', 'paid')->count(),
                'last_order_days' => $customer->orders()
                    ->where('payment_status', 'paid')
                    ->orderByDesc('placed_at')
                    ->first()
                    ?->placed_at
                    ?->diffInDays(now()) ?? 9999,
                'avg_order_value' => (float) $customer->orders()->where('payment_status', 'paid')->avg('grand_total') ?? 0,
                'max_order_value' => (float) $customer->orders()->where('payment_status', 'paid')->max('grand_total') ?? 0,
                default => null,
            };
        }

        $orders = $customer->orders->where('payment_status', 'paid');

        return match ($field) {
            'total_spent' => (float) $orders->sum('grand_total'),
            'order_count' => $orders->count(),
            'last_order_days' => $orders->max('placed_at')?->diffInDays(now()) ?? 9999,
            'avg_order_value' => $orders->avg('grand_total') ?? 0,
            'max_order_value' => $orders->max('grand_total') ?? 0,
            default => null,
        };
    }
}
