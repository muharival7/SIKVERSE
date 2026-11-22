@props([
    'kelompoks',
    'selectedGroups' => [],
    'id' => 'kelompok-filter',
    'label' => 'Kelompok',
    'helper' => 'Pilih satu atau beberapa kelompok.',
    'allowAll' => true,
    'emptyLabel' => 'Semua kelompok',
    'emptyBadge' => 'Semua',
    'emptyHelper' => null,
])

@php
    $selectedIds = collect($selectedGroups)
        ->map(static fn ($value): string => (string) $value)
        ->filter(static fn (string $value): bool => $value !== '')
        ->values()
        ->all();
    $selectedCount = count($selectedIds);
    $totalGroups = count($kelompoks);
    $summary = $selectedCount === 0 ? $emptyLabel : $selectedCount.' kelompok dipilih';
    $badge = $selectedCount === 0 ? $emptyBadge : (string) $selectedCount;
    $emptyHelperText = $emptyHelper ?? $helper;
    $helpText = $selectedCount === 0
        ? $emptyHelperText
        : $selectedCount.' dari '.$totalGroups.' kelompok dipilih.';
@endphp

<div {{ $attributes->merge(['class' => 'kelompok-multiselect']) }} data-kelompok-multiselect>
    <div class="form-label small fw-semibold mb-1" id="{{ $id }}-label">{{ $label }}</div>
    <details class="kelompok-multiselect-panel">
        <summary
            class="kelompok-multiselect-toggle form-select"
            id="{{ $id }}-toggle"
            aria-labelledby="{{ $id }}-label"
            aria-controls="{{ $id }}-menu"
        >
            <span
                class="kelompok-multiselect-summary"
                data-kelompok-summary
                data-empty-label="{{ $emptyLabel }}"
            >{{ $summary }}</span>
            <span
                class="kelompok-multiselect-count"
                data-kelompok-count
                data-empty-badge="{{ $emptyBadge }}"
            >{{ $badge }}</span>
        </summary>
        <div class="kelompok-multiselect-menu" id="{{ $id }}-menu">
            @if ($allowAll)
                <label class="form-check kelompok-option" for="{{ $id }}-all">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="kelompok_id[]"
                        id="{{ $id }}-all"
                        value=""
                        data-kelompok-all
                        @checked($selectedCount === 0)
                    >
                    <span class="form-check-label">Semua kelompok</span>
                </label>
                <div class="kelompok-multiselect-divider"></div>
            @endif
            @foreach ($kelompoks as $kelompok)
                <label class="form-check kelompok-option" for="{{ $id }}-{{ $kelompok->id }}">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="kelompok_id[]"
                        id="{{ $id }}-{{ $kelompok->id }}"
                        value="{{ $kelompok->id }}"
                        data-kelompok-option
                        data-label="{{ $kelompok->nama_kelompok }}"
                        @checked(in_array((string) $kelompok->id, $selectedIds, true))
                    >
                    <span class="form-check-label">{{ $kelompok->nama_kelompok }}</span>
                </label>
            @endforeach
        </div>
    </details>
    <div
        class="form-text kelompok-multiselect-help"
        data-kelompok-help
        data-helper-all="{{ $helper }}"
        data-helper-empty="{{ $emptyHelperText }}"
        data-total="{{ $totalGroups }}"
    >{{ $helpText }}</div>
</div>

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('Css/kelompok-multiselect.css') }}?v={{ filemtime(public_path('Css/kelompok-multiselect.css')) }}">
@endpushOnce

@pushOnce('scripts')
<script>
    document.addEventListener('change', function (event) {
        const target = event.target;

        if (! (target instanceof HTMLInputElement) || ! target.closest('[data-kelompok-multiselect]')) {
            return;
        }

        const root = target.closest('[data-kelompok-multiselect]');
        const allOption = root.querySelector('[data-kelompok-all]');
        let checkedOptions = Array.from(root.querySelectorAll('[data-kelompok-option]:checked'));

        if (target.matches('[data-kelompok-all]') && target.checked) {
            checkedOptions.forEach(function (option) {
                option.checked = false;
            });
            checkedOptions = [];
        } else if (checkedOptions.length > 0 && allOption) {
            allOption.checked = false;
        } else if (allOption) {
            allOption.checked = true;
        }

        const count = checkedOptions.length;
        const summary = root.querySelector('[data-kelompok-summary]');
        const countBadge = root.querySelector('[data-kelompok-count]');
        const help = root.querySelector('[data-kelompok-help]');
        const total = help ? help.dataset.total : '0';
        const emptyLabel = summary ? summary.dataset.emptyLabel : '';
        const emptyBadge = countBadge ? countBadge.dataset.emptyBadge : '';
        const emptyHelper = help ? help.dataset.helperEmpty : '';

        if (summary) {
            summary.textContent = count === 0 ? emptyLabel : count + ' kelompok dipilih';
        }

        if (countBadge) {
            countBadge.textContent = count === 0 ? emptyBadge : String(count);
        }

        if (help) {
            help.textContent = count === 0
                ? emptyHelper
                : count + ' dari ' + total + ' kelompok dipilih.';
        }
    });

    document.addEventListener('click', function (event) {
        document.querySelectorAll('[data-kelompok-multiselect] details[open]').forEach(function (panel) {
            if (! panel.contains(event.target)) {
                panel.open = false;
            }
        });
    });
</script>
@endpushOnce
