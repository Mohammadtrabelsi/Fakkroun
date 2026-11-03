<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Tags\Tag as SpatieTag;

/**
 * Application tag model (configured in config/tags.php). Names and slugs are
 * translatable, like the rest of the content; tags are always created in the
 * default locale and can be translated from the admin tag screen.
 */
class Tag extends SpatieTag
{
    use LogsActivity;

    /**
     * Locale tags are looked up and created in. Spatie uses the request
     * locale, which would split one tag into several depending on the
     * editor's language; the fallback locale keeps a single canonical name.
     */
    public static function getLocale(): string
    {
        return (string) config('app.fallback_locale', 'en');
    }

    /**
     * @return MorphToMany<Post, $this>
     */
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug'])
            ->logOnlyDirty()
            ->useLogName('tags');
    }
}
