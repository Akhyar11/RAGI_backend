<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\SettingTarif;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\MahasiswaTipeTagihan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\PeriodeAkuntansi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PembayaranKasirController extends Controller
{
    /**
     * POST /api/v1/sikeu/pembayaran/kasir
     * Process an offline payment at the campus cashier counter.
     */
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tagihan_id' => 'required|integer|exists:sikeu_tagihan_mahasiswa,id',
            'jumlah_bayar' => 'required|numeric|min:1',
            'channel_bayar' => 'required|in:LOKET_TUNAI,LOKET_TRANSFER',
            'catatan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pembayaran kasir gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tagihan = TagihanMahasiswa::with('details')->findOrFail($request->tagihan_id);

            // Hitung sisa tagihan
            $totalBersih = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
            $sisa = max(0, $totalBersih - (float)$tagihan->total_bayar);

            if ($request->jumlah_bayar > $sisa) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Jumlah bayar (Rp " . number_format($request->jumlah_bayar, 0, ',', '.') . ") melebihi sisa tagihan (Rp " . number_format($sisa, 0, ',', '.') . ").",
                ], 422);
            }

            // Validasi Tutup Buku
            $today = now()->toDateString();
            $periodeTutup = PeriodeAkuntansi::where('status', 'ditutup')
                ->where('tanggal_mulai', '<=', $today)
                ->where('tanggal_selesai', '>=', $today)
                ->first();

            if ($periodeTutup) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Periode akuntansi \"{$periodeTutup->nama_periode}\" sudah ditutup untuk tanggal ini. Tidak dapat memproses transaksi.",
                ], 403);
            }

            // Generate kode transaksi unik
            $kodeTransaksi = 'TRX-LOKET-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            // Create Pembayaran record
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan->id,
                'jumlah_bayar' => $request->jumlah_bayar,
                'waktu_bayar' => now(),
                'channel_bayar' => $request->channel_bayar,
                'status' => 'success',
                'kode_transaksi' => $kodeTransaksi,
                'diverifikasi_oleh' => auth()->id(),
            ]);

            // Update total_bayar & status pada TagihanMahasiswa
            $newTotalBayar = (float)$tagihan->total_bayar + (float)$request->jumlah_bayar;
            $newStatus = $newTotalBayar >= $totalBersih ? 'lunas' : 'sebagian';

            $tagihan->update([
                'total_bayar' => $newTotalBayar,
                'status' => $newStatus,
            ]);

            // Auto Jurnal Akuntansi
            $this->createAutoJurnal($pembayaran, $request->channel_bayar, $tagihan);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran kasir berhasil diproses.',
                'data' => [
                    'pembayaran' => $pembayaran,
                    'kuitansi' => [
                        'kode_transaksi' => $kodeTransaksi,
                        'tanggal' => now()->format('Y-m-d H:i:s'),
                        'mahasiswa_id' => $tagihan->mahasiswa_id,
                        'nomor_tagihan' => $tagihan->nomor_tagihan,
                        'jumlah_bayar' => (float)$request->jumlah_bayar,
                        'channel' => $request->channel_bayar,
                        'sisa_setelah_bayar' => max(0, $sisa - (float)$request->jumlah_bayar),
                        'status_tagihan' => $newStatus,
                        'kasir' => auth()->user()->username ?? 'SYSTEM',
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Pembayaran Kasir Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses pembayaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/pembayaran/{id}/koreksi
     * Reverse/correct a previously recorded payment.
     */
    public function koreksiPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'alasan_koreksi' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi koreksi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $pembayaran = Pembayaran::with('tagihan')->findOrFail($id);

            if ($pembayaran->status === 'reversed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaksi ini sudah pernah dikoreksi/dibatalkan sebelumnya.',
                ], 422);
            }

            // Validasi Tutup Buku
            $today = now()->toDateString();
            $periodeTutup = PeriodeAkuntansi::where('status', 'ditutup')
                ->where('tanggal_mulai', '<=', $today)
                ->where('tanggal_selesai', '>=', $today)
                ->first();

            if ($periodeTutup) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Periode akuntansi sudah ditutup. Tidak dapat melakukan koreksi.",
                ], 403);
            }

            // Reverse pembayaran
            $pembayaran->update(['status' => 'reversed']);

            // Update tagihan
            $tagihan = $pembayaran->tagihan;
            $newTotalBayar = max(0, (float)$tagihan->total_bayar - (float)$pembayaran->jumlah_bayar);
            $totalBersih = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);

            $newStatus = 'belum_bayar';
            if ($newTotalBayar > 0 && $newTotalBayar < $totalBersih) {
                $newStatus = 'sebagian';
            } elseif ($newTotalBayar >= $totalBersih) {
                $newStatus = 'lunas';
            }

            $tagihan->update([
                'total_bayar' => $newTotalBayar,
                'status' => $newStatus,
            ]);

            // Create reversal jurnal
            $this->createReversalJurnal($pembayaran, $request->alasan_koreksi);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Koreksi pembayaran berhasil. Transaksi ' . $pembayaran->kode_transaksi . ' telah dibatalkan.',
                'data' => [
                    'pembayaran' => $pembayaran->fresh(),
                    'tagihan_status_baru' => $newStatus,
                    'total_bayar_baru' => $newTotalBayar,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Koreksi Pembayaran Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal melakukan koreksi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/sikeu/tagihan/generate-mass
     * Generate semester bills in bulk for all matching students.
     */
    public function generateMassTagihan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tahun_angkatan' => 'required|integer|min:2020|max:2040',
            'jalur_kelas' => 'required|string',
            'semester' => 'nullable|integer|min:1|max:14',
            'program_studi_id' => 'nullable|integer',
            'jatuh_tempo' => 'required|date|after:today',
            'semester_label' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi generate tagihan masal gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 1. Load setting tarif yang aktif & cocok
            $tarifQuery = SettingTarif::with('masterBiaya')
                ->where('tahun_angkatan', $request->tahun_angkatan)
                ->where('jalur_kelas', $request->jalur_kelas)
                ->where('is_active', true);

            if ($request->filled('semester')) {
                $tarifQuery->where(function ($q) use ($request) {
                    $q->where('semester', $request->semester)
                      ->orWhereNull('semester');
                });
            }

            if ($request->filled('program_studi_id')) {
                $tarifQuery->where(function ($q) use ($request) {
                    $q->where('program_studi_id', $request->program_studi_id)
                      ->orWhereNull('program_studi_id');
                });
            }

            $settings = $tarifQuery->get();

            if ($settings->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ditemukan setting tarif yang cocok untuk kombinasi Angkatan ' . $request->tahun_angkatan . ' / ' . $request->jalur_kelas . '. Silakan atur setting tarif terlebih dahulu di menu Master.',
                ], 404);
            }

            // 2. Load mahasiswa yang cocok
            $mahasiswaQuery = MahasiswaTipeTagihan::where('tahun_angkatan', $request->tahun_angkatan)
                ->where('jalur_kelas', $request->jalur_kelas);

            $mahasiswaList = $mahasiswaQuery->get();

            if ($mahasiswaList->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ditemukan mahasiswa dengan Angkatan ' . $request->tahun_angkatan . ' dan Jalur ' . $request->jalur_kelas . '. Pastikan tipe tagihan mahasiswa sudah ditetapkan.',
                ], 404);
            }

            $semesterLabel = $request->semester_label ?? ('Semester ' . ($request->semester ?? 'Aktif') . ' ' . $request->tahun_angkatan);
            $generatedCount = 0;
            $skippedCount = 0;

            // 3. Generate tagihan per mahasiswa
            foreach ($mahasiswaList as $mhs) {
                // Cek apakah sudah ada tagihan yang sama (prevent duplikat)
                $nomorTagihan = 'INV-SIAKAD-' . date('Ymd') . '-' . str_pad($mhs->mahasiswa_id, 5, '0', STR_PAD_LEFT);

                $existingTagihan = TagihanMahasiswa::where('nomor_tagihan', $nomorTagihan)->exists();
                if ($existingTagihan) {
                    $skippedCount++;
                    continue;
                }

                // Hitung total dari setting tarif
                $totalNominal = $settings->sum('nominal');

                $tagihan = TagihanMahasiswa::create([
                    'mahasiswa_id' => $mhs->mahasiswa_id,
                    'tahun_akademik_id' => 1,
                    'nomor_tagihan' => $nomorTagihan,
                    'total_tagihan' => $totalNominal,
                    'total_potongan' => 0,
                    'total_denda' => 0,
                    'total_bayar' => 0,
                    'status' => 'belum_bayar',
                    'source_system' => 'SIAKAD',
                    'jatuh_tempo' => $request->jatuh_tempo,
                    'catatan_approval' => 'Tagihan masal ' . $semesterLabel,
                ]);

                // Insert detail per komponen biaya
                foreach ($settings as $st) {
                    DetailTagihan::create([
                        'tagihan_id' => $tagihan->id,
                        'master_biaya_id' => $st->master_biaya_id,
                        'nominal' => $st->nominal,
                        'potongan' => 0,
                        'nominal_bersih' => $st->nominal,
                        'keterangan' => ($st->masterBiaya->nama ?? 'Biaya Pendidikan') . ' - ' . $semesterLabel,
                    ]);
                }

                $generatedCount++;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Berhasil menerbitkan {$generatedCount} tagihan masal untuk {$semesterLabel}." . ($skippedCount > 0 ? " ({$skippedCount} mahasiswa dilewati karena tagihan sudah ada)" : ''),
                'data' => [
                    'total_mahasiswa_eligible' => $mahasiswaList->count(),
                    'total_tagihan_generated' => $generatedCount,
                    'total_skipped' => $skippedCount,
                    'total_nominal_per_mahasiswa' => $settings->sum('nominal'),
                    'semester_label' => $semesterLabel,
                    'jatuh_tempo' => $request->jatuh_tempo,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Generate Mass Tagihan Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menerbitkan tagihan masal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper: Create auto journal entry for a payment.
     */
    private function createAutoJurnal(Pembayaran $pembayaran, string $channel, TagihanMahasiswa $tagihan)
    {
        $akunKas = AkunKeuangan::where('kelompok', 'aset')->first();
        $akunPendapatan = AkunKeuangan::where('kelompok', 'pendapatan')->first();

        if (!$akunKas || !$akunPendapatan) {
            return; // Skip if COA not configured
        }

        $nomorJurnal = 'JRN-PAY-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        $channelLabel = $channel === 'LOKET_TUNAI' ? 'Tunai Loket Kasir' : 'Transfer Bank (Non-Tunai)';

        $jurnal = JurnalUmum::create([
            'nomor_jurnal' => $nomorJurnal,
            'tanggal_jurnal' => now()->toDateString(),
            'jenis_sumber' => 'pembayaran_mahasiswa',
            'referensi_id' => $pembayaran->id,
            'keterangan' => "Pembayaran {$channelLabel} - {$tagihan->nomor_tagihan}",
            'status_posting' => 'posted',
            'total_debet' => $pembayaran->jumlah_bayar,
            'total_kredit' => $pembayaran->jumlah_bayar,
            'created_by' => auth()->id(),
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        // Debet: Kas/Bank
        DetailJurnalUmum::create([
            'jurnal_id' => $jurnal->id,
            'akun_id' => $akunKas->id,
            'debet' => $pembayaran->jumlah_bayar,
            'kredit' => 0,
            'keterangan' => "Penerimaan kas pembayaran mahasiswa",
        ]);

        // Kredit: Pendapatan UKT/SPP
        DetailJurnalUmum::create([
            'jurnal_id' => $jurnal->id,
            'akun_id' => $akunPendapatan->id,
            'debet' => 0,
            'kredit' => $pembayaran->jumlah_bayar,
            'keterangan' => "Pengakuan pendapatan UKT/SPP mahasiswa",
        ]);
    }

    /**
     * Helper: Create reversal journal entry.
     */
    private function createReversalJurnal(Pembayaran $pembayaran, string $alasan)
    {
        $akunKas = AkunKeuangan::where('kelompok', 'aset')->first();
        $akunPendapatan = AkunKeuangan::where('kelompok', 'pendapatan')->first();

        if (!$akunKas || !$akunPendapatan) {
            return;
        }

        $nomorJurnal = 'JRN-REV-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $jurnal = JurnalUmum::create([
            'nomor_jurnal' => $nomorJurnal,
            'tanggal_jurnal' => now()->toDateString(),
            'jenis_sumber' => 'penyesuaian',
            'referensi_id' => $pembayaran->id,
            'keterangan' => "KOREKSI PEMBATALAN: {$pembayaran->kode_transaksi} - {$alasan}",
            'status_posting' => 'posted',
            'total_debet' => $pembayaran->jumlah_bayar,
            'total_kredit' => $pembayaran->jumlah_bayar,
            'created_by' => auth()->id(),
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        // Reverse: Debet Pendapatan (cancel revenue)
        DetailJurnalUmum::create([
            'jurnal_id' => $jurnal->id,
            'akun_id' => $akunPendapatan->id,
            'debet' => $pembayaran->jumlah_bayar,
            'kredit' => 0,
            'keterangan' => "Pembalik pendapatan - koreksi " . $pembayaran->kode_transaksi,
        ]);

        // Reverse: Kredit Kas (return cash)
        DetailJurnalUmum::create([
            'jurnal_id' => $jurnal->id,
            'akun_id' => $akunKas->id,
            'debet' => 0,
            'kredit' => $pembayaran->jumlah_bayar,
            'keterangan' => "Pengembalian kas - koreksi " . $pembayaran->kode_transaksi,
        ]);
    }
}
