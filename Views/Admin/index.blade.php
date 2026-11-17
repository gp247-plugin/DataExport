{{--
    Data Export — new export (kind, filters of that kind, encoding, row estimate) and the admin's
    own runs (progress while built, download / cancel / delete). While a run is waiting or being
    built the screen pulls one slice every 2 s (wire:poll) — no cron or queue needed.

    @aidlc-unit data-export
    @aidlc-story US-data-export-entity-csv
    @aidlc-story US-data-export-filters
    @aidlc-story US-data-export-excel-encoding
    @aidlc-story US-data-export-sliced-build
    @aidlc-story US-data-export-download-retention

    Variables: $kinds (kind => label), $jobs (paginator of ExportJob), $building (bool), $encodings (list), $options (FilterOptions::for()).
--}}
@php($l = 'Plugins/DataExport::lang.')
@php($inputCls = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100')
@php($fields = $options['fields'])
<div class="space-y-4" @if ($building) wire:poll.2s="dataExportTick" @endif data-testid="data-export-index">
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render($l.'subtitle') }}</p>

    @if (count($kinds) > 0)
        <x-gp247::card :title="gp247_language_render($l.'new_export')" :subtitle="gp247_language_render($l.'new_export_help')" data-testid="data-export-index-form">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block text-sm text-gray-700 dark:text-gray-200">
                    <span class="mb-1 block font-medium">{{ gp247_language_render($l.'field_kind') }}</span>
                    <select wire:model.live="kind" class="{{ $inputCls }}" data-testid="data-export-index-kind">
                        @foreach ($kinds as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('kind') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm text-gray-700 dark:text-gray-200">
                    <span class="mb-1 block font-medium">{{ gp247_language_render($l.'field_encoding') }}</span>
                    <select wire:model.live="encoding" class="{{ $inputCls }}" data-testid="data-export-index-encoding">
                        @foreach ($encodings as $code)
                            <option value="{{ $code }}">{{ gp247_language_render($l.'encoding.'.$code) }}</option>
                        @endforeach
                    </select>
                    @error('encoding') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                @if (in_array('store', $fields, true) && count($options['stores']) > 0)
                    <label class="block text-sm text-gray-700 dark:text-gray-200">
                        <span class="mb-1 block font-medium">{{ gp247_language_render($l.'field_store') }}</span>
                        <select wire:model.live="store" class="{{ $inputCls }}" data-testid="data-export-index-store">
                            <option value="">{{ gp247_language_render($l.'all') }}</option>
                            @foreach ($options['stores'] as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('store') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                @endif

                @if (in_array('status', $fields, true))
                    <label class="block text-sm text-gray-700 dark:text-gray-200">
                        <span class="mb-1 block font-medium">{{ gp247_language_render($l.'field_status') }}</span>
                        <select wire:model.live="status" class="{{ $inputCls }}" data-testid="data-export-index-status">
                            <option value="">{{ gp247_language_render($l.'all') }}</option>
                            @if ($kind === 'order')
                                @foreach ($options['statuses'] as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            @else
                                <option value="1">{{ gp247_language_render($l.'status_on') }}</option>
                                <option value="0">{{ gp247_language_render($l.'status_off') }}</option>
                            @endif
                        </select>
                        @error('status') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                @endif

                @if (in_array('payment_status', $fields, true))
                    <label class="block text-sm text-gray-700 dark:text-gray-200">
                        <span class="mb-1 block font-medium">{{ gp247_language_render($l.'field_payment_status') }}</span>
                        <select wire:model.live="paymentStatus" class="{{ $inputCls }}" data-testid="data-export-index-payment-status">
                            <option value="">{{ gp247_language_render($l.'all') }}</option>
                            @foreach ($options['payment'] as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_status') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                @endif

                @if (in_array('category', $fields, true))
                    <label class="block text-sm text-gray-700 dark:text-gray-200">
                        <span class="mb-1 block font-medium">{{ gp247_language_render($l.'field_category') }}</span>
                        <select wire:model.live="category" class="{{ $inputCls }}" data-testid="data-export-index-category">
                            <option value="">{{ gp247_language_render($l.'all') }}</option>
                            @foreach ($options['categories'] as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                @endif

                @if (in_array('date_from', $fields, true))
                    <label class="block text-sm text-gray-700 dark:text-gray-200">
                        <span class="mb-1 block font-medium">{{ gp247_language_render($l.'field_date_from') }}</span>
                        <input type="date" wire:model.live="dateFrom" class="{{ $inputCls }}" data-testid="data-export-index-date-from" />
                        @error('date_from') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm text-gray-700 dark:text-gray-200">
                        <span class="mb-1 block font-medium">{{ gp247_language_render($l.'field_date_to') }}</span>
                        <input type="date" wire:model.live="dateTo" class="{{ $inputCls }}" data-testid="data-export-index-date-to" />
                        @error('date_to') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-gp247::button variant="secondary" wire:click="estimateRows" data-testid="data-export-index-estimate">{{ gp247_language_render($l.'estimate') }}</x-gp247::button>
                <x-gp247::button wire:click="create" wire:loading.attr="disabled" data-testid="data-export-index-create">{{ gp247_language_render($l.'create') }}</x-gp247::button>
                @if ($estimate !== null)
                    <span class="text-sm text-gray-700 dark:text-gray-200" data-testid="data-export-index-estimate-result">{{ gp247_language_render($l.'estimate_result', ['rows' => number_format($estimate)]) }}</span>
                @endif
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render($l.'retention_hint', ['hours' => (string) config('Plugins/DataExport.retention_hours', 24)]) }}</p>
        </x-gp247::card>
    @else
        <x-gp247::alert type="info" data-testid="data-export-index-no-permission">{{ gp247_language_render($l.'no_permission') }}</x-gp247::alert>
    @endif

    <x-gp247::card :title="gp247_language_render($l.'runs')">
        @include('Plugins/DataExport::Admin.partials.jobs', ['jobs' => $jobs, 'l' => $l])
    </x-gp247::card>
</div>
