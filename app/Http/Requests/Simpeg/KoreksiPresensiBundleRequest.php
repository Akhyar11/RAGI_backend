<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class KoreksiPresensiBundleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->hasPermission('simpeg.presensi.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'presensi_periode_id' => 'required|exists:simpeg_presensi_periode,id',
            'pegawai_id'          => 'required|exists:simpeg_pegawai,id',
            'tanggal'             => 'required|date_format:Y-m-d',
            'status_kehadiran'    => 'required|in:hadir,izin,sakit,dinas,alfa',
            'jam_masuk'           => 'nullable|string',
            'jam_keluar'          => 'nullable|string',
            'alasan_koreksi'      => 'required|string|min:5|max:500',
        ];
    }

    /**
     * Custom message validation
     */
    public function messages(): array
    {
        return [
            'presensi_periode_id.required' => 'ID Periode bundle wajib disertakan.',
            'presensi_periode_id.exists'   => 'Periode bundle presensi tidak ditemukan.',
            'pegawai_id.required'          => 'Pegawai wajib dipilih.',
            'pegawai_id.exists'            => 'Data pegawai tidak ditemukan di sistem.',
            'tanggal.required'             => 'Tanggal presensi wajib diisi.',
            'tanggal.date_format'          => 'Format tanggal harus YYYY-MM-DD.',
            'status_kehadiran.required'    => 'Status kehadiran wajib dipilih.',
            'status_kehadiran.in'          => 'Status kehadiran harus salah satu dari: hadir, izin, sakit, dinas, alfa.',
            'alasan_koreksi.required'      => 'Alasan koreksi presensi wajib diisi oleh admin sebagai catatan audit.',
            'alasan_koreksi.min'           => 'Alasan koreksi minimal 5 karakter.',
        ];
    }
}
