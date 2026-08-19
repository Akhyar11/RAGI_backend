<?php

namespace App\Services\SPMB;

use App\Models\Spmb\GelombangPenerimaan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GelombangService
{
    /**
     * Get all gelombang
     */
    public function getAllGelombang()
    {
        return GelombangPenerimaan::with('jalurMasuk')->orderBy('tanggal_buka', 'desc')->get();
    }

    /**
     * Store a new gelombang
     */
    public function storeGelombang(array $data)
    {
        DB::beginTransaction();
        try {
            $gelombang = GelombangPenerimaan::create($data);
            DB::commit();
            return $gelombang;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Gelombang: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update existing gelombang
     */
    public function updateGelombang($id, array $data)
    {
        DB::beginTransaction();
        try {
            $gelombang = GelombangPenerimaan::findOrFail($id);
            $gelombang->update($data);
            DB::commit();
            return $gelombang;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update Gelombang: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete gelombang
     */
    public function deleteGelombang($id)
    {
        DB::beginTransaction();
        try {
            $gelombang = GelombangPenerimaan::findOrFail($id);
            $gelombang->delete();
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete Gelombang: ' . $e->getMessage());
            throw $e;
        }
    }
}
