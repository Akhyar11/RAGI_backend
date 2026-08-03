<?php

namespace App\Http\Controllers\Sikeu;

use App\Http\Controllers\Controller;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MahasiswaTagihanController extends Controller
{
    /**
     * GET /api/v1/sikeu/mahasiswa/tagihan
     * List bills for logged-in student (or student portal view)
     */
    public function myBills(Request $request)
    {
        $mahasiswaId = $request->query('mahasiswa_id', auth()->id() ?? 1);

        $tagihans = TagihanMahasiswa::with(['details.jenisBiaya', 'virtualAccount', 'dispensasis'])
            ->where('mahasiswa_id', $mahasiswaId)
            ->orderBy('id', 'desc')
            ->get();

        $data = $tagihans->map(function ($t) {
            return [
                'id' => $t->id,
                'nomor_tagihan' => $t->nomor_tagihan,
                'tahun_akademik' => '2025/2026 Ganjil',
                'total_tagihan' => (float)$t->total_tagihan,
                'total_potongan' => (float)$t->total_potongan,
                'total_denda' => (float)$t->total_denda,
                'total_bayar' => (float)$t->total_bayar,
                'sisa_bayar' => (float)($t->total_tagihan - $t->total_bayar),
                'status' => $t->status,
                'jatuh_tempo' => $t->jatuh_tempo,
                'va_number' => $t->virtualAccount->va_number ?? null,
                'bank_nama' => $t->virtualAccount->bank_nama ?? 'Bank BNI',
                'details' => $t->details->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'nama_biaya' => $d->jenisBiaya->nama ?? 'Biaya Kuliah',
                        'nominal' => (float)$d->nominal,
                        'potongan' => (float)$d->potongan,
                        'nominal_bersih' => (float)$d->nominal_bersih,
                    ];
                }),
                'dispensasi_aktif' => $t->dispensasis->where('status', 'approved')->first(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * GET /api/v1/sikeu/mahasiswa/invoice/{id}
     * Generate or view official invoice and Virtual Account details for a bill
     */
    public function generateInvoice($id)
    {
        $tagihan = TagihanMahasiswa::with(['details.jenisBiaya', 'virtualAccount'])->findOrFail($id);

        // Auto create Virtual Account if not exist
        if (!$tagihan->virtualAccount) {
            $vaNumber = '8801' . str_pad($tagihan->id, 8, '0', STR_PAD_LEFT);
            $va = VirtualAccount::create([
                'tagihan_id' => $tagihan->id,
                'va_number' => $vaNumber,
                'bank_kode' => 'BNI',
                'bank_nama' => 'Bank BNI (Virtual Account)',
                'nominal' => $tagihan->total_tagihan - $tagihan->total_bayar,
                'expired_at' => now()->addDays(30),
                'status' => 'aktif',
            ]);
            $tagihan->load('virtualAccount');
        }

        $invoice = [
            'invoice_number' => 'INV-' . $tagihan->nomor_tagihan,
            'tanggal_terbit' => $tagihan->created_at ? $tagihan->created_at->format('Y-m-d') : date('Y-m-d'),
            'jatuh_tempo' => $tagihan->jatuh_tempo,
            'mahasiswa' => [
                'nama' => 'Mahasiswa #' . $tagihan->mahasiswa_id,
                'nim' => '2024' . str_pad($tagihan->mahasiswa_id, 4, '0', STR_PAD_LEFT),
                'prodi' => 'Teknik Informatika',
                'angkatan' => 2024,
            ],
            'virtual_account' => [
                'bank' => $tagihan->virtualAccount->bank_nama ?? 'Bank BNI',
                'va_number' => $tagihan->virtualAccount->va_number,
                'nominal_instruksi' => (float)($tagihan->total_tagihan - $tagihan->total_bayar),
                'expired_at' => $tagihan->virtualAccount->expired_at,
            ],
            'ringkasan' => [
                'subtotal' => (float)$tagihan->total_tagihan,
                'potongan' => (float)$tagihan->total_potongan,
                'denda' => (float)$tagihan->total_denda,
                'total_dibayar' => (float)$tagihan->total_bayar,
                'sisa_tagihan' => (float)($tagihan->total_tagihan - $tagihan->total_bayar),
                'status' => $tagihan->status,
            ],
            'items' => $tagihan->details->map(function ($d) {
                return [
                    'deskripsi' => $d->jenisBiaya->nama ?? 'Komponen Biaya Pendidikan',
                    'nominal' => (float)$d->nominal,
                    'potongan' => (float)$d->potongan,
                    'nominal_bersih' => (float)$d->nominal_bersih,
                ];
            }),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $invoice
        ]);
    }
}
