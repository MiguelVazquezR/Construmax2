<?php

namespace App\Http\Requests\Tools;

use Illuminate\Foundation\Http\FormRequest;

class DeployBuildRequest extends FormRequest
{
    // Laravel's file `max` rule is measured in kilobytes, not bytes.
    private const ZIP_MAX_SIZE_KB = 20 * 1024; // 20MB

    public function authorize(): bool
    {
        return $this->user()?->can('permissions.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'build_zip' => [
                'required',
                'file',
                'max:' . self::ZIP_MAX_SIZE_KB,
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'build_zip.required'  => 'Selecciona el archivo build.zip.',
            'build_zip.max'       => 'El archivo no debe superar los 20MB.',
            'build_zip.mimetypes' => 'El archivo debe ser un ZIP válido.',
        ];
    }
}
