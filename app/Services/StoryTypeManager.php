<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;

class StoryTypeManager
{
    /**
     * Get all available story types
     */
    public static function getTypes(): array
    {
        return Config::get('stories.types', []);
    }

    /**
     * Get configuration for a specific story type
     */
    public static function getTypeConfig(string $type): ?array
    {
        return Config::get("stories.types.{$type}");
    }

    /**
     * Check if a story type is valid
     */
    public static function isValidType(string $type): bool
    {
        return array_key_exists($type, self::getTypes());
    }

    /**
     * Get the default story type
     */
    public static function getDefaultType(): string
    {
        return Config::get('stories.default_type', 'announcement');
    }

    /**
     * Validate story content against type configuration
     */
    public static function validateContent(string $type, array $content): array
    {
        $config = self::getTypeConfig($type);
        
        if (!$config) {
            return ['valid' => false, 'errors' => ['Invalid story type']];
        }

        $errors = [];
        $fields = $config['fields'] ?? [];

        foreach ($fields as $fieldName => $fieldConfig) {
            $isRequired = $fieldConfig['required'] ?? false;
            $value = $content[$fieldName] ?? null;

            if ($isRequired && empty($value)) {
                $errors[] = "Field '{$fieldName}' is required for {$type} stories";
            }

            if ($value !== null) {
                $validationError = self::validateFieldType($fieldName, $value, $fieldConfig);
                if ($validationError) {
                    $errors[] = $validationError;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate individual field type
     */
    private static function validateFieldType(string $fieldName, $value, array $config): ?string
    {
        $type = $config['type'] ?? 'string';

        switch ($type) {
            case 'number':
                if (!is_numeric($value)) {
                    return "Field '{$fieldName}' must be a number";
                }
                break;
            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    return "Field '{$fieldName}' must be a boolean";
                }
                break;
            case 'datetime':
                if (!strtotime($value)) {
                    return "Field '{$fieldName}' must be a valid datetime";
                }
                break;
            case 'select':
                $options = $config['options'] ?? [];
                if (!in_array($value, $options, true)) {
                    return "Field '{$fieldName}' must be one of: " . implode(', ', $options);
                }
                break;
        }

        return null;
    }

    /**
     * Format story content for API response
     */
    public static function formatContent(string $type, array $content): array
    {
        $config = self::getTypeConfig($type);
        
        if (!$config) {
            return $content;
        }

        $formatted = [];
        $fields = $config['fields'] ?? [];

        foreach ($fields as $fieldName => $fieldConfig) {
            if (isset($content[$fieldName])) {
                $formatted[$fieldName] = self::formatFieldValue($content[$fieldName], $fieldConfig);
            }
        }

        return $formatted;
    }

    /**
     * Format individual field value
     */
    private static function formatFieldValue($value, array $config)
    {
        $type = $config['type'] ?? 'string';

        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float) $value : $value;
            case 'boolean':
                return (bool) $value;
            case 'datetime':
                return $value;
            default:
                return (string) $value;
        }
    }

    /**
     * Get story type options for admin select
     */
    public static function getTypeOptions(): array
    {
        $types = self::getTypes();
        $options = [];

        foreach ($types as $key => $config) {
            $options[$key] = $config['label'] ?? ucfirst($key);
        }

        return $options;
    }

    /**
     * Get fields for a specific story type
     */
    public static function getFieldsForType(string $type): array
    {
        $config = self::getTypeConfig($type);
        return $config['fields'] ?? [];
    }

    /**
     * Check if CTA is required for a story type
     */
    public static function isCtaRequired(string $type): bool
    {
        $config = self::getTypeConfig($type);
        return $config['cta_required'] ?? false;
    }
}
