<?php

namespace App\Services\Shared;

use App\Models\Shared\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared AuditLogService
 *
 * Base service for audit log operations that works with both Central and Tenant contexts.
 * Each context (Central/Tenant) extends this with their specific implementations.
 *
 * MULTI-DATABASE TENANCY:
 * - Uses App\Models\Shared\Activity (works in both contexts)
 * - Central: Activity stored in central database (admin actions)
 * - Tenant: Activity stored in tenant database (user actions)
 * - The only difference is log retention limits (tenant-only feature)
 */
abstract class AuditLogService
{
    /**
     * Get the Activity model class for this context.
     *
     * @return class-string<Activity>
     */
    abstract protected function getActivityModel(): string;

    /**
     * Get the causer type for filtering (morph map key).
     * Central: 'admin', Tenant: 'user'
     */
    abstract protected function getCauserType(): string;

    /**
     * Create a new query builder for the Activity model.
     *
     * @return Builder<Activity>
     */
    protected function query(): Builder
    {
        $modelClass = $this->getActivityModel();

        return $modelClass::query();
    }

    /**
     * Get paginated activities with applied filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getActivities(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['causer:id,name,email', 'subject'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get a single activity by ID.
     */
    public function getActivity(string $id): ?Model
    {
        return $this->query()
            ->with(['causer:id,name,email', 'subject'])
            ->find($id);
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
        return $this->query()
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
        return $this->query()
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
        return $this->query()
            ->distinct()
            ->pluck('log_name')
            ->filter()
            ->values();
    }

    /**
     * Get activity statistics for a date range.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = $this->query();

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return [
            'total' => $query->count(),
            'by_event' => (clone $query)->selectRaw('event, count(*) as count')
                ->groupBy('event')
                ->pluck('count', 'event')
                ->toArray(),
            'by_log_name' => (clone $query)->selectRaw('log_name, count(*) as count')
                ->groupBy('log_name')
                ->pluck('count', 'log_name')
                ->toArray(),
        ];
    }

    /**
     * Export audit logs to CSV.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportToCsv(array $filters): StreamedResponse
    {
        $query = $this->query()
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
     * Log a custom activity.
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        string $event = 'custom',
        string $logName = 'default',
        array $properties = []
    ): Model {
        $modelClass = $this->getActivityModel();

        return $modelClass::create([
            'log_name' => $logName,
            'description' => $description,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'causer_type' => $causer ? $causer->getMorphClass() : null,
            'causer_id' => $causer?->getKey(),
            'event' => $event,
            'properties' => $properties,
        ]);
    }

    /**
     * Apply filters to the activity query.
     *
     * @param  Builder<Activity>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['user_id'])) {
            $query->where('causer_id', $filters['user_id'])
                ->where('causer_type', $this->getCauserType());
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

    /**
     * Delete old activity records.
     * Override in tenant service to respect plan limits.
     */
    public function pruneOldRecords(int $days): int
    {
        return $this->query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
