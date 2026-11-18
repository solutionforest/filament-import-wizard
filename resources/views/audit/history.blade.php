<div style="font-size: .875rem">
    <p><strong>{{ $audit->file_name }}</strong> · {{ class_basename($audit->importer) }}</p>
    <p>SHA-256: <code style="word-break: break-all">{{ $audit->checksum ?? '—' }}</code></p>
    <p>{{ number_format($audit->total_rows) }} rows · {{ number_format($audit->invalid_rows) }} with problems
        @if ($audit->completed_at) · {{ number_format($audit->successful_rows) }} imported, {{ number_format($audit->failed_rows) }} failed @endif
    </p>
    <ol style="margin-top: 1rem; list-style: none; padding: 0">
        @foreach ($audit->events as $event)
            <li style="padding: .375rem 0; border-top: 1px solid var(--gray-100)">
                <strong>{{ str_replace('_', ' ', $event->to_status) }}</strong>
                <span style="color: var(--gray-500)">{{ $event->created_at?->toDayDateTimeString() }}@if ($event->user_id) · user #{{ $event->user_id }}@endif</span>
                @if ($event->note)<div>{{ $event->note }}</div>@endif
            </li>
        @endforeach
    </ol>
</div>
