{{--
    The admin's own export runs: when, kind, filters, rows, phase (with progress while built) and
    the actions of the phase. Values come from the run record only — never from the file.

    @aidlc-unit data-export
    @aidlc-story US-data-export-sliced-build
    @aidlc-story US-data-export-download-retention
    @aidlc-story US-data-export-excel-encoding

    Variables: $jobs (paginator of ExportJob), $l (language prefix).
--}}
@php($phaseColors = ['ready' => 'green', 'building' => 'blue', 'queued' => 'amber', 'failed' => 'red', 'cancelled' => 'gray', 'expired' => 'gray', 'deleted' => 'gray'])
<x-gp247::table data-testid="data-export-index-table"
    :headers="[
        gp247_language_render($l.'col_created'),
        gp247_language_render($l.'col_kind'),
        gp247_language_render($l.'col_filters'),
        gp247_language_render($l.'col_rows'),
        gp247_language_render($l.'col_phase'),
        gp247_language_render($l.'col_actions'),
    ]"
    :empty="$jobs->isEmpty() ? gp247_language_render($l.'no_runs') : null">
    @foreach ($jobs as $run)
        <tr wire:key="export-{{ $run->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50" data-testid="data-export-index-row">
            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">{{ optional($run->created_at)->format('Y-m-d H:i') }}</td>
            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                {{ gp247_language_render($l.'kind.'.$run->kind) }}
                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render($l.'encoding.'.$run->encoding) }}</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                @forelse ((array) $run->filters as $name => $value)
                    <span class="mr-2 whitespace-nowrap">{{ gp247_language_render($l.'filter.'.$name) }}: {{ $value }}</span>
                @empty
                    {{ gp247_language_render($l.'all') }}
                @endforelse
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
                {{ number_format((int) $run->written_rows) }} / {{ number_format((int) $run->expected_rows) }}
                @if ((int) $run->replaced_cells > 0)
                    <span class="block text-xs text-amber-600" data-testid="data-export-index-replaced">{{ gp247_language_render($l.'replaced_cells', ['cells' => (string) $run->replaced_cells, 'row' => (string) $run->first_replaced_row]) }}</span>
                @endif
            </td>
            <td class="px-4 py-3">
                <x-gp247::badge :color="$phaseColors[$run->phase] ?? 'blue'" data-testid="data-export-index-phase">{{ gp247_language_render($l.'phase.'.$run->phase) }}</x-gp247::badge>
                @if ($run->phase === 'ready' && $run->expires_at)
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render($l.'expires_at', ['time' => $run->expires_at->format('Y-m-d H:i')]) }}</span>
                @endif
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <div class="flex flex-wrap gap-2">
                    @if ($run->isDownloadable())
                        <x-gp247::button size="sm" wire:click="download('{{ $run->id }}')" data-testid="data-export-download-{{ $run->id }}">{{ gp247_language_render($l.'download') }}</x-gp247::button>
                        <x-gp247::button size="sm" variant="danger" wire:click="deleteFile('{{ $run->id }}')" wire:confirm="{{ gp247_language_render($l.'delete_confirm') }}" data-testid="data-export-delete-{{ $run->id }}">{{ gp247_language_render($l.'delete') }}</x-gp247::button>
                    @elseif (in_array($run->phase, ['queued', 'building'], true))
                        <x-gp247::button size="sm" variant="secondary" wire:click="cancel('{{ $run->id }}')" data-testid="data-export-cancel-{{ $run->id }}">{{ gp247_language_render($l.'cancel') }}</x-gp247::button>
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
</x-gp247::table>
<div class="mt-4">{{ $jobs->links('gp247-admin::partials.pagination') }}</div>
