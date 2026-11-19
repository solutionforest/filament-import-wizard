<div style="font-size: .875rem">
    <p><strong>{{ $audit->file_name }}</strong> · {{ class_basename($audit->importer) }}</p>
    <p>{{ __('filament-import-wizard::audit.history.checksum') }}: <code style="word-break: break-all">{{ $audit->checksum ?? '—' }}</code></p>
    <p>{{ __('filament-import-wizard::audit.history.summary', ['rows' => \Illuminate\Support\Number::format($audit->total_rows), 'problems' => \Illuminate\Support\Number::format($audit->invalid_rows)]) }}
        @if ($audit->completed_at) · {{ __('filament-import-wizard::audit.history.result', ['imported' => \Illuminate\Support\Number::format($audit->successful_rows), 'failed' => \Illuminate\Support\Number::format($audit->failed_rows)]) }} @endif
    </p>
    <ol style="margin-top: 1rem; list-style: none; padding: 0">
        @foreach ($audit->events as $event)
            <li style="padding: .375rem 0; border-top: 1px solid var(--gray-100)">
                <strong>{{ \SolutionForest\FilamentImportWizard\Models\AuditStatus::tryFrom($event->to_status)?->getLabel() ?? $event->to_status }}</strong>
                <span style="color: var(--gray-500)">{{ $event->created_at?->isoFormat('LLL') }}@if ($event->user_id) · {{ __('filament-import-wizard::audit.history.user', ['id' => $event->user_id]) }}@endif</span>
                @if ($event->note)<div>{{ $event->note }}</div>@endif
            </li>
        @endforeach
    </ol>
</div>
