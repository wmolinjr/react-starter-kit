<?php

namespace App\Http\Resources\Shared;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * ActivityResource
 *
 * SHARED RESOURCE:
 * - Works in both Central and Tenant contexts
 * - Transforms App\Models\Shared\Activity for listing views
 */
class ActivityResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'description' => 'string',
            'event' => 'string',
            'log_name' => 'string',
            'subject_type' => 'string | null',
            'subject_id' => 'string | null',
            'subject_name' => 'string | null',
            'causer' => 'ActivityCauser | undefined',
            'created_at' => 'string',
            'created_at_human' => 'string',
            'created_at_formatted' => 'string',
            'properties' => 'ActivityProperties',
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'event' => $this->event,
            'log_name' => $this->log_name,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->getSubjectName(),
            'causer' => $this->when(
                $this->relationLoaded('causer') && $this->causer,
                fn () => [
                    'id' => $this->causer->id,
                    'name' => $this->causer->name,
                    'email' => $this->causer->email,
                ]
            ),
            'created_at' => $this->formatIso($this->created_at),
            'created_at_human' => $this->formatDiff($this->created_at),
            'created_at_formatted' => $this->formatDate($this->created_at, 'd/m/Y H:i:s'),

            // Properties (changes)
            'properties' => $this->formatProperties(),
        ];
    }

    /**
     * Get a human-readable name for the activity subject.
     */
    protected function getSubjectName(): ?string
    {
        if (! $this->subject) {
            $properties = $this->properties?->toArray() ?? [];
            if (isset($properties['old']['name'])) {
                return $properties['old']['name'].' (deleted)';
            }
            if (isset($properties['attributes']['name'])) {
                return $properties['attributes']['name'];
            }

            return null;
        }

        return $this->subject->name
            ?? $this->subject->title
            ?? $this->subject->email
            ?? "#{$this->subject_id}";
    }

    /**
     * Format properties for display.
     */
    protected function formatProperties(): array
    {
        $properties = $this->properties?->toArray() ?? [];

        return [
            'old' => $properties['old'] ?? null,
            'new' => $properties['attributes'] ?? null,
            'extra' => array_diff_key($properties, ['old' => 1, 'attributes' => 1]),
        ];
    }
}
