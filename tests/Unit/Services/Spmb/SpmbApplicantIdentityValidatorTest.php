<?php

namespace Tests\Unit\Services\Spmb;

use App\Services\Spmb\SpmbApplicantIdentityValidator;
use PHPUnit\Framework\TestCase;

class SpmbApplicantIdentityValidatorTest extends TestCase
{
    private SpmbApplicantIdentityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SpmbApplicantIdentityValidator;
    }

    public function test_wni_requires_a_sixteen_digit_nik(): void
    {
        $this->assertArrayHasKey('nik', $this->validator->errors($this->validData(['nik' => '123']), 'WNI'));
        $this->assertArrayNotHasKey('nik', $this->validator->errors($this->validData(), 'WNI'));
    }

    public function test_wna_requires_passport_and_rejects_nik(): void
    {
        $errors = $this->validator->errors($this->validData([
            'nik' => '3201234567890001',
            'nomor_paspor' => '',
            'negara_penerbit_paspor' => '',
            'negara_kewarganegaraan' => '',
            'kode_negara_kewarganegaraan' => '',
        ]), 'WNA');

        $this->assertArrayHasKey('nik', $errors);
        $this->assertArrayHasKey('nomor_paspor', $errors);
        $this->assertArrayHasKey('negara_penerbit_paspor', $errors);
    }

    public function test_foreign_residence_does_not_require_indonesian_regions(): void
    {
        $errors = $this->validator->errors($this->validData([
            'nik' => null,
            'nomor_paspor' => 'AB123456',
            'negara_penerbit_paspor' => 'Malaysia',
            'negara_kewarganegaraan' => 'Malaysia',
            'kode_negara_kewarganegaraan' => 'MY',
            'negara_domisili' => 'Malaysia',
            'provinsi' => null,
            'kota_kabupaten' => null,
            'kecamatan' => null,
        ]), 'WNA');

        $this->assertSame([], $errors);
    }

    public function test_indonesian_residence_requires_administrative_regions(): void
    {
        $errors = $this->validator->errors($this->validData(['kecamatan' => null]), 'WNI');
        $this->assertArrayHasKey('kecamatan', $errors);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'nik' => '3201234567890001',
            'nomor_paspor' => null,
            'negara_penerbit_paspor' => null,
            'negara_domisili' => 'Indonesia',
            'alamat' => 'Jl. Merdeka No. 10',
            'provinsi' => 'Jawa Tengah',
            'kota_kabupaten' => 'Surakarta',
            'kecamatan' => 'Laweyan',
        ], $overrides);
    }
}
