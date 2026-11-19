@php
    $livewire = $getLivewire();
    $steps = array_values(__('filament-import-wizard::import.steps'));
@endphp

{{-- Self-contained styles: a package's views are not scanned by the app's Tailwind build. --}}
<style>
    .iw-steps { display: flex; flex-wrap: wrap; align-items: center; gap: 1.25rem; list-style: none; margin: 0; padding: 0; font-size: .875rem; }
    .iw-step { display: flex; align-items: center; gap: .5rem; color: var(--gray-500); }
    .iw-step[data-state="done"] { color: var(--gray-950); }
    .iw-step[data-state="current"] { color: var(--primary-600); font-weight: 600; }
    .dark .iw-step[data-state="done"] { color: #fff; }
    .iw-step-number { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: .375rem; font-size: .75rem; background: var(--gray-100); color: var(--gray-600); }
    .dark .iw-step-number { background: rgba(255,255,255,.1); color: #fff; }
    .iw-step[data-state="current"] .iw-step-number { background: var(--primary-600); color: #fff; }
    .iw-sep { color: var(--gray-400); }
    .iw-progress { height: .5rem; overflow: hidden; border-radius: 9999px; background: var(--gray-100); margin-top: .5rem; }
    .dark .iw-progress { background: rgba(255,255,255,.1); }
    .iw-progress > div { height: 100%; border-radius: 9999px; background: var(--primary-600); transition: width .3s; }
    .iw-row { display: flex; justify-content: space-between; align-items: center; font-size: .875rem; font-weight: 500; }
    .iw-ok { color: var(--success-600); font-size: .875rem; margin-top: .75rem; }
    .iw-bad { color: var(--danger-600); font-size: .875rem; }
    .iw-errors { list-style: none; margin: 0; padding: 0; font-size: .875rem; }
    .iw-errors li { padding: .5rem 0; border-top: 1px solid var(--gray-100); }
    .iw-errors li:first-child { border-top: 0; }
    .iw-errors strong { margin-right: .5rem; }
    .iw-actions { display: flex; justify-content: flex-end; gap: .75rem; }
    .iw-drop { display: block; border: 2px dashed var(--gray-300); border-radius: .75rem; padding: 2rem 1rem; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; }
    .iw-drop:hover, .iw-drop[data-over="true"] { border-color: var(--primary-600); background: color-mix(in srgb, var(--primary-600) 6%, transparent); }
    .dark .iw-drop { border-color: rgba(255,255,255,.2); }
    .iw-drop small { display: block; margin-top: .25rem; color: var(--gray-500); }
    .iw-table-wrap { overflow-x: auto; }
    .iw-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .iw-table th { text-align: left; font-size: .75rem; letter-spacing: .03em; text-transform: uppercase; color: var(--gray-500); font-weight: 600; padding: .5rem .75rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    .iw-table td { padding: .5rem .75rem; border-bottom: 1px solid var(--gray-100); white-space: nowrap; max-width: 16rem; overflow: hidden; text-overflow: ellipsis; }
    .dark .iw-table th, .dark .iw-table td { border-color: rgba(255,255,255,.1); }
    .iw-table .iw-status { width: 2rem; text-align: center; font-weight: 700; }
    .iw-table tr[data-bad="true"] .iw-status { color: var(--danger-600); }
    .iw-table tr[data-bad="false"] .iw-status { color: var(--success-600); }
    .iw-table td[data-error="true"] { background: color-mix(in srgb, var(--danger-600) 10%, transparent); color: var(--danger-600); }
    .iw-table caption { text-align: left; padding: 0 .75rem .5rem; font-size: .875rem; font-weight: 500; }
    .iw-stack > * + * { margin-top: 1rem; }
</style>

<ol class="iw-steps">
    @foreach ($steps as $index => $label)
        @php($number = $index + 1)
        <li class="iw-step" data-state="{{ $livewire->step === $number ? 'current' : ($livewire->step > $number ? 'done' : 'todo') }}">
            <span class="iw-step-number">{{ $number }}</span>
            {{ $label }}
        </li>
        @unless ($loop->last)
            <li class="iw-sep" aria-hidden="true">›</li>
        @endunless
    @endforeach
</ol>
