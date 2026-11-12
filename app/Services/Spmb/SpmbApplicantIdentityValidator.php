<?php

namespace App\Services\Spmb;

use Illuminate\Validation\ValidationException;

class SpmbApplicantIdentityValidator
{
    /** @return array<string, string> */
    public function errors(array $data, string $nationalityCode): array
    {
        $errors = [];
        $code = strtoupper(trim($nationalityCode));
        if (! in_array($code, ['WNI', 'WNA'], true)) {
            if (str_contains($code, 'ASING') || str_contains($code, 'WNA')) {
                $code = 'WNA';
            } elseif (str_contains($code, 'INDONESIA') || str_contains($code, 'WNI')) {
                $code = 'WNI';
            }
        }

        if (! in_array($code, ['WNI', 'WNA'], true)) {
            $errors['kewarganegaraan_id'] = 'Kode kewarganegaraan tidak dikenali.';

            return $errors;
        }

        if ($code === 'WNI' && ! preg_match('/^[0-9]{16}$/', (string) ($data['nik'] ?? ''))) {
            $errors['nik'] = 'NIK WNI wajib 16 digit angka.';
        }

        if ($code === 'WNA') {
            if (! empty($data['nik'])) {
                $errors['nik'] = 'NIK tidak berlaku untuk WNA; kosongkan NIK.';
            }
            if (empty($data['nomor_paspor'])) {
                $errors['nomor_paspor'] = 'Nomor paspor wajib diisi untuk WNA.';
            }
            if (empty($data['negara_penerbit_paspor'])) {
                $errors['negara_penerbit_paspor'] = 'Negara penerbit paspor wajib diisi untuk WNA.';
            }
            if (empty($data['negara_kewarganegaraan'])) {
                $errors['negara_kewarganegaraan'] = 'Negara kewarganegaraan wajib diisi untuk WNA.';
            }
            if (! preg_match('/^[A-Z]{2}$/', strtoupper(trim((string) ($data['kode_negara_kewarganegaraan'] ?? ''))))) {
                $errors['kode_negara_kewarganegaraan'] = 'Kode negara kewarganegaraan wajib menggunakan 2 huruf ISO 3166-1 alpha-2.';
            }
        }

        if (mb_strlen(trim((string) ($data['alamat'] ?? ''))) < 5) {
            $errors['alamat'] = 'Alamat domisili wajib diisi minimal 5 karakter.';
        }

        $country = strtoupper(trim((string) ($data['negara_domisili'] ?? '')));
        if (in_array($country, ['', 'ID', 'IDN', 'INDONESIA'], true)) {
            foreach (['provinsi', 'kota_kabupaten', 'kecamatan'] as $field) {
                if (empty($data[$field])) {
                    $errors[$field] = 'Provinsi, kota/kabupaten, dan kecamatan wajib diisi untuk domisili Indonesia.';
                }
            }
        }

        return $errors;
    }

    public function validate(array $data, string $nationalityCode): void
    {
        $errors = $this->errors($data, $nationalityCode);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
