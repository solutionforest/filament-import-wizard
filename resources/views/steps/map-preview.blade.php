@php($livewire = $getLivewire())

<x-filament::section :compact="true">
    @include('filament-import-wizard::steps.preview-table', [
        'columns' => $livewire->getMappedColumns(),
        'rows' => $livewire->getMapPreview(),
        'caption' => __('filament-import-wizard::import.preview.map'),
        'withStatus' => false,
    ])
</x-filament::section>
