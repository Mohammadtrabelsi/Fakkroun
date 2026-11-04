<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;

/**
 * Categories are shared taxonomy, so every editing ability is gated by the
 * `manage categories` permission alone. Moderation is scoped: see moderate().
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage categories');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('manage categories');
    }

    public function create(User $user): bool
    {
        return $user->can('manage categories');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('manage categories');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('manage categories');
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->can('manage categories');
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $user->can('manage categories');
    }

    /**
     * Review questions and translations in this category (spec §3.3).
     * Holders of `manage questions` (admins) moderate everything; moderators
     * only their assigned categories, which include those categories'
     * subcategories.
     */
    public function moderate(User $user, Category $category): bool
    {
        if ($user->can('manage questions')) {
            return true;
        }

        if (! $user->can('moderate questions')) {
            return false;
        }

        return in_array($category->id, app(CategoryService::class)->moderatedCategoryIds($user), true);
    }
}
