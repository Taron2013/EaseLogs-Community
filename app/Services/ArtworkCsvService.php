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

        foreach ($rows as $row) {
            $rowErrors = $this->validateRow($row['values'], $row['line']);
            array_push($errors, ...$rowErrors);
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        $created = 0;

        DB::transaction(function () use ($rows, $user, &$created): void {
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
            static fn (?string $header): string => strtolower(trim((string) $header)),
            $headers
        );
    }

    /**
     * @param  list<string>  $headers
     */
    private function validateHeaders(array $headers): void
    {
        if ($headers === [] || $headers === ['']) {
            throw new \InvalidArgumentException('The CSV file must include a header row.');
        }

        foreach ($headers as $header) {
            if ($header === '') {
                throw new \InvalidArgumentException('The CSV header row contains an empty column name.');
            }

            if (in_array($header, self::DISALLOWED_HEADERS, true)) {
                throw new \InvalidArgumentException("The CSV column \"{$header}\" is not allowed in Community Edition.");
            }

            if (! in_array($header, self::COLUMNS, true)) {
                throw new \InvalidArgumentException("Unknown CSV column \"{$header}\". Use only approved Community Edition metadata fields.");
            }
        }

        if (count($headers) !== count(array_unique($headers))) {
            throw new \InvalidArgumentException('The CSV header row contains duplicate column names.');
        }
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
            $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
            if ($mapped[$header] === '') {
                $mapped[$header] = null;
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string, string|null>  $values
     * @return list<string>
     */
    private function validateRow(array $values, int $line): array
    {
        $errors = [];

        foreach (['start_date', 'completed_date'] as $dateField) {
            $value = $values[$dateField] ?? null;

            if ($value === null) {
                continue;
            }

            if (! $this->isValidDate($value)) {
                $errors[] = "Row {$line}: Invalid {$dateField} \"{$value}\". Use YYYY-MM-DD format.";
            }
        }

        if (
            $values['start_date'] !== null
            && $values['completed_date'] !== null
            && $this->isValidDate($values['start_date'])
            && $this->isValidDate($values['completed_date'])
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

    private function isValidDate(string $value): bool
    {
        try {
            Carbon::createFromFormat('Y-m-d', $value);

            return true;
        } catch (InvalidFormatException) {
            return false;
        }
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, mixed>
     */
    private function attributesFromRow(array $values): array
    {
        return [
            'title' => $values['title'] ?? '',
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
