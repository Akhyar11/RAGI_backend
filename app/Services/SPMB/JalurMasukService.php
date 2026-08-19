<?php

namespace App\Services\SPMB;

use App\Models\Spmb\JalurMasuk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JalurMasukService
{
    /**
     * Get all jalur masuk
     */
    public function getAllJalur()
    {
        return JalurMasuk::all();
    }

    /**
     * Store a new jalur masuk
     */
    public function storeJalur(array $data)
    {
        DB::beginTransaction();
        try {
            $jalur = JalurMasuk::create($data);
            DB::commit();
            return $jalur;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Jalur Masuk: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update existing jalur masuk
     */
    public function updateJalur($id, array $data)
    {
        DB::beginTransaction();
        try {
            $jalur = JalurMasuk::findOrFail($id);
            $jalur->update($data);
            DB::commit();
            return $jalur;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update Jalur Masuk: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete jalur masuk
     */
    public function deleteJalur($id)
    {
        DB::beginTransaction();
        try {
            $jalur = JalurMasuk::findOrFail($id);
            $jalur->delete();
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete Jalur Masuk: ' . $e->getMessage());
            throw $e;
        }
    }
}
