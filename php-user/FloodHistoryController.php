<?php

namespace App\Http\Controllers;

use App\Models\FloodHistory;
use Illuminate\Http\Request;

class FloodHistoryController extends Controller
{
    // GET /api/flood-history
    //Role: User, Admin
    
    public function index()
    {
        try {
            $histories = FloodHistory::orderBy('flood_date', 'desc')->get();

            if ($histories->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Belum ada data riwayat banjir',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data riwayat banjir berhasil diambil',
                'data' => $histories
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data riwayat banjir',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // GET /api/flood-history/{id}
    // Role: User, Admin

    public function show($id)
    {
        try {
            $history = FloodHistory::find($id);

            if (!$history) {
                return response()->json([
                    'success' => false,
                    'message' => 'Riwayat banjir tidak ditemukan',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail riwayat banjir',
                'data' => $history
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail riwayat banjir',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // DELETE /api/flood-history/{id}
    // Role: Admin

    public function destroy($id)
    {
        try {
            $history = FloodHistory::find($id);

            if (!$history) {
                return response()->json([
                    'success' => false,
                    'message' => 'Riwayat banjir tidak ditemukan',
                    'data' => null
                ], 404);
            }

            $history->delete();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat banjir berhasil dihapus',
                'data' => null
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus riwayat banjir',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}