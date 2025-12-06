<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Activity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class TeamActivityController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.TenantPermission::TEAM_ACTIVITY->value),
        ];
    }

    /**
     * Display the team activity log.
     *
     * MULTI-DATABASE TENANCY:
     * - Activity log is in tenant database (no tenant_id needed)
     * - Data isolation is at database level
     */
    public function index(Request $request)
    {
        $tenant = tenant();

        // Get filter parameters
        $filters = [
            'user_id' => $request->input('user_id'),
            'event' => $request->input('event'),
            'subject_type' => $request->input('subject_type'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        // Build query for activities
        // Multi-database: no tenant_id filter - data isolated per database
        $query = Activity::query()
            ->with(['causer:id,name,email', 'subject'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($filters['user_id']) {
            $query->where('causer_id', $filters['user_id'])
                ->where('causer_type', 'App\\Models\\User');
        }

        if ($filters['event']) {
            $query->where('event', $filters['event']);
        }

        if ($filters['subject_type']) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if ($filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Paginate results
        $activities = $query->paginate(20)->through(function ($activity) {
            return [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'subject_name' => $this->getSubjectName($activity),
                'causer' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                    'email' => $activity->causer->email,
                ] : null,
                'properties' => $activity->properties?->toArray(),
                'created_at' => $activity->created_at->toISOString(),
                'created_at_human' => $activity->created_at->diffForHumans(),
            ];
        });

        // Get team members for filter dropdown
        $teamMembers = $tenant->users()
            ->select('users.id', 'users.name', 'users.email')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        // Get unique event types for filter
        $eventTypes = Activity::query()
            ->distinct()
            ->pluck('event')
            ->filter()
            ->values();

        // Get unique subject types for filter
        $subjectTypes = Activity::query()
            ->distinct()
            ->pluck('subject_type')
            ->filter()
            ->map(fn ($type) => [
                'value' => $type,
                'label' => class_basename($type),
            ])
            ->values();

        return Inertia::render('tenant/admin/team/activity', [
            'activities' => $activities,
            'teamMembers' => $teamMembers,
            'eventTypes' => $eventTypes,
            'subjectTypes' => $subjectTypes,
            'filters' => $filters,
            // NOTE: Don't override 'tenant' - use shared props from HandleInertiaRequests
            // which includes plan, features, limits, etc. needed for sidebar navigation
        ]);
    }

    /**
     * Get a human-readable name for the activity subject.
     */
    protected function getSubjectName(Activity $activity): ?string
    {
        if (! $activity->subject) {
            return null;
        }

        // Try common name attributes
        if (isset($activity->subject->name)) {
            return $activity->subject->name;
        }

        if (isset($activity->subject->title)) {
            return $activity->subject->title;
        }

        if (isset($activity->subject->email)) {
            return $activity->subject->email;
        }

        return "#{$activity->subject_id}";
    }
}
