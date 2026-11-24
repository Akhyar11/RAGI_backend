<?php

namespace Tests\Unit\Services\Siakad;

use App\Services\Siakad\LmsExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Unit test helper writer LmsExportService.
 *
 * Alur export lengkap (RBAC, 422 format=pdf, isi rekap) sudah dicakup feature
 * test `SiakadLmsExportTest`; di sini difokuskan pada kontrak writer agar
 * perubahan kecil pada BOM/CRLF, normalisasi sel, dan nama berkas segera
 * terdeteksi.
 */
class LmsExportServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `streamedContent()` hanya tersedia di TestResponse, bukan di object
     * StreamedResponse mentah yang dikembalikan service.
     */
    private function content(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    public function test_format_ekspor_hanya_csv_dan_xlsx(): void
    {
        $this->assertSame(['csv', 'xlsx'], LmsExportService::FORMATS);
    }

    public function test_normalisasi_sel_menangani_null_tanggal_dan_boolean(): void
    {
        $service = app(LmsExportService::class);
        $normalize = new ReflectionMethod($service, 'normalizeCell');
        $normalize->setAccessible(true);

        $this->assertSame('', $normalize->invoke($service, null));
        $this->assertSame('Ya', $normalize->invoke($service, true));
        $this->assertSame('Tidak', $normalize->invoke($service, false));
        $this->assertSame(42, $normalize->invoke($service, 42));
        $this->assertSame(
            '2026-11-24 07:30',
            $normalize->invoke($service, new \DateTimeImmutable('2026-11-24 07:30:45'))
        );
    }

    public function test_sanitasi_nama_file_membuang_karakter_berbahaya(): void
    {
        $service = app(LmsExportService::class);
        $sanitize = new ReflectionMethod($service, 'sanitizeFilename');
        $sanitize->setAccessible(true);

        $this->assertSame('rekap_nilai_kelas', $sanitize->invoke($service, 'rekap nilai|kelas'));
        $this->assertSame('a_b', $sanitize->invoke($service, '..a\\b..'));
        $this->assertSame('lms_export', $sanitize->invoke($service, '***'));
    }

    public function test_csv_response_memakai_bom_utf8_dan_crlf(): void
    {
        Storage::fake('local');

        $response = app(LmsExportService::class)->csvResponse(
            'Rekap Nilai',
            ['NIM', 'Nama', 'Nilai', 'Lulus'],
            [['2026002001', 'Budi', 87.5, true]]
        );

        $csv = $this->content($response);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("\r\n", $csv);
        $this->assertStringNotContainsString("\n", str_replace("\r\n", '', substr($csv, 3)));
        $this->assertStringContainsString("2026002001,Budi,87.5,Ya\r\n", $csv);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Rekap_Nilai.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_download_memilih_writer_xlsx_dan_fallback_ke_csv(): void
    {
        Storage::fake('local');
        $service = app(LmsExportService::class);

        $xlsx = $service->download('rekap', ['Kode'], [['IF-A']], LmsExportService::FORMAT_XLSX);
        $this->assertStringStartsWith("PK\x03\x04", $this->content($xlsx));
        $this->assertStringContainsString('.xlsx', $xlsx->headers->get('Content-Disposition'));

        // Format di luar whitelist ditolak ExportLmsRequest sebelum sampai ke
        // service. Sebagai pertahanan terakhir, service fallback ke CSV.
        $fallback = $service->download('rekap', ['Kode'], [['IF-A']], 'pdf');
        $this->assertSame('text/csv; charset=UTF-8', $fallback->headers->get('Content-Type'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $this->content($fallback));
    }
}
