
{{-- Global toast container. Toasts are rendered client-side by
     resources/js/app.js in response to the `notify` browser event dispatched
     from Livewire components (see App\Support\Livewire\InteractsWithToasts). --}}
<div
    aria-live="polite"
    aria-atomic="true"
    data-toast-hub
    @if (session('status')) data-toast-flash="{{ session('status') }}" @endif
></div>
