<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * Get paginated activity logs with filters
     */
    public function getLogs(Request $request)
    {
        $query = ActivityLog::with('user:id,name,email')
            ->latest();

        // Filter by aksi
        if ($request->filled('aksi')) {
            $query->byAksi($request->aksi);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->forUser($request->user_id);
        }

        // Filter date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->dateRange(
                $request->start_date,
                $request->end_date
            );
        }

        // Pagination
        $perPage = $request->get('per_page', 10);

        return $query->paginate($perPage);
    }

    /**
     * Store activity log (dipakai oleh controller lain)
     */
    public function log(
        int $userId,
        string $aksi,
        array|string|null $catatan = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id'    => $userId,
            'aksi'       => $aksi,
            'catatan'    => is_array($catatan) ? json_encode($catatan) : $catatan,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
