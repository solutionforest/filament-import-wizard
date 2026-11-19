@php
    $livewire = $getLivewire();
    $urls = $livewire->getChunkedUploadUrls();
    $accept = implode(',', array_map(fn (string $extension): string => '.'.$extension, \SolutionForest\FilamentImportWizard\Support\ChunkedUpload::EXTENSIONS));
@endphp

{{-- Sends the file in small chunks straight to the disk, so PHP's upload limits only apply per chunk. --}}
<div
    x-data="{
        urls: @js($urls),
        busy: false,
        over: false,
        percent: 0,
        name: null,
        error: null,
        async post(url, body, isJson) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', ...(isJson ? { 'Content-Type': 'application/json' } : {}) },
                body: isJson ? JSON.stringify(body) : body,
                credentials: 'same-origin',
            })
            const data = await response.json().catch(() => ({}))
            if (! response.ok) throw new Error(data.message || @js(__('filament-import-wizard::errors.upload_failed', ['status' => '__STATUS__'])).replace('__STATUS__', response.status))
            return data
        },
        async upload(file) {
            if (! file) return
            this.busy = true; this.error = null; this.percent = 0; this.name = file.name
            try {
                const started = await this.post(this.urls.start, { name: file.name, size: file.size }, true)
                for (let index = 0; index < started.chunks; index++) {
                    const form = new FormData()
                    form.append('id', started.id)
                    form.append('index', index)
                    form.append('chunk', file.slice(index * started.chunkSize, (index + 1) * started.chunkSize), 'chunk')
                    for (let attempt = 1; ; attempt++) {
                        try { await this.post(this.urls.chunk, form, false); break }
                        catch (e) { if (attempt >= 3) throw e; await new Promise(r => setTimeout(r, attempt * 1000)) }
                    }
                    this.percent = Math.round(((index + 1) / started.chunks) * 100)
                }
                const done = await this.post(this.urls.complete, { id: started.id }, true)
                await $wire.chunkedUploadFinished(done.path)
            } catch (e) {
                this.error = e.message
            } finally {
                this.busy = false
            }
        },
    }"
    class="iw-stack"
>
    <label class="iw-drop"
        x-bind:data-over="over"
        x-on:dragover.prevent="over = true"
        x-on:dragleave.prevent="over = false"
        x-on:drop.prevent="over = false; upload($event.dataTransfer.files[0])"
    >
        <input type="file" accept="{{ $accept }}" style="display: none" x-on:change="upload($event.target.files[0])" x-bind:disabled="busy">
        <span x-show="! busy">{{ __('filament-import-wizard::import.upload.drop') }}</span>
        <span x-show="busy" x-cloak><span x-text="name"></span> — <span x-text="percent"></span>%</span>
        <small>{{ __('filament-import-wizard::import.upload.helper') }}</small>
    </label>

    <div class="iw-progress" x-show="busy" x-cloak><div x-bind:style="'width:' + percent + '%'"></div></div>
    <p class="iw-bad" x-show="error" x-text="error" x-cloak></p>
    @error('data.file')<p class="iw-bad">{{ $message }}</p>@enderror
</div>
