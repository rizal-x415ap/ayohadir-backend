<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the media item.
     */
    public function view(User $user, Media $media): bool
    {
        if ($media->is_system) {
            return true;
        }

        return $user->id === $media->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can upload media for a wedding.
     */
    public function uploadForWedding(User $user, Wedding $wedding): bool
    {
        return $user->id === $wedding->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can manage global admin assets.
     */
    public function manageGlobalAssets(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update metadata of the media.
     */
    public function update(User $user, Media $media): bool
    {
        if ($media->is_system) {
            return $user->isAdmin();
        }

        return $user->id === $media->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the media.
     */
    public function delete(User $user, Media $media): bool
    {
        if ($media->is_system) {
            return $user->isAdmin();
        }

        return $user->id === $media->user_id || $user->isAdmin();
    }
}
