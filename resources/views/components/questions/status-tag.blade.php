@props(['status'])
@php($value = $status instanceof \BackedEnum ? $status->value : (string) $status)
<span {{ $attributes->merge(['class' => 'tag '.match ($value) {
    'published', 'approved' => 'tag-accent',
    'rejected', 'unpublished' => 'tag-outline',
    default => 'tag-neutral',
}]) }}>{{ __('questions.status.'.$value) }}</span>
