<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class EnvEditor
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? app()->environmentFilePath();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $values = $this->parse();

        return $values[$key] ?? $default;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, string|null>
     */
    public function getMany(array $keys): array
    {
        $values = $this->parse();
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $values[$key] ?? null;
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->parse();
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     */
    public function setMany(array $values): void
    {
        $lines = $this->readLines();
        $current = $this->parseLines($lines);
        $pending = $values;

        foreach ($lines as $index => $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key] = explode('=', $line, 2);
            $key = trim($key);

            if ($key === '' || ! array_key_exists($key, $values)) {
                continue;
            }

            $newValue = $values[$key];
            $currentValue = $current[$key] ?? null;

            if ($this->normalizeValue($newValue) === $this->normalizeValue($currentValue)) {
                unset($pending[$key]);
                continue;
            }

            $lines[$index] = $key . '=' . $this->formatValue($newValue);
            unset($pending[$key]);
        }

        if (! empty($pending)) {
            if (! empty($lines) && trim(end($lines)) !== '') {
                $lines[] = '';
            }

            foreach ($pending as $key => $value) {
                $lines[] = $key . '=' . $this->formatValue($value);
            }
        }

        $content = implode(PHP_EOL, $lines);
        if (! str_ends_with($content, PHP_EOL)) {
            $content .= PHP_EOL;
        }

        $this->write($content);
    }

    /**
     * @return array<int, string>
     */
    private function readLines(): array
    {
        if (! is_file($this->path)) {
            throw new RuntimeException('Environment file not found.');
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read environment file.');
        }

        return $lines;
    }

    private function write(string $content): void
    {
        $result = file_put_contents($this->path, $content);
        if ($result === false) {
            throw new RuntimeException('Unable to write environment file.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function parse(): array
    {
        return $this->parseLines($this->readLines());
    }

    /**
     * @param array<int, string> $lines
     * @return array<string, string>
     */
    private function parseLines(array $lines): array
    {
        $values = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $values[$key] = $this->stripQuotes(trim($value));
        }

        return $values;
    }

    private function stripQuotes(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
            if ($first === '"') {
                $value = str_replace('\"', '"', $value);
            }
        }

        return $value;
    }

    private function formatValue(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;
        $value = str_replace(["\r", "\n"], '', $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|#|"|=/', $value)) {
            $escaped = str_replace('"', '\\"', $value);
            return '"' . $escaped . '"';
        }

        return $value;
    }

    private function normalizeValue(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return trim((string) $value);
    }
}
