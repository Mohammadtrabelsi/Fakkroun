{{--
    Renders a schema.org graph built by App\Services\StructuredDataService.
    The JSON is encoded with JSON_HEX_TAG, so content such as "</script>" in a
    post title cannot break out of the script element.
--}}
@props(['graph' => null])

@if ($graph && config('seo.structured_data', true))
    {!! $graph->toScript() !!}
@endif
