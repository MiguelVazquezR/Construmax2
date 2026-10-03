<?php

namespace App\Http\Requests\Tutorials;

use Illuminate\Foundation\Http\FormRequest;

class StoreTutorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tutorials.create');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'string', 'max:10'],
            'video' => ['required', 'file', 'mimes:mp4,mov,avi,mkv,webm', 'max:524288'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Escribe el título del tutorial.',
            'video.required' => 'Selecciona el archivo de video.',
            'video.mimes' => 'El video debe ser un archivo MP4, MOV, AVI, MKV o WEBM.',
            'video.max' => 'El video no debe superar los 512 MB.',
            'thumbnail.image' => 'La miniatura debe ser una imagen.',
            'thumbnail.mimes' => 'La miniatura debe ser un archivo JPG, PNG o WEBP.',
            'thumbnail.max' => 'La miniatura no debe superar los 4 MB.',
        ];
    }
}
