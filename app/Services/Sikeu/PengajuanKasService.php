<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\PengajuanPencairanKas;
use App\Models\Sikeu\TransaksiKasUnit;
use App\Models\Sikeu\UnitKas;
use App\Models\Sikeu\PengeluaranKampus;
use App\Models\Simpeg\SuratTugas;
use App\Services\Sikeu\AutoJournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PengajuanKasService
{
    /**
     * Buat pengajuan pencairan kas baru.
     */
    public function create(array $data, ?int $pemohonId): PengajuanPencairanKas
    {
        return DB::transaction(function () use ($data, $pemohonId) {
            $pengajuan = PengajuanPencairanKas::create([
                'nomor_pengajuan' => 'PK-' . time(),
                'unit_kas_id' => $data['unit_kas_id'],
                'unit_kerja_id' => $data['unit_kerja_id'],
                'pemohon_id' => $pemohonId,
                'judul_pengajuan' => $data['judul_pengajuan'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'nominal_diajukan' => $data['nominal_diajukan'],
                'nominal_disetujui' => null,
                'jenis_pengajuan' => $data['jenis_pengajuan'],
                'status' => 'pending_keuangan',
            ]);

            return $pengajuan->load(['unitKas', 'pemohon']);
        });
    }

    /**
     * Update pengajuan pencairan kas.
     */
    public function update(PengajuanPencairanKas $pengajuan, array $data): PengajuanPencairanKas
    {
        if ($pengajuan->status !== 'pending_keuangan') {
            throw ValidationException::withMessages([
                'status' => ['Hanya pengajuan dengan status pending keuangan yang dapat diubah.'],
            ]);
        }

        $pengajuan->update($data);

        return $pengajuan->fresh(['unitKas', 'pemohon']);
    }

    /**
     * Setujui dan cairkan pengajuan kas.
     */
    public function approve(PengajuanPencairanKas $pengajuan, array $data, ?int $userId): PengajuanPencairanKas
    {
        if ($pengajuan->status === 'dicairkan') {
            throw ValidationException::withMessages([
                'status' => ['Pengajuan ini sudah dicairkan sebelumnya.'],
            ]);
        }

        return DB::transaction(function () use ($pengajuan, $data, $userId) {
            $nominalDisetujui = isset($data['nominal_disetujui']) && $data['nominal_disetujui'] !== null
                ? (float) $data['nominal_disetujui']
                : ($pengajuan->nominal_disetujui ?? $pengajuan->nominal_diajukan);

            $unitKasId = !empty($data['unit_kas_id']) ? $data['unit_kas_id'] : $pengajuan->unit_kas_id;

            $pengajuan->status = 'dicairkan';
            $pengajuan->nominal_disetujui = $nominalDisetujui;
            if ($unitKasId) {
                $pengajuan->unit_kas_id = $unitKasId;
            }
            $pengajuan->approved_keuangan_by = $userId;
            $pengajuan->approved_keuangan_at = now();
            $pengajuan->save();

            // Mutasi saldo Unit Kas
            $unitKas = UnitKas::find($pengajuan->unit_kas_id);
            if ($unitKas) {
                $saldoSebelum = $unitKas->saldo_saat_ini;
                $unitKas->saldo_saat_ini += $pengajuan->nominal_disetujui;
                $unitKas->save();

                TransaksiKasUnit::create([
                    'unit_kas_id' => $unitKas->id,
                    'pengajuan_pencairan_id' => $pengajuan->id,
                    'kode_transaksi' => 'TRX-' . time(),
                    'jenis_transaksi' => 'debet_pemasukan',
                    'nominal' => $pengajuan->nominal_disetujui,
                    'saldo_sebelum' => $saldoSebelum,
                    'saldo_sesudah' => $unitKas->saldo_saat_ini,
                    'keterangan' => 'Pencairan: ' . $pengajuan->judul_pengajuan,
                    'tanggal_transaksi' => now()->toDateString(),
                ]);

                // Trigger Auto Journal (Debet Beban Operasional Unit, Kredit Kas Utama Rektorat)
                AutoJournalService::recordDisbursementJournal(
                    'KAS_UNIT',
                    $pengajuan->id,
                    (float)$pengajuan->nominal_disetujui,
                    'Pencairan Kas Unit: ' . $pengajuan->judul_pengajuan,
                    '502.01'
                );
            }

            // Catat di Pengeluaran Kampus
            $nomorPengeluaran = 'KAS-OUT-' . date('Ymd') . '-' . sprintf('%04d', $pengajuan->id);
            $pengeluaran = PengeluaranKampus::firstOrNew(['nomor_transaksi' => $nomorPengeluaran]);

            $pengeluaran->fill([
                'kategori' => 'kegiatan',
                'nominal' => $nominalDisetujui,
                'net_dibayarkan' => $nominalDisetujui,
                'keterangan' => 'Pencairan: ' . $pengajuan->judul_pengajuan . ($pengajuan->deskripsi ? ' (' . $pengajuan->deskripsi . ')' : ''),
                'tanggal_transaksi' => now()->toDateString(),
                'status_pembayaran' => 'lunas',
                'created_by' => $userId,
            ]);
            $pengeluaran->save();

            // Update status pencairan pada Surat Tugas SIMPEG menjadi sudah_cair jika terhubung
            $suratTugas = SuratTugas::where('sikeu_pencairan_id', $pengajuan->id)->first();
            if ($suratTugas) {
                $suratTugas->update([
                    'nominal_disetujui' => $nominalDisetujui,
                    'status_pencairan' => 'sudah_cair',
                ]);
            }

            return $pengajuan->fresh(['unitKas', 'pemohon', 'suratTugas.pegawai']);
        });
    }

    /**
     * Tolak pengajuan pencairan kas.
     */
    public function reject(PengajuanPencairanKas $pengajuan, array $data, ?int $userId): PengajuanPencairanKas
    {
        if ($pengajuan->status === 'dicairkan') {
            throw ValidationException::withMessages([
                'status' => ['Pengajuan yang sudah dicairkan tidak dapat ditolak.'],
            ]);
        }

        return DB::transaction(function () use ($pengajuan, $data, $userId) {
            $catatan = $data['catatan'] ?? 'Ditolak oleh bagian keuangan';
            $pengajuan->status = 'ditolak';
            $pengajuan->deskripsi = ($pengajuan->deskripsi ? $pengajuan->deskripsi . "\n" : '') . '[Ditolak Keuangan]: ' . $catatan;
            $pengajuan->approved_keuangan_by = $userId;
            $pengajuan->approved_keuangan_at = now();
            $pengajuan->save();

            // Update Surat Tugas SIMPEG menjadi ditolak jika terhubung
            $suratTugas = SuratTugas::where('sikeu_pencairan_id', $pengajuan->id)->first();
            if ($suratTugas) {
                $suratTugas->update([
                    'status_pencairan' => 'ditolak',
                ]);
            }

            return $pengajuan->fresh(['unitKas', 'pemohon', 'suratTugas.pegawai']);
        });
    }
}
