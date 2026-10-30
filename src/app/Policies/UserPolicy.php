<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Kebijakan akses pengelolaan pengguna (KF-02):
 * hanya Admin yang dapat melihat, membuat, mengubah, dan
 * menghapus akun pengguna serta menetapkan hak akses.
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Pengguna tidak dapat menghapus akunnya sendiri, dan admin
     * terakhir tidak dapat dihapus agar sistem tidak kehilangan
     * satu-satunya pengelola.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->isAdmin() || $user->id === $model->id) {
            return false;
        }

        if ($model->isAdmin() && User::where('role', UserRole::Admin)->count() <= 1) {
            return false;
        }

        return true;
    }
}
