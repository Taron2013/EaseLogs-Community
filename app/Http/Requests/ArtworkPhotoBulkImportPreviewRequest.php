<?php

namespace App\Http\Requests;

use App\Support\ArtworkPhotoBulkImport\PhotoImportUploadEnvironment;
use App\Support\ArtworkPhotoBulkImport\PhotoImportUploadLimit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ArtworkPhotoBulkImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo_zip' => PhotoImportUploadLimit::photoZipRules(),
            'mapping_csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $appMaxMb = PhotoImportUploadLimit::maxMegabytes();
        $effectiveMaxMb = PhotoImportUploadEnvironment::report()['effective_max_mb'];

        $maxMessage = sprintf(
            'The photo ZIP may not be larger than %s MB (EaseLogs app limit).',
            number_format($appMaxMb),
        );

        if ($effectiveMaxMb !== null && $effectiveMaxMb < $appMaxMb) {
            $maxMessage = sprintf(
                'The photo ZIP may not be larger than %s MB for this server (PHP upload limits are lower than the EaseLogs app limit of %s MB). Ask your administrator to raise PHP post_max_size, upload_max_filesize, and Nginx client_max_body_size.',
                number_format($effectiveMaxMb),
                number_format($appMaxMb),
            );
        }

        return [
            'photo_zip.required' => PhotoImportUploadEnvironment::missingFileMessage($this->contentLength()),
            'photo_zip.file' => 'The photo ZIP must be a valid uploaded file.',
            'photo_zip.mimes' => 'The photo ZIP must be a .zip archive.',
            'photo_zip.max' => $maxMessage,
            'mapping_csv.mimes' => 'The mapping file must be a CSV.',
            'mapping_csv.max' => 'The mapping CSV may not be larger than 2 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('photo_zip')) {
                return;
            }

            $file = $this->file('photo_zip');

            if ($file instanceof UploadedFile && ! $file->isValid()) {
                $validator->errors()->add(
                    'photo_zip',
                    PhotoImportUploadEnvironment::uploadErrorMessage($file->getError(), $this->contentLength()),
                );
            }
        });
    }

    private function contentLength(): ?int
    {
        $length = (int) $this->server('CONTENT_LENGTH');

        return $length > 0 ? $length : null;
    }
}
