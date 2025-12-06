<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AuditLogService
 *
 * Handles all business logic for audit log operations in tenant context.
 *
 * MULTI-DATABASE TENANCY:
 * - Activity log is in tenant database (no tenant_id needed)
 * - Data isolation is at database level
 */
class AuditLogService
{
    /**
     * Get paginated activities with applied filters.
     *
     * Returns Activity models for use with ActivityResource.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getActivities(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = Activity::query()
            ->with(['causer:id,name,email', 'subject'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get filter options for the audit log UI.
     *
     * @return array<string, mixed>
     */
    public function getFilterOptions(): array
    {
        return [
            'eventTypes' => $this->getEventTypes(),
            'subjectTypes' => $this->getSubjectTypes(),
            'logNames' => $this->getLogNames(),
        ];
    }

    /**
     * Get unique event types.
     */
    public function getEventTypes(): Collection
    {
        return Activity::query()
            ->distinct()
            ->pluck('event')
            ->filter()
            ->values();
    }

    /**
     * Get unique subject types with labels.
     */
    public function getSubjectTypes(): Collection
    {
        return Activity::query()
            ->distinct()
            ->pluck('subject_type')
            ->filter()
            ->map(fn (string $type) => [
                'value' => $type,
                'label' => class_basename($type),
            ])
            ->values();
    }

    /**
     * Get unique log names.
     */
    public function getLogNames(): Collection
    {
        return Activity::query()
            ->distinct()
            ->pluck('log_name')
            ->filter()
            ->values();
    }

    /**
     * Export audit logs to CSV.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportToCsv(array $filters): StreamedResponse
    {
        $query = Activity::query()
            ->with(['causer:id,name,email'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);

        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // CSV Header
            fputcsv($handle, [
                'Date',
                'Time',
                'User',
                'Email',
                'Event',
                'Subject Type',
                'Subject ID',
                'Description',
                'Log Name',
                'Changes',
            ]);

            // Stream data in chunks
            $query->chunk(100, function ($activities) use ($handle) {
                foreach ($activities as $activity) {
                    $changes = '';
                    if ($activity->properties) {
                        $props = $activity->properties->toArray();
                        if (isset($props['old']) || isset($props['attributes'])) {
                            $changes = json_encode([
                                'old' => $props['old'] ?? [],
                                'new' => $props['attributes'] ?? [],
                            ]);
                        }
                    }

                    fputcsv($handle, [
                        $activity->created_at->format('Y-m-d'),
                        $activity->created_at->format('H:i:s'),
                        $activity->causer?->name ?? 'System',
                        $activity->causer?->email ?? '-',
                        $activity->event,
                        $activity->subject_type ? class_basename($activity->subject_type) : '-',
                        $activity->subject_id ?? '-',
                        $activity->description,
                        $activity->log_name ?? 'default',
                        $changes,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Apply filters to the activity query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Activity>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['user_id'])) {
            $query->where('causer_id', $filters['user_id'])
                ->where('causer_type', 'App\\Models\\User');
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (! empty($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('causer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }
    }

}
