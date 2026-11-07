<?php

namespace App\Services;

use App\Models\Partner;
use App\Services\Concerns\ManagesModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Single entry point for reading and writing Partner records.
 */
class PartnerService
{
    /** @use ManagesModel<Partner> */
    use ManagesModel;

    public const LOGO_DIRECTORY = 'partners';

    /**
     * Every partner in display order (admin listing).
     *
     * @return Collection<int, Partner>
     */
    public function ordered(): Collection
    {
        return $this->query()->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Partners shown on the public home page.
     *
     * @return Collection<int, Partner>
     */
    public function visible(): Collection
    {
        return $this->query()->visible()->get();
    }

    /**
     * Creates the partner when none is given, otherwise updates it. A new
     * logo replaces (and deletes) the previous file.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function savePartner(?Partner $partner, array $attributes, ?UploadedFile $logo = null): Partner
    {
        $partner ??= new Partner;
        $partner->fill($attributes);

        if ($logo) {
            $previous = $partner->logo_path;
            $partner->logo_path = $logo->storePublicly(self::LOGO_DIRECTORY, 'public');

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        $partner->save();

        return $partner;
    }

    /**
     * Deletes the partner together with its logo file.
     */
    public function delete(Partner $partner): void
    {
        if ($partner->logo_path) {
            Storage::disk('public')->delete($partner->logo_path);
        }

        $partner->delete();
    }

    protected function model(): string
    {
        return Partner::class;
    }
}
