@props(['status'])
<span {{ $attributes->merge(['class' => 'tag '.match (\Illuminate\Support\enum_value($status)) {
    'published', 'approved' => 'tag-accent',
    'rejected', 'unpublished', 'changes_requested' => 'tag-outline',
    default => 'tag-neutral',
}]) }}>{{ __('questions.status.'.\Illuminate\Support\enum_value($status)) }}</span>
