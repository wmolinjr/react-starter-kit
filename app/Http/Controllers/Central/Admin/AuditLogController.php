<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\UserSummaryResource;
use App\Http\Resources\Shared\ActivityResource;
use App\Models\Central\Activity;
use App\Models\Central\User;
use App\Services\Central\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller implements HasMiddleware
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.CentralPermission::AUDIT_VIEW->value, only: ['index', 'show']),
            new Middleware('permission:'.CentralPermission::AUDIT_EXPORT->value, only: ['export']),
        ];
    }

    /**
     * Display the audit log listing.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'user_id' => $request->input('user_id'),
            'event' => $request->input('event'),
            'subject_type' => $request->input('subject_type'),
            'log_name' => $request->input('log_name'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        $activities = $this->auditLogService->getActivities($filters);
        $filterOptions = $this->auditLogService->getFilterOptions();

        // Get admin users for filter dropdown
        $adminUsers = User::select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('central/admin/audit/index', [
            'activities' => ActivityResource::collection($activities),
            'adminUsers' => UserSummaryResource::collection($adminUsers),
            'eventTypes' => $filterOptions['eventTypes'],
            'subjectTypes' => $filterOptions['subjectTypes'],
            'logNames' => $filterOptions['logNames'],
            'filters' => $filters,
        ]);
    }

    /**
     * Show detailed activity log entry.
     */
    public function show(Activity $activity)
    {
        $activity->load(['causer:id,name,email', 'subject']);

        return response()->json([
            'activity' => new ActivityResource($activity),
        ]);
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'user_id' => $request->input('user_id'),
            'event' => $request->input('event'),
            'subject_type' => $request->input('subject_type'),
            'log_name' => $request->input('log_name'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        return $this->auditLogService->exportToCsv($filters);
    }
}
