<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\Pembayaran;
use App\Models\Sikeu\SettingTarif;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\MahasiswaTipeTagihan;
use App\Models\Sikeu\JurnalUmum;
use App\Models\Sikeu\DetailJurnalUmum;
use App\Models\Sikeu\AkunKeuangan;
use App\Models\Sikeu\PeriodeAkuntansi;
use App\Models\Sikeu\PotonganTagihan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PembayaranKasirController extends Controller
{
    /**
     * POST /api/v1/sikeu/pembayaran/kasir
     * Process an offline payment at the campus cashier counter (supports single and multi-bill batch).
     */
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tagihan_id' => 'nullable|integer|exists:sikeu_tagihan_mahasiswa,id',
            'tagihan_ids' => 'nullable|array',
            'tagihan_ids.*' => 'integer|exists:sikeu_tagihan_mahasiswa,id',
            'jumlah_bayar' => 'required|numeric|min:0',
            'channel_bayar' => 'required|in:LOKET_TUNAI,LOKET_TRANSFER',
            'potongan' => 'nullable|numeric|min:0',
            'alasan_potongan' => 'nullable|string|max:255',
            'catatan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pembayaran kasir gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Tentukan daftar target tagihan
        $targetTagihanIds = [];
        if ($request->has('tagihan_ids') && is_array($request->tagihan_ids) && count($request->tagihan_ids) > 0) {
            $targetTagihanIds = $request->tagihan_ids;
        } elseif ($request->filled('tagihan_id')) {
            $targetTagihanIds = [(int)$request->tagihan_id];
        }

        if (empty($targetTagihanIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pilih minimal 1 tagihan untuk diproses.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tagihans = TagihanMahasiswa::with('details')
                ->whereIn('id', $targetTagihanIds)
                ->orderBy('id', 'asc')
                ->get();

            if ($tagihans->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tagihan tidak ditemukan.',
                ], 404);
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

            // Hitung total sisa seluruh tagihan terpilih
            $totalSisaAll = 0;
            $billBalances = [];
            foreach ($tagihans as $t) {
                $totalBersih = (float)($t->total_tagihan + $t->total_denda - $t->total_potongan);
                $sisa = max(0, $totalBersih - (float)$t->total_bayar);
                $billBalances[$t->id] = [
                    'tagihan' => $t,
                    'sisa' => $sisa,
                    'bersih' => $totalBersih,
                ];
                $totalSisaAll += $sisa;
            }

            $potonganTambahanTotal = (float)($request->potongan ?? 0);
            $totalSisaSetelahDiskon = max(0, $totalSisaAll - $potonganTambahanTotal);

            if ($request->jumlah_bayar > ($totalSisaAll + 100)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Jumlah bayar (Rp " . number_format($request->jumlah_bayar, 0, ',', '.') . ") melebihi total sisa seluruh tagihan terpilih (Rp " . number_format($totalSisaAll, 0, ',', '.') . ").",
                ], 422);
            }

            // Generate kode transaksi unik kasir loket (shared batch code for kuitansi)
            $batchKodeTransaksi = 'TRX-LOKET-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $remainingBayar = (float)$request->jumlah_bayar;
            $remainingPotongan = $potonganTambahanTotal;
            $processedPembayarans = [];
            $paidBillNumbers = [];
            $totalSisaAkhir = 0;

            $allocationIndex = 0;

            foreach ($tagihans as $tagihan) {
                $currentSisa = $billBalances[$tagihan->id]['sisa'];

                // 1. Alokasikan potongan diskon tambahan jika ada
                if ($remainingPotongan > 0 && $currentSisa > 0) {
                    $potonganItem = min($remainingPotongan, $currentSisa);
                    PotonganTagihan::create([
                        'tagihan_id' => $tagihan->id,
                        'tipe' => 'diskon',
                        'nominal_potongan' => $potonganItem,
                        'keterangan' => $request->alasan_potongan ?? 'Potongan Kasir Loket',
                        'diinput_oleh' => auth()->id(),
                    ]);
                    $tagihan->total_potongan = (float)$tagihan->total_potongan + $potonganItem;
                    $tagihan->save();
                    $remainingPotongan -= $potonganItem;
                    $currentSisa = max(0, $currentSisa - $potonganItem);
                }

                // 2. Alokasikan pembayaran kasir
                $alokasiBayar = min($remainingBayar, $currentSisa);
                if ($alokasiBayar > 0 || (count($tagihans) === 1 && $remainingBayar >= 0)) {
                    $allocationIndex++;
                    $kodeTransaksi = $batchKodeTransaksi . '-' . $allocationIndex;
                    $pembayaran = Pembayaran::create([
                        'tagihan_id' => $tagihan->id,
                        'jumlah_bayar' => $alokasiBayar,
                        'waktu_bayar' => now(),
                        'channel_bayar' => $request->channel_bayar,
                        'status' => 'success',
                        'kode_transaksi' => $kodeTransaksi,
                        'diverifikasi_oleh' => auth()->id(),
                    ]);

                    $newTotalBayar = (float)$tagihan->total_bayar + $alokasiBayar;
                    $totalBersihBaru = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
                    $newStatus = $newTotalBayar >= $totalBersihBaru ? 'lunas' : ($newTotalBayar > 0 ? 'sebagian' : 'belum_bayar');

                    $tagihan->update([
                        'total_bayar' => $newTotalBayar,
                        'status' => $newStatus,
                    ]);

                    // Auto Jurnal Akuntansi
                    if ($alokasiBayar > 0) {
                        $this->createAutoJurnal($pembayaran, $request->channel_bayar, $tagihan);
                    }

                    $remainingBayar -= $alokasiBayar;
                    $processedPembayarans[] = $pembayaran;
                    $paidBillNumbers[] = $tagihan->nomor_tagihan;
                }

                $totalBersihFinal = (float)($tagihan->total_tagihan + $tagihan->total_denda - $tagihan->total_potongan);
                $totalSisaAkhir += max(0, $totalBersihFinal - (float)$tagihan->total_bayar);
            }

            DB::commit();

            $primaryTagihan = $tagihans->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran kasir loket berhasil diproses untuk ' . count($tagihans) . ' tagihan.',
                'data' => [
                    'pembayaran' => $processedPembayarans[0] ?? null,
                    'pembayarans' => $processedPembayarans,
                    'kuitansi' => [
                        'kode_transaksi' => $batchKodeTransaksi,
                        'tanggal' => now()->format('Y-m-d H:i:s'),
                        'mahasiswa_id' => $primaryTagihan->mahasiswa_id,
                        'nomor_tagihan' => implode(', ', $paidBillNumbers),
                        'nomor_tagihan_list' => $paidBillNumbers,
                        'jumlah_bayar' => (float)$request->jumlah_bayar,
                        'potongan_tambahan' => $potonganTambahanTotal,
                        'channel' => $request->channel_bayar,
                        'sisa_setelah_bayar' => $totalSisaAkhir,
                        'status_tagihan' => $totalSisaAkhir <= 0 ? 'lunas' : 'sebagian',
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
     * Generate semester bills in bulk for all matching students with auto-beasiswa and Virtual Account.
     */
    public function generateMassTagihan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tahun_angkatan' => 'required|integer|min:2020|max:2040',
            'jalur_kelas' => 'required|string',
            'semester' => 'nullable|integer|min:1|max:14',
            'program_studi_id' => 'nullable|integer',
            'jatuh_tempo' => 'required|date|after_or_equal:today',
            'semester_label' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi generate tagihan masal gagal: ' . implode(', ', $validator->errors()->all()),
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

            if ($request->filled('master_biaya_ids') && is_array($request->master_biaya_ids) && count($request->master_biaya_ids) > 0) {
                $tarifQuery->whereIn('master_biaya_id', $request->master_biaya_ids);
            }

            $settings = $tarifQuery->get();

            if ($settings->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ditemukan setting tarif yang cocok untuk kombinasi Angkatan ' . $request->tahun_angkatan . ' / ' . $request->jalur_kelas . ($request->filled('semester') ? (' / Semester ' . $request->semester) : '') . '. Silakan atur setting tarif terlebih dahulu di menu Master.',
                ], 404);
            }

            // 2. Load mahasiswa yang cocok (dari MahasiswaTipeTagihan atau SIAKAD Mahasiswa)
            $mahasiswaList = collect();

            $tipeQuery = MahasiswaTipeTagihan::where('tahun_angkatan', $request->tahun_angkatan)
                ->where('jalur_kelas', $request->jalur_kelas);

            if ($request->filled('program_studi_id')) {
                $tipeQuery->whereHas('mahasiswa', function ($mq) use ($request) {
                    $mq->where('program_studi_id', $request->program_studi_id);
                });
            }

            $tipeList = $tipeQuery->get();

            if ($tipeList->isNotEmpty()) {
                $mahasiswaList = $tipeList->map(function ($item) {
                    return (object)[
                        'mahasiswa_id' => $item->mahasiswa_id,
                        'nim' => $item->nim,
                        'nama_mahasiswa' => $item->nama_mahasiswa,
                    ];
                });
            } else {
                // Fallback: Cari langsung ke tabel siakad_mahasiswa untuk angkatan tersebut
                $siakadQuery = \App\Models\Siakad\Mahasiswa::where('angkatan', $request->tahun_angkatan)
                    ->where('status', 'aktif');

                if ($request->filled('program_studi_id')) {
                    $siakadQuery->where('program_studi_id', $request->program_studi_id);
                }

                $siakadList = $siakadQuery->get();
                if ($siakadList->isNotEmpty()) {
                    $mahasiswaList = $siakadList->map(function ($item) {
                        return (object)[
                            'mahasiswa_id' => $item->id,
                            'nim' => $item->nim,
                            'nama_mahasiswa' => $item->nama_lengkap,
                        ];
                    });
                }
            }

            if ($mahasiswaList->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ditemukan mahasiswa aktif untuk Angkatan ' . $request->tahun_angkatan . ' dan Jalur ' . $request->jalur_kelas . '. Pastikan data mahasiswa / penetapan tipe tagihan sudah tersedia.',
                ], 404);
            }

            $semesterNum = $request->semester ?? 1;
            $rawLabel = $request->semester_label;
            if ($rawLabel && !str_contains(strtolower($rawLabel), 'semester ' . $semesterNum)) {
                $semesterLabel = 'Semester ' . $semesterNum . ' (' . $rawLabel . ')';
            } else {
                $semesterLabel = $rawLabel ?? ('Semester ' . $semesterNum . ' Angkatan ' . $request->tahun_angkatan);
            }
            $generatedCount = 0;
            $skippedCount = 0;

            // 3. Generate tagihan per mahasiswa dengan auto-potongan beasiswa dan VA
            foreach ($mahasiswaList as $mhs) {
                // Cek apakah sudah ada tagihan yang sama untuk semester ini (mencegah double billing baik status lunas maupun belum lunas)
                $nomorTagihan = 'INV-SIAKAD-' . $request->tahun_angkatan . '-SMT' . $semesterNum . '-' . str_pad($mhs->mahasiswa_id, 5, '0', STR_PAD_LEFT);

                $alreadyBilled = TagihanMahasiswa::where('mahasiswa_id', $mhs->mahasiswa_id)
                    ->where(function ($q) use ($nomorTagihan, $semesterLabel, $request, $semesterNum) {
                        $q->where('nomor_tagihan', $nomorTagihan)
                          ->orWhere('catatan_approval', 'like', "%Semester {$semesterNum}%")
                          ->orWhere('nomor_tagihan', 'like', "%-{$request->tahun_angkatan}-SMT{$semesterNum}-%");
                    })
                    ->exists();

                if ($alreadyBilled) {
                    $skippedCount++;
                    continue;
                }

                // Cek beasiswa aktif mahasiswa
                $beasiswaMhs = \App\Models\Sikeu\MahasiswaBeasiswa::with('beasiswa')
                    ->where('mahasiswa_id', $mhs->mahasiswa_id)
                    ->where(function ($q) {
                        $q->where('status', 'aktif')
                          ->orWhereNull('status');
                    })
                    ->first();

                // Hitung total dari setting tarif
                $totalNominal = (float)$settings->sum('nominal');
                $totalPotonganBeasiswa = 0;

                if ($beasiswaMhs && $beasiswaMhs->beasiswa) {
                    $b = $beasiswaMhs->beasiswa;
                    if ($b->tipe_potongan === 'persen' || $b->tipe_potongan === 'persentase') {
                        $totalPotonganBeasiswa = ($totalNominal * (float)$b->nilai_potongan) / 100;
                    } elseif ($b->tipe_potongan === 'nominal') {
                        $totalPotonganBeasiswa = min($totalNominal, (float)$b->nilai_potongan);
                    } else {
                        $totalPotonganBeasiswa = $totalNominal; // full scholarship
                    }
                }

                $tagihan = TagihanMahasiswa::create([
                    'mahasiswa_id' => $mhs->mahasiswa_id,
                    'tahun_akademik_id' => 1,
                    'nomor_tagihan' => $nomorTagihan,
                    'total_tagihan' => $totalNominal,
                    'total_potongan' => $totalPotonganBeasiswa,
                    'total_denda' => 0,
                    'total_bayar' => 0,
                    'status' => $totalPotonganBeasiswa >= $totalNominal ? 'lunas' : 'belum_bayar',
                    'source_system' => 'SIAKAD',
                    'jatuh_tempo' => $request->jatuh_tempo,
                    'catatan_approval' => 'Tagihan masal ' . $semesterLabel . ($beasiswaMhs ? " (Beasiswa: {$beasiswaMhs->beasiswa->nama})" : ''),
                ]);

                // Insert detail per komponen biaya
                foreach ($settings as $st) {
                    $detailPotongan = 0;
                    if ($totalNominal > 0 && $totalPotonganBeasiswa > 0) {
                        $detailPotongan = round(($st->nominal / $totalNominal) * $totalPotonganBeasiswa, 2);
                    }

                    DetailTagihan::create([
                        'tagihan_id' => $tagihan->id,
                        'master_biaya_id' => $st->master_biaya_id,
                        'nominal' => $st->nominal,
                        'potongan' => $detailPotongan,
                        'nominal_bersih' => max(0, (float)$st->nominal - $detailPotongan),
                        'keterangan' => ($st->masterBiaya->nama ?? 'Biaya Pendidikan') . ' - ' . $semesterLabel,
                    ]);
                }

                // Catat potongan beasiswa di PotonganTagihan jika ada
                if ($totalPotonganBeasiswa > 0 && $beasiswaMhs) {
                    PotonganTagihan::create([
                        'tagihan_id' => $tagihan->id,
                        'tipe' => 'subsidi',
                        'nominal_potongan' => $totalPotonganBeasiswa,
                        'keterangan' => 'Pemotongan otomatis beasiswa: ' . ($beasiswaMhs->beasiswa->nama ?? 'Beasiswa'),
                        'diinput_oleh' => auth()->id(),
                    ]);
                }

                // Auto-generate BNI Virtual Account untuk tagihan ini (status 'aktif' sesuai enum database)
                \App\Models\Sikeu\VirtualAccount::updateOrCreate(
                    ['tagihan_id' => $tagihan->id],
                    [
                        'va_number' => '88012' . str_pad($mhs->nim ? preg_replace('/[^0-9]/', '', $mhs->nim) : $mhs->mahasiswa_id, 10, '0', STR_PAD_LEFT),
                        'bank_kode' => 'BNI',
                        'bank_nama' => 'Bank BNI',
                        'nominal' => max(0, $totalNominal - $totalPotonganBeasiswa),
                        'expired_at' => $request->jatuh_tempo . ' 23:59:59',
                        'status' => 'aktif',
                    ]
                );

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

    /**
     * GET /api/v1/sikeu/mahasiswa/{id}/unpaid-bills
     * Get all active/unpaid bills for a specific student.
     */
    public function getStudentUnpaidBills($id)
    {
        $mhs = \App\Models\Siakad\Mahasiswa::with('programStudi')->find($id);
        $tipeMhs = MahasiswaTipeTagihan::where('mahasiswa_id', $id)->first();

        $bills = TagihanMahasiswa::with(['details.masterBiaya', 'potonganTagihan', 'dendaTagihan', 'virtualAccount'])
            ->where('mahasiswa_id', $id)
            ->whereIn('status', ['belum_bayar', 'sebagian', 'dispensasi'])
            ->orderBy('id', 'asc')
            ->get();

        $mappedBills = $bills->map(function ($b) {
            $totalBersih = (float)($b->total_tagihan + $b->total_denda - $b->total_potongan);
            $sisa = max(0, $totalBersih - (float)$b->total_bayar);
            
            $namaKomponen = $b->details->map(function ($d) {
                return !empty($d->keterangan) ? $d->keterangan : ($d->masterBiaya->nama ?? 'Komponen Biaya');
            })->filter()->implode(', ');

            if (empty($namaKomponen)) {
                $namaKomponen = !empty($b->catatan_approval) ? str_replace('Tagihan masal ', '', $b->catatan_approval) : 'Tagihan Semester';
            }

            $jt = null;
            if ($b->jatuh_tempo) {
                $jt = (is_object($b->jatuh_tempo) && method_exists($b->jatuh_tempo, 'format'))
                    ? $b->jatuh_tempo->format('Y-m-d')
                    : (string)$b->jatuh_tempo;
            }

            return [
                'id' => $b->id,
                'nomor_tagihan' => $b->nomor_tagihan,
                'jenis' => $namaKomponen,
                'periode_label' => !empty($b->catatan_approval) ? str_replace('Tagihan masal ', '', $b->catatan_approval) : 'Semester Ganjil 2026/2027',
                'total_tagihan' => (float)$b->total_tagihan,
                'total_potongan' => (float)$b->total_potongan,
                'total_denda' => (float)$b->total_denda,
                'total_bayar' => (float)$b->total_bayar,
                'sisa' => $sisa,
                'status' => $b->status,
                'jatuh_tempo' => $jt,
                'details' => $b->details->map(fn($d) => [
                    'id' => $d->id,
                    'master_biaya' => $d->masterBiaya->nama ?? 'Komponen Biaya',
                    'nominal' => (float)$d->nominal,
                    'potongan' => (float)$d->potongan,
                    'nominal_bersih' => (float)$d->nominal_bersih,
                    'keterangan' => $d->keterangan ?? ($d->masterBiaya->nama ?? 'Biaya Kuliah'),
                ]),
            ];
        });

        $nim = $mhs?->nim ?? $tipeMhs?->nim ?? ('2024' . str_pad($id, 4, '0', STR_PAD_LEFT));
        $nama = $mhs?->nama_lengkap ?? $tipeMhs?->nama_mahasiswa ?? ('Mahasiswa #' . $id);
        $prodi = $mhs?->programStudi?->nama ?? $mhs?->programStudi?->nama_prodi ?? 'Teknik Informatika';
        $angkatan = (int)($mhs?->angkatan ?? $tipeMhs?->tahun_angkatan ?? 2025);

        return response()->json([
            'status' => 'success',
            'data' => [
                'mahasiswa' => [
                    'id' => (int)$id,
                    'nim' => $nim,
                    'nama_mahasiswa' => $nama,
                    'program_studi' => $prodi,
                    'tahun_angkatan' => $angkatan,
                    'jalur_kelas' => $tipeMhs?->jalur_kelas ?? 'Reguler',
                    'kelompok_ukt' => (int)($tipeMhs?->kelompok_ukt ?? 3),
                ],
                'bills' => $mappedBills,
                'total_unpaid' => $mappedBills->sum('sisa'),
                'count_unpaid' => $mappedBills->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/sikeu/pembayaran/direct-cashier
     * Direct cashier billing & immediate payment on-the-spot.
     */
    public function directCashierPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mahasiswa_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.master_biaya_id' => 'nullable|integer',
            'items.*.master_biaya_kode' => 'nullable|string',
            'items.*.nominal' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
            'jumlah_bayar' => 'required|numeric|min:1',
            'potongan' => 'nullable|numeric|min:0',
            'alasan_potongan' => 'nullable|string',
            'channel_bayar' => 'required|in:LOKET_TUNAI,LOKET_TRANSFER',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pembayaran langsung kasir gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $mhsId = $request->mahasiswa_id;
            $siakad = null;
            try {
                $siakad = \App\Models\Siakad\Mahasiswa::with('programStudi')->find($mhsId);
            } catch (\Throwable $e) {}

            $tipe = MahasiswaTipeTagihan::where('mahasiswa_id', $mhsId)->first();
            $nim = $siakad?->nim ?? $tipe?->nim ?? ('2024' . str_pad($mhsId, 4, '0', STR_PAD_LEFT));
            $nama = $siakad?->nama_lengkap ?? $tipe?->nama_mahasiswa ?? ('Mahasiswa #' . $mhsId);
            $prodi = $siakad?->programStudi?->nama ?? $siakad?->programStudi?->nama_prodi ?? 'Teknik Informatika';

            $totalNominal = collect($request->items)->sum('nominal');
            $potongan = (float)$request->input('potongan', 0);
            $totalBersih = max(0, $totalNominal - $potongan);
            $jumlahBayar = (float)$request->jumlah_bayar;

            $nomorTagihan = 'INV-LOKET-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $catatanTransaksi = $request->catatan ?: ('Pembayaran Kasir Loket ' . date('d/m/Y'));

            // 1. Create Tagihan
            $tagihan = TagihanMahasiswa::create([
                'mahasiswa_id' => $mhsId,
                'tahun_akademik_id' => 1,
                'nomor_tagihan' => $nomorTagihan,
                'total_tagihan' => $totalNominal,
                'total_potongan' => $potongan,
                'total_denda' => 0,
                'total_bayar' => $jumlahBayar,
                'status' => $jumlahBayar >= $totalBersih ? 'lunas' : 'sebagian',
                'source_system' => 'SIKEU_LOKET',
                'jatuh_tempo' => now()->toDateString(),
                'catatan_approval' => 'Pembayaran kasir: ' . $catatanTransaksi,
            ]);

            // 2. Insert Detail Tagihan
            foreach ($request->items as $item) {
                $mbId = $item['master_biaya_id'] ?? null;
                if (!$mbId && !empty($item['master_biaya_kode'])) {
                    $mb = MasterBiaya::where('kode', $item['master_biaya_kode'])->first();
                    $mbId = $mb?->id;
                }
                if (!$mbId) {
                    $mb = MasterBiaya::first();
                    $mbId = $mb?->id ?? 1;
                } else {
                    $mb = MasterBiaya::find($mbId);
                }

                $itemDesc = $item['keterangan'] ?? (($mb?->nama ?? 'Komponen Biaya') . ' - ' . $catatanTransaksi);

                DetailTagihan::create([
                    'tagihan_id' => $tagihan->id,
                    'master_biaya_id' => $mbId,
                    'nominal' => $item['nominal'],
                    'potongan' => 0,
                    'nominal_bersih' => $item['nominal'],
                    'keterangan' => $itemDesc,
                ]);
            }

            // 3. Potongan jika ada
            if ($potongan > 0) {
                PotonganTagihan::create([
                    'tagihan_id' => $tagihan->id,
                    'tipe' => 'diskon',
                    'nominal_potongan' => $potongan,
                    'keterangan' => $request->alasan_potongan ?? 'Diskon khusus kasir loket',
                    'diinput_oleh' => auth()->id() ?? 1,
                ]);
            }

            // 4. Create Pembayaran
            $kodeTransaksi = 'TRX-LOKET-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan->id,
                'kode_transaksi' => $kodeTransaksi,
                'jumlah_bayar' => $jumlahBayar,
                'waktu_bayar' => now(),
                'channel_bayar' => $request->channel_bayar,
                'status' => 'success',
                'diverifikasi_oleh' => auth()->id() ?? 1,
            ]);

            // 5. Auto Jurnal
            $this->createAutoJurnal($pembayaran, $request->channel_bayar, $tagihan);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran langsung loket kasir berhasil diproses!',
                'data' => [
                    'pembayaran' => $pembayaran,
                    'tagihan' => $tagihan,
                    'kuitansi' => [
                        'kode_transaksi' => $kodeTransaksi,
                        'nomor_tagihan' => $nomorTagihan,
                        'nama_mahasiswa' => $nama,
                        'nim' => $nim,
                        'program_studi' => $prodi,
                        'periode_label' => $catatanTransaksi,
                        'total_tagihan' => $totalNominal,
                        'potongan' => $potongan,
                        'total_bersih' => $totalBersih,
                        'jumlah_dibayar' => $jumlahBayar,
                        'sisa_setelah_bayar' => max(0, $totalBersih - $jumlahBayar),
                        'channel_bayar' => $request->channel_bayar,
                        'waktu_transaksi' => now()->format('d M Y H:i:s'),
                        'petugas_kasir' => auth()->user()?->username ?? 'Kasir Loket',
                    ]
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Direct Cashier Payment Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses pembayaran langsung kasir: ' . $e->getMessage(),
            ], 500);
        }
    }
}
