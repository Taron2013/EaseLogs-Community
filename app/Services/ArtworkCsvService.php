<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\User;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArtworkCsvService
{
    /**
     * Approved Community Edition CSV columns in export order.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'title',
        'start_date',
        'completed_date',
        'artwork_type',
        'medium',
        'height',
        'width',
        'depth',
        'dimension_unit',
        'notes',
    ];

    /**
     * Headers that must never appear in Community Edition CSV files.
     *
     * @var list<string>
     */
    private const DISALLOWED_HEADERS = [
        'id',
        'user_id',
        'photo',
        'photo_path',
        'image',
        'file_path',
        'created_at',
        'updated_at',
        'inventory_code',
        'sku',
        'status',
        'condition',
        'location',
        'storage_area',
        'estimated_value',
        'sale_price',
        'currency',
        'description',
        'surface',
        'category',
        'style',
        'subject',
    ];

    public function downloadResponse(EloquentCollection $artworks): StreamedResponse
    {
        $filename = 'easelogs-artworks-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($artworks): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::COLUMNS);

            foreach ($artworks as $artwork) {
                fputcsv($handle, $this->rowFromArtwork($artwork));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{created: int}
     */
    public function import(UploadedFile $file, User $user): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new \InvalidArgumentException('The CSV file could not be read.');
        }

        $headerRow = fgetcsv($handle);

        if ($headerRow === false) {
            fclose($handle);

            throw new \InvalidArgumentException('The CSV file is empty.');
        }

        $headers = $this->normalizeHeaders($headerRow);
        $this->validateHeaders($headers);

        $rows = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $rows[] = [
                'line' => $lineNumber,
                'values' => $this->mapRowToColumns($headers, $row),
            ];
        }

        fclose($handle);

        $errors = [];

        foreach ($rows as $index => $row) {
            $normalized = $this->normalizeDateFields($row['values'], $row['line'], $errors);
            $rowErrors = $this->validateRow($normalized, $row['line']);
            array_push($errors, ...$rowErrors);
            $rows[$index]['values'] = $normalized;
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        $created = 0;

        DB::transaction(function () use (&$rows, $user, &$created): void {
            foreach ($rows as $row) {
                Artwork::create([
                    'user_id' => $user->id,
                    ...$this->attributesFromRow($row['values']),
                ]);
                $created++;
            }
        });

        return ['created' => $created];
    }

    /**
     * @return list<string|int|float|null>
     */
    private function rowFromArtwork(Artwork $artwork): array
    {
        return [
            $artwork->title,
            $artwork->start_date?->format('Y-m-d'),
            $artwork->completed_date?->format('Y-m-d'),
            $artwork->artwork_type,
            $artwork->medium,
            $this->formatDecimal($artwork->height),
            $this->formatDecimal($artwork->width),
            $this->formatDecimal($artwork->depth),
            $artwork->dimension_unit,
            $artwork->notes,
        ];
    }

    private function formatDecimal(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  list<string|null>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(
            fn (?string $header): string => $this->normalizeHeaderName($header),
            $headers
        );
    }

    private function normalizeHeaderName(?string $header): string
    {
        $header = strtolower(trim((string) $header));

        if (str_starts_with($header, "\xEF\xBB\xBF")) {
            $header = substr($header, 3);
        }

        return $header;
    }

    /**
     * @param  list<string>  $headers
     */
    private function validateHeaders(array $headers): void
    {
        if ($headers === [] || $headers === ['']) {
            throw new \InvalidArgumentException('The CSV file must include a header row.');
        }

        $hasApprovedColumn = false;

        foreach ($headers as $header) {
            if ($header === '') {
                throw new \InvalidArgumentException('The CSV header row contains an empty column name.');
            }

            if (in_array($header, self::DISALLOWED_HEADERS, true)) {
                throw new \InvalidArgumentException("The CSV column \"{$header}\" is not allowed in Community Edition.");
            }

            if (in_array($header, self::COLUMNS, true)) {
                $hasApprovedColumn = true;
            }
        }

        if (! $hasApprovedColumn) {
            throw new \InvalidArgumentException(
                'The CSV must include at least one approved metadata column (for example title).'
            );
        }

        if (count($headers) !== count(array_unique($headers))) {
            throw new \InvalidArgumentException('The CSV header row contains duplicate column names.');
        }
    }

    private function isApprovedColumn(string $header): bool
    {
        return in_array($header, self::COLUMNS, true);
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     * @return array<string, string|null>
     */
    private function mapRowToColumns(array $headers, array $row): array
    {
        $mapped = array_fill_keys(self::COLUMNS, null);

        foreach ($headers as $index => $header) {
            if (! $this->isApprovedColumn($header)) {
                continue;
            }

            $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
            if ($mapped[$header] === '') {
                $mapped[$header] = null;
            }
        }

        return $mapped;
    }

    /**
     * Normalize a CSV date or date-time string to YYYY-MM-DD for database storage.
     *
     * Slash-separated dates use US order (MM/DD/YYYY or MM/DD/YY). Returns null for blank or unparseable values.
     */
    public function normalizeDateForImport(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T\s]\d{1,2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)?$/', $value, $matches)) {
            $parsed = $this->parseDateWithFormat($matches[1], 'Y-m-d');

            if ($parsed !== null) {
                return $parsed->format('Y-m-d');
            }
        }

        foreach ($this->importDateFormats() as $format) {
            $parsed = $this->parseDateWithFormat($value, $format);

            if ($parsed !== null) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Explicit import formats, most specific first. US slash and dash dates use MM/DD/YYYY or MM/DD/YY.
     *
     * @return list<string>
     */
    private function importDateFormats(): array
    {
        return [
            'Y-m-d',
            'Y/m/d',
            'Y.m.d',
            'n/j/Y',
            'm/d/Y',
            'n-j-Y',
            'm-d-Y',
            'n/j/y',
            'm/d/y',
            'n-j-y',
            'm-d-y',
            'F j, Y',
            'F j Y',
            'j F Y',
        ];
    }

    private function parseDateWithFormat(string $value, string $format): ?Carbon
    {
        try {
            $parsed = Carbon::createFromFormat($format, $value, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }

        if ($parsed === false) {
            return null;
        }

        $errors = Carbon::getLastErrors();

        if (($errors['error_count'] ?? 0) > 0 || ($errors['warning_count'] ?? 0) > 0) {
            return null;
        }

        if ($this->formatUsesTwoDigitYear($format)) {
            $parsed = $parsed->setYear($this->expandTwoDigitYear((int) $parsed->format('y')));
        }

        if ($parsed->format($format) !== $value) {
            return null;
        }

        return $parsed;
    }

    private function formatUsesTwoDigitYear(string $format): bool
    {
        return str_contains($format, 'y') && ! str_contains($format, 'Y');
    }

    /**
     * Map two-digit years to four-digit years (00–69 => 2000–2069, 70–99 => 1970–1999).
     */
    private function expandTwoDigitYear(int $twoDigitYear): int
    {
        if ($twoDigitYear >= 0 && $twoDigitYear <= 69) {
            return 2000 + $twoDigitYear;
        }

        return 1900 + $twoDigitYear;
    }

    /**
     * @param  array<string, string|null>  $values
     * @param  list<string>  $errors
     * @return array<string, string|null>
     */
    private function normalizeDateFields(array $values, int $line, array &$errors): array
    {
        foreach (['start_date', 'completed_date'] as $dateField) {
            $raw = $values[$dateField] ?? null;

            if ($raw === null) {
                continue;
            }

            $normalized = $this->normalizeDateForImport($raw);

            if ($normalized === null) {
                $errors[] = "Row {$line}: Invalid {$dateField} \"{$raw}\". Use a recognizable date (for example YYYY-MM-DD or MM/DD/YYYY).";

                continue;
            }

            $values[$dateField] = $normalized;
        }

        return $values;
    }

    /**
     * @param  array<string, string|null>  $values
     * @return list<string>
     */
    private function validateRow(array $values, int $line): array
    {
        $errors = [];

        if (
            $values['start_date'] !== null
            && $values['completed_date'] !== null
            && $values['completed_date'] < $values['start_date']
        ) {
            $errors[] = "Row {$line}: completed_date must be on or after start_date.";
        }

        foreach (['height', 'width', 'depth'] as $numericField) {
            $value = $values[$numericField] ?? null;

            if ($value === null) {
                continue;
            }

            if (! is_numeric($value) || (float) $value < 0) {
                $errors[] = "Row {$line}: Invalid {$numericField} \"{$value}\". Use a non-negative number.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, mixed>
     */
    private function attributesFromRow(array $values): array
    {
        $title = $values['title'] ?? null;
        $title = $title === null ? '' : $title;

        return [
            'title' => $title,
            'start_date' => $values['start_date'],
            'completed_date' => $values['completed_date'],
            'artwork_type' => $values['artwork_type'],
            'medium' => $values['medium'],
            'height' => $values['height'],
            'width' => $values['width'],
            'depth' => $values['depth'],
            'dimension_unit' => $values['dimension_unit'] ?? 'in',
            'notes' => $values['notes'],
        ];
    }
}
