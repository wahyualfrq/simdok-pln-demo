<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UploadFileRequest
 *
 * Validasi untuk upload file ke Google Drive.
 * Membatasi tipe dan ukuran file sesuai kebutuhan PLN.
 * Sesuai dengan: 08_CODING_STANDARD.md & 04_GOOGLE_DRIVE_API.md
 */
class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_id' => ['required', 'string', 'min:1'],
            'file'      => [
                'required',
                'file',
                'max:51200', // 50MB
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,zip,rar,txt,csv',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'folder_id.required' => 'Folder tujuan tidak valid.',
            'file.required'      => 'File tidak boleh kosong.',
            'file.max'           => 'Ukuran file maksimal 50 MB.',
            'file.mimes'         => 'Tipe file tidak didukung. Gunakan: PDF, Word, Excel, PowerPoint, gambar, atau ZIP.',
        ];
    }
}
