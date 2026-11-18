@php
    $import = $getLivewire()->getImport();
    $finished = $import?->completed_at !== null;
    $percent = $import && $import->total_rows ? min(100, $import->processed_rows / $import->total_rows * 100) : 0;
@endphp

<div @unless ($finished || $getLivewire()->awaitingApproval) wire:poll.1s @endunless>
    <x-filament::section>
        @if ($getLivewire()->awaitingApproval)
            <p class="iw-row">{{ __('filament-import-wizard::import.approval.submitted') }}</p>
            <p style="margin-top: .5rem; font-size: .875rem">{{ __('filament-import-wizard::import.approval.hint') }}</p>
            <div class="iw-actions" style="margin-top: 1rem"><x-filament::button wire:click="startOver">{{ __('filament-import-wizard::import.start_over') }}</x-filament::button></div>
        @elseif ($import)
            <div class="iw-row">
                <span>
                    {{ $finished
                        ? __('filament-import-wizard::import.import.done', ['successful' => number_format($import->successful_rows), 'total' => number_format($import->total_rows)])
                        : __('filament-import-wizard::import.import.importing', ['processed' => number_format($import->processed_rows), 'total' => number_format($import->total_rows)]) }}
                </span>
                <span>{{ round($percent) }}%</span>
            </div>
            <div class="iw-progress"><div style="width: {{ $percent }}%"></div></div>
            @if ($finished && ($failed = $import->getFailedRowsCount()))
                <p class="iw-bad" style="margin-top: .75rem">{{ __('filament-import-wizard::import.import.failed', ['count' => number_format($failed)]) }}</p>
            @endif
            @if ($finished)
                <div class="iw-actions" style="margin-top: 1rem"><x-filament::button wire:click="startOver">{{ __('filament-import-wizard::import.start_over') }}</x-filament::button></div>
            @endif
        @endif
    </x-filament::section>
</div>
