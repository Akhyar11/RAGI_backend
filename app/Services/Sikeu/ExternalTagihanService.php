<?php

namespace App\Services\Sikeu;

use App\Models\Sikeu\DetailTagihan;
use App\Models\Sikeu\MasterBiaya;
use App\Models\Sikeu\PaymentGatewayConfig;
use App\Models\Sikeu\PotonganTagihan;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Sikeu\VirtualAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExternalTagihanService
{
    /**
     * Ambil master biaya berdasarkan kode, atau buat otomatis bila belum ada
     * agar tagihan eksternal tetap dapat dicatat.
     */
    public function resolveMasterBiaya(array $item, string $tipe): MasterBiaya
    {
        $masterBiaya = MasterBiaya::where('kode', $item['master_biaya_kode'])->first();

        if (! $masterBiaya) {
            $masterBiaya = MasterBiaya::create([
                'kode' => $item['master_biaya_kode'],
                'nama' => $item['keterangan'] ?? $item['master_biaya_kode'],
                'tipe' => $tipe,
                'nominal_standar' => (float) $item['nominal'],
                'is_active' => true,
            ]);

            Log::info("Master biaya '{$item['master_biaya_kode']}' dibuat otomatis (tipe {$tipe}).");
        }

        return $masterBiaya;
    }

    /**
     * Terbitkan tagihan eksternal dari sistem luar (SPMB, SIAKAD, SIMPEG, SIPPM).
     * Seluruh tulis database dibungkus transaksi; error didelegasikan ke pemanggil.
     *
     * @return array{tagihan: TagihanMahasiswa, virtual_account: VirtualAccount|array|null, requires_approval: bool}
     */
    public function issueExternalBill(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $sourceSystem = strtoupper($data['source_system']);
            $requiresApproval = (bool) ($data['requires_approval'] ?? false);
            $nomorTagihan = 'INV-'.$sourceSystem.'-'.date('Ymd').'-'.Str::random(5);

            $totalNominal = 0;
            $totalPotongan = 0;

            // Compute details
            $detailsData = [];
            $masterBiayaTipe = $data['master_biaya_tipe'] ?? 'lainnya';
            foreach ($data['details'] as $item) {
                // Resolve via Service layer (auto-provision bila kode belum terdaftar).
                $masterBiaya = $this->resolveMasterBiaya($item, $masterBiayaTipe);

                $nominal = (float) $item['nominal'];
                $totalNominal += $nominal;

                $detailsData[] = [
                    'master_biaya_id' => $masterBiaya->id,
                    'nominal' => $nominal,
                    'potongan' => 0,
                    'nominal_bersih' => $nominal,
                    'keterangan' => $item['keterangan'] ?? 'Komponen tagihan '.$item['master_biaya_kode'],
                ];
            }

            // Compute deductions
            $potonganData = [];
            if (isset($data['potongan']) && is_array($data['potongan'])) {
                foreach ($data['potongan'] as $pot) {
                    $nomPot = (float) $pot['nominal_potongan'];
                    $totalPotongan += $nomPot;
                    $potonganData[] = [
                        'tipe' => $pot['tipe'] ?? 'diskon',
                        'nominal_potongan' => $nomPot,
                        'keterangan' => $pot['keterangan'] ?? 'Potongan khusus eksternal',
                        'diinput_oleh' => auth()->id() ?? 1,
                    ];
                }
            }

            $totalBayar = max(0, $totalNominal - $totalPotongan);
            $initialStatus = $requiresApproval ? 'pending_approval' : 'belum_bayar';
            $statusApproval = $requiresApproval ? 'pending' : 'approved';
            $tipeReferensi = $data['tipe_referensi'] ?? (! empty($data['calon_mahasiswa_id']) ? 'calon_mahasiswa' : 'mahasiswa');

            $tagihan = TagihanMahasiswa::create([
                'mahasiswa_id' => $data['mahasiswa_id'] ?? null,
                'calon_mahasiswa_id' => $data['calon_mahasiswa_id'] ?? null,
                'tipe_referensi' => $tipeReferensi,
                'tahun_akademik_id' => $data['tahun_akademik_id'] ?? 1,
                'nomor_tagihan' => strtoupper($nomorTagihan),
                'total_tagihan' => $totalNominal,
                'total_potongan' => $totalPotongan,
                'total_denda' => 0,
                'total_bayar' => 0,
                'status' => $initialStatus,
                'requires_approval' => $requiresApproval,
                'status_approval' => $statusApproval,
                'source_system' => $sourceSystem,
                'catatan_approval' => $data['keterangan'] ?? null,
                'jatuh_tempo' => $data['jatuh_tempo'] ?? date('Y-m-d', strtotime('+30 days')),
            ]);

            // Save details
            foreach ($detailsData as $detail) {
                $detail['tagihan_id'] = $tagihan->id;
                DetailTagihan::create($detail);
            }

            // Save deductions
            foreach ($potonganData as $pot) {
                $pot['tagihan_id'] = $tagihan->id;
                PotonganTagihan::create($pot);
            }

            // If no approval required, generate VA automatically (Xendit Integration or Local VA)
            $vaData = null;
            if (! $requiresApproval) {
                $bankCode = 'BNI';
                $vaNumber = '888'.date('ymd').str_pad($tagihan->id, 5, '0', STR_PAD_LEFT);

                // Check active Payment Gateway Config
                $pgConfig = PaymentGatewayConfig::where('is_active', true)->first();
                $apiKey = $pgConfig->api_key_encrypted ?? $pgConfig->public_key_encrypted ?? null;

                if ($pgConfig && $pgConfig->gateway_name === 'xendit' && ! empty($apiKey)) {
                    try {
                        $xenditRes = Http::withoutVerifying()
                            ->withBasicAuth($apiKey, '')
                            ->post('https://api.xendit.co/callback_virtual_accounts', [
                                'external_id' => $tagihan->nomor_tagihan,
                                'bank_code' => $bankCode,
                                'name' => 'SPMB Calon Mhs #'.($data['calon_mahasiswa_id'] ?? $tagihan->id),
                                'expected_amount' => (int) $totalBayar,
                                'is_closed' => true,
                                'is_single_use' => true,
                                'expiration_date' => date('c', strtotime('+30 days')),
                            ]);

                        if ($xenditRes->successful()) {
                            $xData = $xenditRes->json();
                            $vaNumber = $xData['account_number'] ?? $vaNumber;
                            $bankCode = $xData['bank_code'] ?? $bankCode;
                            Log::info("Xendit VA Created Successfully: VA {$vaNumber} for Tagihan {$tagihan->nomor_tagihan}");
                        } else {
                            Log::error("Xendit VA Creation Error ({$xenditRes->status()}): ".$xenditRes->body());
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Xendit VA Creation Exception: '.$e->getMessage());
                    }
                }

                $vaData = VirtualAccount::create([
                    'tagihan_id' => $tagihan->id,
                    'va_number' => $vaNumber,
                    'bank_kode' => $bankCode,
                    'bank_nama' => 'Bank '.$bankCode,
                    'nominal' => $totalBayar,
                    'expired_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'status' => 'aktif',
                ]);
            }

            return [
                'tagihan' => $tagihan->load(['detailTagihan', 'potonganTagihan']),
                'virtual_account' => $vaData,
                'requires_approval' => $requiresApproval,
            ];
        });
    }
}
