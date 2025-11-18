<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any media.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // Users can view media from their current tenant
        return $user->current_tenant_id !== null;
    }

    /**
     * Determine whether the user can view the media.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Media  $media
     * @return bool
     */
    public function view(User $user, Media $media): bool
    {
        // Users can only view media from their current tenant
        return $user->current_tenant_id === $media->tenant_id;
    }

    /**
     * Determine whether the user can create media.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Users must belong to a tenant to create media
        return $user->current_tenant_id !== null;
    }

    /**
     * Determine whether the user can update the media.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Media  $media
     * @return bool
     */
    public function update(User $user, Media $media): bool
    {
        // Users can only update media from their current tenant
        return $user->current_tenant_id === $media->tenant_id;
    }

    /**
     * Determine whether the user can delete the media.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Media  $media
     * @return bool
     */
    public function delete(User $user, Media $media): bool
    {
        // Users can only delete media from their current tenant
        return $user->current_tenant_id === $media->tenant_id;
    }

    /**
     * Determine whether the user can restore the media.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Media  $media
     * @return bool
     */
    public function restore(User $user, Media $media): bool
    {
        // Users can only restore media from their current tenant
        return $user->current_tenant_id === $media->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the media.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Media  $media
     * @return bool
     */
    public function forceDelete(User $user, Media $media): bool
    {
        // Users can only force delete media from their current tenant
        return $user->current_tenant_id === $media->tenant_id;
    }

    /**
     * Determine whether the user can download the media.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Media  $media
     * @return bool
     */
    public function download(User $user, Media $media): bool
    {
        // Users can only download media from their current tenant
        return $user->current_tenant_id === $media->tenant_id;
    }
}
