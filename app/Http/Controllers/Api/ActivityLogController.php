<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Display activity logs (READ ONLY)
     */
    public function index(Request $request)
    {
        $logs = $this->activityLogService->getLogs($request);

        return response()->json([
            'success' => true,
            'message' => 'Activity logs retrieved successfully',
            'data'    => $logs->items(),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
                'last_page'    => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Show single activity log detail
     */
    public function show($id)
    {
        $log = \App\Models\ActivityLog::with('user:id,name,email')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Activity log detail',
            'data'    => $log,
        ]);
    }
}
