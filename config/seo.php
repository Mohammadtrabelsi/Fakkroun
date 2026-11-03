<?php

/*
| Structured data (JSON-LD) published on the public pages by
| App\Services\StructuredDataService. The organisation's name and contact
| email come from Admin → Settings; these are the technical extras.
*/

return [

    'structured_data' => (bool) env('SEO_STRUCTURED_DATA', true),

    'organization' => [
        /* Logo path relative to public/ (or an absolute URL). */
        'logo' => env('SEO_ORGANIZATION_LOGO', 'images/favicon_io/android-chrome-512x512.png'),

        /* Official profiles (comma-separated URLs), e.g. GitHub, LinkedIn. */
        'same_as' => array_values(array_filter(array_map('trim', explode(',', (string) env('SEO_SAME_AS', env('GITHUB_URL', '')))))),
    ],

];
