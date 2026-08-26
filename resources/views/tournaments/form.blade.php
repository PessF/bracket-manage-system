@extends('layouts.app')
@php
    $editing = $tournament->exists;
    $bracketPrepared = $editing && $tournament->matches()->exists();
    $structureLocked = $editing && ($bracketPrepared || !in_array($tournament->status, [App\Enums\TournamentStatus::DRAFT, App\Enums\TournamentStatus::READY], true));
    $grandFinalMatches = (string) old('grand_final_matches', $tournament->double_elimination_config['grand_final_matches'] ?? 2);
    $advancedConfig = $tournament->advanced_config ?? [];
    $selectedStructure = old('structure', $tournament->structure?->value ?? App\Enums\TournamentStructure::STANDARD->value);
    $selectedFormat = old('format', $selectedStructure === 'ADVANCED' ? ($advancedConfig['playoff_format'] ?? App\Enums\TournamentFormat::SINGLE_ELIMINATION->value) : ($tournament->format?->value ?? App\Enums\TournamentFormat::RANKING->value));
    $participantCount = $editing ? $tournament->participants()->count() : 0;
    $initialGroupCount = (int) old('advanced_group_count', $advancedConfig['group_count'] ?? 4);
    $savedGroupLimits = old('advanced_group_limits', $advancedConfig['group_limits'] ?? []);
    $suggestedGroupLimits = collect(range(1, 16))->map(function (int $order) use ($participantCount, $initialGroupCount, $savedGroupLimits) {
        if (array_key_exists($order - 1, $savedGroupLimits) && filled($savedGroupLimits[$order - 1])) {
            return (int) $savedGroupLimits[$order - 1];
        }
        if ($participantCount < 1 || $order > $initialGroupCount) {
            return '';
        }
        return intdiv($participantCount, $initialGroupCount) + ($order <= ($participantCount % $initialGroupCount) ? 1 : 0);
    })->all();
@endphp
@section('title', ($editing ? __('ui.title_settings') : __('ui.title_new_tournament')).' · EasyKids')
@section('content')
@if($editing)
<div class="page-head"><div><div class="actions" style="margin-bottom:4px"><h1 style="margin:0">{{ $tournament->name }}</h1><span class="badge {{ $tournament->status->value }}">{{ __('ui.tournament_status_labels.'.$tournament->status->value) }}</span></div><div class="muted">{{ __('ui.competition_settings') }}</div></div></div>
@include('tournaments._tabs')
@else
<div class="page-head"><div><h1>{{ __('ui.create_tournament') }}</h1><div class="muted">{{ __('ui.create_help') }}</div></div></div>
@endif

<form method="post" action="{{ $editing ? route('tournaments.update', $tournament) : route('tournaments.store') }}">@csrf @if($editing)@method('PUT')@endif
<section class="card"><h2>{{ __('ui.competition_information') }}</h2><div class="muted" style="margin:-9px 0 17px">{{ __('ui.competition_information_help') }}</div>
<div class="form-grid">
<div class="field"><label for="name">{{ __('ui.tournament_name') }}</label><input id="name" name="name" required maxlength="200" value="{{ old('name', $tournament->name) }}"></div>
<div class="field"><label for="competition">{{ __('ui.competition_event') }}</label><input id="competition" name="competition" required maxlength="200" value="{{ old('competition', $tournament->competition) }}"></div>
<div class="field"><label for="division">{{ __('ui.division') }}</label><input id="division" name="division" required maxlength="200" value="{{ old('division', $tournament->division) }}"></div>
<div class="field"><label for="competition_date">{{ __('ui.competition_date') }}</label><input id="competition_date" type="datetime-local" name="competition_date" value="{{ old('competition_date', $tournament->competition_date?->format('Y-m-d\TH:i')) }}"></div>
<div class="field full"><label for="venue">{{ __('ui.venue') }}</label><input id="venue" name="venue" maxlength="255" value="{{ old('venue', $tournament->venue) }}"></div>
<div class="field full"><label for="notes">{{ __('ui.notes') }}</label><textarea id="notes" name="notes">{{ old('notes', $tournament->notes) }}</textarea></div>
</div></section>

<section class="card"><div class="actions split-actions" style="justify-content:space-between"><div><h2 style="margin-bottom:2px">{{ __('ui.competition_format') }}</h2><div class="muted">{{ __('ui.competition_format_help') }}</div></div>@if($structureLocked)<span class="badge">{{ __('ui.locked_after_start') }}</span>@endif</div>
@if($structureLocked)<div class="alert neutral" style="margin-top:15px">{{ __('ui.locked_help') }}</div>@endif
<div class="form-grid" style="margin-top:16px">
<div class="field full"><label for="structure">{{ __('ui.tournament_structure') }}</label><select id="structure" name="structure" required @disabled($structureLocked)>@foreach(App\Enums\TournamentStructure::cases() as $structure)<option value="{{ $structure->value }}" @selected($selectedStructure === $structure->value)>{{ __('ui.structure_labels.'.$structure->value) }}</option>@endforeach</select><small>{{ __('ui.tournament_structure_help') }}</small></div>
<div class="field" data-standard-builder-field><label for="format">{{ __('ui.format') }}</label><select id="format" name="format" required @disabled($structureLocked)>@foreach(App\Enums\TournamentFormat::cases() as $format)<option value="{{ $format->value }}" @selected($selectedFormat === $format->value)>{{ __('ui.format_labels.'.$format->value) }}</option>@endforeach</select></div>
<div class="field"><label for="seeding_method">{{ __('ui.seeding_method') }}</label><select id="seeding_method" name="seeding_method" required @disabled($structureLocked)>@foreach(App\Enums\SeedingMethod::cases() as $method)<option value="{{ $method->value }}" @selected(old('seeding_method', $tournament->seeding_method?->value ?? 'REGISTRATION_ORDER') === $method->value)>{{ __('ui.seeding_labels.'.$method->value) }}</option>@endforeach</select></div>

<div class="format-config-panel full advanced-builder-panel" data-structure-panel="ADVANCED" @if($selectedStructure !== 'ADVANCED') hidden @endif>
    <div class="format-config-head"><span class="format-config-icon">AB</span><div><strong>{{ __('ui.advanced_builder') }}</strong><span>{{ __('ui.advanced_builder_help') }}</span></div></div>
    <div class="format-settings-grid three">
        <div class="field"><label for="advanced_group_count">{{ __('ui.advanced_group_count') }}</label><input id="advanced_group_count" type="number" min="1" max="16" name="advanced_group_count" value="{{ $initialGroupCount }}" data-participant-count="{{ $participantCount }}" @disabled($structureLocked)><small>{{ __('ui.advanced_group_count_help') }}</small></div>
        <div class="field"><label for="advanced_qualifiers_per_group">{{ __('ui.advanced_qualifiers_per_group') }}</label><input id="advanced_qualifiers_per_group" type="number" min="1" max="16" name="advanced_qualifiers_per_group" value="{{ old('advanced_qualifiers_per_group', $advancedConfig['qualifiers_per_group'] ?? 1) }}" @disabled($structureLocked)></div>
        <div class="field"><label for="advanced_group_format">{{ __('ui.advanced_group_format') }}</label><select id="advanced_group_format" name="advanced_group_format" @disabled($structureLocked)>@foreach(App\Enums\TournamentFormat::cases() as $format)<option value="{{ $format->value }}" @selected(old('advanced_group_format', $advancedConfig['group_format'] ?? App\Enums\TournamentFormat::ROUND_ROBIN->value) === $format->value)>{{ __('ui.format_labels.'.$format->value) }}</option>@endforeach</select></div>
        <div class="field"><label for="advanced_playoff_format">{{ __('ui.advanced_playoff_format') }}</label><select id="advanced_playoff_format" name="advanced_playoff_format" @disabled($structureLocked)>@foreach(App\Enums\TournamentFormat::cases() as $format)<option value="{{ $format->value }}" @selected(old('advanced_playoff_format', $advancedConfig['playoff_format'] ?? App\Enums\TournamentFormat::SINGLE_ELIMINATION->value) === $format->value)>{{ __('ui.format_labels.'.$format->value) }}</option>@endforeach</select></div>
        <label class="choice-card compact-choice"><input type="checkbox" name="advanced_third_place" value="1" @checked(old('advanced_third_place', $advancedConfig['third_place'] ?? false)) @disabled($structureLocked)><span><strong>{{ __('ui.advanced_third_place') }}</strong><small>{{ __('ui.advanced_third_place_help') }}</small></span></label>
    </div>
    <div class="group-limit-editor" data-group-limit-editor>
        <div class="group-limit-head"><div><strong>{{ __('ui.advanced_group_limits') }}</strong><span>{{ __('ui.advanced_group_limits_help') }}</span></div><button class="btn secondary tiny" type="button" data-balance-groups @disabled($structureLocked)>{{ __('ui.balance_groups') }}</button></div>
        <div class="group-limit-grid">
            @foreach(range(1, 16) as $order)
                <div class="field group-limit-field" data-group-limit-field="{{ $order }}" @if($order > $initialGroupCount) hidden @endif>
                    <label for="advanced_group_limit_{{ $order }}">{{ __('ui.group_label', ['letter' => chr(64 + $order)]) }}</label>
                    <input id="advanced_group_limit_{{ $order }}" type="number" min="1" max="64" name="advanced_group_limits[]" value="{{ $suggestedGroupLimits[$order - 1] }}" @disabled($structureLocked)>
                </div>
            @endforeach
        </div>
        <div class="muted group-limit-summary" data-group-limit-summary></div>
    </div>
</div>

<div class="format-config-panel full" data-format-panel="RANKING" @if($selectedStructure === 'ADVANCED' || $selectedFormat !== 'RANKING') hidden @endif>
    <div class="format-config-head"><span class="format-config-icon">#</span><div><strong>{{ __('ui.ranking_settings') }}</strong><span>{{ __('ui.ranking_format_help') }}</span></div></div>
    <div class="format-settings-grid">
        <div class="field"><label for="ranking_attempts">{{ __('ui.ranking_attempts') }}</label><input id="ranking_attempts" type="number" min="1" max="20" name="ranking_attempts" value="{{ old('ranking_attempts', $tournament->ranking_config['attempts'] ?? 2) }}" @disabled($structureLocked)></div>
        <div class="field"><label for="ranking_comparator">{{ __('ui.ranking_comparator') }}</label><select id="ranking_comparator" name="ranking_comparator" @disabled($structureLocked)><option value="BEST_SCORE_HIGHER" @selected(old('ranking_comparator', $tournament->ranking_config['comparator'] ?? '') === 'BEST_SCORE_HIGHER')>{{ __('ui.higher_score_wins') }}</option><option value="BEST_TIME_LOWER" @selected(old('ranking_comparator', $tournament->ranking_config['comparator'] ?? '') === 'BEST_TIME_LOWER')>{{ __('ui.lower_time_wins') }}</option></select></div>
    </div>
</div>

<div class="format-config-panel full" data-format-panel="ROUND_ROBIN" @if($selectedStructure === 'ADVANCED' || $selectedFormat !== 'ROUND_ROBIN') hidden @endif>
    <div class="format-config-head"><span class="format-config-icon">↻</span><div><strong>{{ __('ui.round_robin_settings') }}</strong><span>{{ __('ui.round_robin_format_help') }}</span></div></div>
</div>

<div class="format-config-panel full compact" data-format-panel="SINGLE_ELIMINATION" @if($selectedStructure === 'ADVANCED' || $selectedFormat !== 'SINGLE_ELIMINATION') hidden @endif>
    <div class="format-config-head"><span class="format-config-icon">1×</span><div><strong>{{ __('ui.single_elimination_settings') }}</strong><span>{{ __('ui.single_elimination_format_help') }}</span></div></div>
</div>

<div class="format-config-panel full" data-format-panel="DOUBLE_ELIMINATION" @if($selectedStructure === 'ADVANCED' || $selectedFormat !== 'DOUBLE_ELIMINATION') hidden @endif>
    <div class="format-config-head"><span class="format-config-icon">2×</span><div><strong>{{ __('ui.double_elimination_settings') }}</strong><span>{{ __('ui.double_elimination_format_help') }}</span></div></div>
    <fieldset class="choice-grid" style="border:0;padding:0" aria-label="{{ __('ui.grand_final_matches') }}">
        <label class="choice-card"><input type="radio" name="grand_final_matches" value="1" @checked($grandFinalMatches === '1') @disabled($structureLocked)><span><strong>{{ __('ui.grand_final_one_match') }}</strong><small>{{ __('ui.grand_final_one_match_help') }}</small></span></label>
        <label class="choice-card"><input type="radio" name="grand_final_matches" value="2" @checked($grandFinalMatches === '2') @disabled($structureLocked)><span><strong>{{ __('ui.grand_final_two_matches') }}</strong><small>{{ __('ui.grand_final_two_matches_help') }}</small></span></label>
    </fieldset>
</div>
</div></section>

<div class="actions"><button class="btn">{{ $editing ? __('ui.save_settings') : __('ui.create') }}</button><a class="btn secondary" href="{{ $editing ? route('tournaments.show', $tournament) : route('tournaments.index') }}">{{ __('ui.cancel') }}</a></div>
</form>

@if($editing)
<section class="card danger-card" style="margin-top:34px"><h2>{{ __('ui.danger_zone') }}</h2>
    @if($bracketPrepared && in_array($tournament->status, [App\Enums\TournamentStatus::READY, App\Enums\TournamentStatus::LIVE, App\Enums\TournamentStatus::COMPLETED], true))
    <div class="actions danger-row" style="justify-content:space-between">
        <div><strong>{{ __('ui.reset_bracket') }}</strong><div class="muted">{{ __('ui.reset_bracket_help') }}</div></div>
        <form method="post" action="{{ route('tournaments.reset-bracket', $tournament) }}" data-confirm="{{ __('ui.reset_bracket_confirm', ['name' => $tournament->name]) }}">@csrf<button class="btn secondary">{{ __('ui.reset_bracket_button') }}</button></form>
    </div>
    @endif
    <div class="actions danger-row" style="justify-content:space-between">
        <div><strong>{{ __('ui.delete_competition') }}</strong><div class="muted">{{ __('ui.delete_competition_help') }}</div></div>
        <form method="post" action="{{ route('tournaments.destroy', $tournament) }}" data-confirm="{{ __('ui.delete_competition_confirm', ['name' => $tournament->name]) }}">@csrf @method('DELETE')<button class="btn danger">{{ __('ui.delete_button') }}</button></form>
    </div>
</section>
@endif
@endsection

@push('styles')
<style>
    .format-config-panel { min-width:0; padding: 15px; border: 1px solid var(--line); border-radius: 7px; background: var(--soft); }
    .format-config-panel[hidden] { display: none; }
    .format-config-panel.compact { padding: 14px 15px; }
    .format-config-head { display: flex; gap: 11px; align-items: flex-start; }
    .format-config-head > div { display: flex; flex-direction: column; min-width: 0; }
    .format-config-head strong { font-size: 14px; }
    .format-config-head span:not(.format-config-icon) { margin-top: 2px; color: var(--muted); font-size: 13px; }
    .format-config-icon { display: inline-flex; flex: 0 0 auto; align-items: center; justify-content: center; width: 30px; height: 30px; border: 1px solid var(--line-strong); border-radius: 6px; background: var(--card); color: #b4c0cc; font-size: 12px; font-weight: 800; }
    .format-settings-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; margin-top: 14px; }
    .format-settings-grid.three { grid-template-columns: repeat(3,minmax(0,1fr)); }
    .format-settings-grid .field { margin: 0; }
    .choice-grid { display:grid; min-width:0; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin:15px 0 0; }
    .choice-card { display:flex; align-items:flex-start; gap:10px; min-height:76px; margin:0; padding:13px; border:1px solid var(--line-strong); border-radius:7px; background:var(--card); cursor:pointer; }
    .choice-card:has(input:checked) { border-color:#4d8db8; background:#132536; box-shadow:0 0 0 2px rgb(77 141 184 / .14); }
    .choice-card input { flex:0 0 auto; width:20px; height:20px; min-height:20px; margin:1px 0 0; }
    .choice-card span { display:flex; min-width:0; flex-direction:column; }
    .choice-card small { margin-top:3px; color:var(--muted); font-weight:500; }
    .compact-choice { min-height: 0; align-items: center; }
    .advanced-builder-panel { border-color: rgba(103,232,249,.38); background: linear-gradient(135deg, rgba(21,94,117,.2), rgba(15,23,42,.88)); }
    .group-limit-editor { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--line); }
    .group-limit-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .group-limit-head > div { display: flex; min-width: 0; flex-direction: column; }
    .group-limit-head span { margin-top: 2px; color: var(--muted); font-size: 13px; }
    .group-limit-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 10px; margin-top: 12px; }
    .group-limit-field[hidden] { display: none; }
    .group-limit-summary { margin-top: 9px; font-size: 13px; }
    .btn.tiny { min-height: 34px; padding: 7px 10px; font-size: 12px; }
    .danger-row { gap:14px; padding:12px 0; border-top:1px solid var(--line); }
    .danger-row:first-of-type { border-top:0; padding-top:0; }
    .danger-row:last-child { padding-bottom:0; }
    @media (max-width: 820px) { .format-settings-grid.three, .group-limit-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } }
    @media (max-width: 680px) { .format-settings-grid, .choice-grid, .group-limit-grid { grid-template-columns: 1fr; } .choice-card { min-height:86px; padding:16px; } .danger-row, .group-limit-head { align-items:flex-start; flex-direction:column; } }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const format = document.getElementById('format');
    const structure = document.getElementById('structure');
    const playoffFormat = document.getElementById('advanced_playoff_format');
    const groupCountInput = document.getElementById('advanced_group_count');
    const balanceGroupsButton = document.querySelector('[data-balance-groups]');
    const groupLimitFields = Array.from(document.querySelectorAll('[data-group-limit-field]'));
    const groupLimitSummary = document.querySelector('[data-group-limit-summary]');
    const standardFields = Array.from(document.querySelectorAll('[data-standard-builder-field]'));
    const panels = Array.from(document.querySelectorAll('[data-format-panel]'));
    const structurePanels = Array.from(document.querySelectorAll('[data-structure-panel]'));
    const structureLocked = @js($structureLocked);
    if (!format || !panels.length) return;

    const updateFormatFields = () => {
        const advancedActive = structure?.value === 'ADVANCED';
        panels.forEach((panel) => {
            const active = !advancedActive && panel.dataset.formatPanel === format.value;
            panel.hidden = !active;
            panel.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = structureLocked || !active;
                if (control.matches('select')) control.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    };

    const updateStructureFields = () => {
        const advancedActive = structure?.value === 'ADVANCED';
        standardFields.forEach((field) => {
            field.hidden = advancedActive;
        });
        if (advancedActive && playoffFormat && format) {
            format.value = playoffFormat.value;
        }
        structurePanels.forEach((panel) => {
            const active = structure && panel.dataset.structurePanel === structure.value;
            panel.hidden = !active;
            panel.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = structureLocked || !active;
                if (control.matches('select')) control.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
        updateFormatFields();
    };

    const visibleGroupLimitInputs = () => groupLimitFields
        .filter((field) => !field.hidden)
        .map((field) => field.querySelector('input'))
        .filter(Boolean);

    const updateGroupLimitSummary = () => {
        if (!groupLimitSummary) return;
        const participantCount = Number(groupCountInput?.dataset.participantCount || 0);
        const total = visibleGroupLimitInputs().reduce((sum, input) => sum + Number(input.value || 0), 0);
        groupLimitSummary.textContent = participantCount > 0
            ? @js(__('ui.group_limit_summary_with_participants')).replace(':total', total).replace(':participants', participantCount)
            : @js(__('ui.group_limit_summary_without_participants')).replace(':total', total);
    };

    const updateGroupLimitFields = () => {
        const groupCount = Math.max(1, Math.min(16, Number(groupCountInput?.value || 1)));
        groupLimitFields.forEach((field) => {
            const order = Number(field.dataset.groupLimitField || 0);
            const active = order <= groupCount;
            field.hidden = !active;
            const input = field.querySelector('input');
            if (input) input.disabled = structureLocked || !active;
        });
        updateGroupLimitSummary();
    };

    const balanceGroupLimits = () => {
        const groupCount = Math.max(1, Math.min(16, Number(groupCountInput?.value || 1)));
        const participantCount = Number(groupCountInput?.dataset.participantCount || 0);
        if (participantCount < 1) return;
        groupLimitFields.forEach((field) => {
            const order = Number(field.dataset.groupLimitField || 0);
            const input = field.querySelector('input');
            if (!input || order > groupCount) return;
            input.value = Math.floor(participantCount / groupCount) + (order <= (participantCount % groupCount) ? 1 : 0);
        });
        updateGroupLimitSummary();
    };

    format.addEventListener('change', updateFormatFields);
    groupCountInput?.addEventListener('input', updateGroupLimitFields);
    balanceGroupsButton?.addEventListener('click', balanceGroupLimits);
    groupLimitFields.forEach((field) => field.querySelector('input')?.addEventListener('input', updateGroupLimitSummary));
    playoffFormat?.addEventListener('change', () => {
        if (structure?.value === 'ADVANCED' && format) {
            format.value = playoffFormat.value;
        }
    });
    structure?.addEventListener('change', updateStructureFields);
    updateStructureFields();
    updateGroupLimitFields();
})();
</script>
@endpush
