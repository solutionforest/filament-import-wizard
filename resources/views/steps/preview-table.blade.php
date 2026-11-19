{{-- Expects: $columns (name => label), $rows (list of {row, values, errors}), $caption, $withStatus --}}
<div class="iw-table-wrap">
    <table class="iw-table">
        <caption>{{ $caption }}</caption>
        <thead>
            <tr>
                @if ($withStatus)<th class="iw-status"></th><th>#</th>@endif
                @foreach ($columns as $label)<th>{{ $label }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr data-bad="{{ $row['errors'] === [] ? 'false' : 'true' }}">
                    @if ($withStatus)
                        <td class="iw-status" title="{{ $row['errors'] === [] ? '' : collect($row['errors'])->flatten()->implode(' ') }}">{{ $row['errors'] === [] ? '✓' : '✕' }}</td>
                        <td>{{ $row['row'] }}</td>
                    @endif
                    @foreach ($columns as $name => $label)
                        <td @if (isset($row['errors'][$name])) data-error="true" title="{{ implode(' ', $row['errors'][$name]) }}" @endif>{{ $row['values'][$name] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + ($withStatus ? 2 : 0) }}">{{ __('filament-import-wizard::import.preview.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
