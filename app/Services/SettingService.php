<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Concerns\ManagesModel;

class SettingService
{
    /** @use ManagesModel<Setting> */
    use ManagesModel;

    public function current(): Setting
    {
        return Setting::query()->firstOrCreate([], [
            'site_name' => ['en' => config('app.name'), 'ar' => config('app.name'), 'fr' => config('app.name')],
            'site_description' => ['en' => '', 'ar' => '', 'fr' => ''],
        ]);
    }

    public function dateFormat(): string
    {
        return $this->current()->date_format ?? 'M j, Y';
    }

    public function update(Setting $setting, array $data): Setting
    {
        $setting->fill($data);
        $setting->save();

        return $setting;
    }

    protected function model(): string
    {
        return Setting::class;
    }
}
