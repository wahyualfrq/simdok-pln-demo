<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CreateFolderRequest
 *
 * Validasi untuk pembuatan folder baru di Google Drive.
 * Sesuai dengan: 08_CODING_STANDARD.md (Gunakan Form Request)
 */
class CreateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_id'   => ['required', 'string', 'min:1'],
            'folder_name' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'folder_id.required'   => 'Folder tujuan tidak valid.',
            'folder_name.required' => 'Nama folder tidak boleh kosong.',
            'folder_name.max'      => 'Nama folder terlalu panjang (maksimal 255 karakter).',
        ];
    }
}
