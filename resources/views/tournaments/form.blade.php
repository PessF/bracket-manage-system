@extends('layouts.app')
@php
    $editing = $tournament->exists;
    $structureLocked = $editing && !in_array($tournament->status, [App\Enums\TournamentStatus::DRAFT, App\Enums\TournamentStatus::READY], true);
    $selectedFormat = old('format', $tournament->format?->value ?? App\Enums\TournamentFormat::RANKING->value);
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

<section class="card"><div class="actions" style="justify-content:space-between"><div><h2 style="margin-bottom:2px">{{ __('ui.competition_format') }}</h2><div class="muted">{{ __('ui.competition_format_help') }}</div></div>@if($structureLocked)<span class="badge">{{ __('ui.locked_after_start') }}</span>@endif</div>
@if($structureLocked)<div class="alert" style="background:#f8fafc;border-color:#e2e8f0;color:#475569;margin-top:15px">{{ __('ui.locked_help') }}</div>@endif
<div class="form-grid" style="margin-top:16px">
<div class="field"><label for="format">{{ __('ui.format') }}</label><select id="format" name="format" required @disabled($structureLocked)>@foreach(App\Enums\TournamentFormat::cases() as $format)<option value="{{ $format->value }}" @selected($selectedFormat === $format->value)>{{ __('ui.format_labels.'.$format->value) }}</option>@endforeach</select></div>
<div class="field"><label for="seeding_method">{{ __('ui.seeding_method') }}</label><select id="seeding_method" name="seeding_method" required @disabled($structureLocked)>@foreach(App\Enums\SeedingMethod::cases() as $method)<option value="{{ $method->value }}" @selected(old('seeding_method', $tournament->seeding_method?->value ?? 'REGISTRATION_ORDER') === $method->value)>{{ __('ui.seeding_labels.'.$method->value) }}</option>@endforeach</select></div>

<div class="format-config-panel full" data-format-panel="RANKING" @if($selectedFormat !== 'RANKING') hidden @endif>
    <div class="format-config-head"><span class="format-config-icon">#</span><div><strong>{{ __('ui.ranking_settings') }}</strong><span>{{ __('ui.ranking_format_help') }}</span></div></div>
    <div class="format-settings-grid">
        <div class="field"><label for="ranking_attempts">{{ __('ui.ranking_attempts') }}</label><input id="ranking_attempts" type="number" min="1" max="20" name="ranking_attempts" value="{{ old('ranking_attempts', $tournament->ranking_config['attempts'] ?? 3) }}" @disabled($structureLocked)></div>
        <div class="field"><label for="ranking_comparator">{{ __('ui.ranking_comparator') }}</label><select id="ranking_comparator" name="ranking_comparator" @disabled($structureLocked)><option value="BEST_SCORE_HIGHER" @selected(old('ranking_comparator', $tournament->ranking_config['comparator'] ?? '') === 'BEST_SCORE_HIGHER')>{{ __('ui.higher_score_wins') }}</option><option value="BEST_TIME_LOWER" @selected(old('ranking_comparator', $tournament->ranking_config['comparator'] ?? '') === 'BEST_TIME_LOWER')>{{ __('ui.lower_time_wins') }}</option></select></div>
    </div>
</div>

<div class="format-config-panel full" data-format-panel="ROUND_ROBIN" @if($selectedFormat !== 'ROUND_ROBIN') hidden @endif>
    <div class="format-config-head"><span class="format-config-icon">↻</span><div><strong>{{ __('ui.round_robin_settings') }}</strong><span>{{ __('ui.round_robin_format_help') }}</span></div></div>
    <div class="format-settings-grid three">
        <div class="field"><label for="win_points">{{ __('ui.rr_win_points') }}</label><input id="win_points" type="number" min="0" max="100" name="win_points" value="{{ old('win_points', $tournament->round_robin_config['win_points'] ?? 3) }}" @disabled($structureLocked)></div>
        <div class="field"><label for="draw_points">{{ __('ui.draw_points') }}</label><input id="draw_points" type="number" min="0" max="100" name="draw_points" value="{{ old('draw_points', $tournament->round_robin_config['draw_points'] ?? 1) }}" @disabled($structureLocked)></div>
        <div class="field"><label for="loss_points">{{ __('ui.loss_points') }}</label><input id="loss_points" type="number" min="0" max="100" name="loss_points" value="{{ old('loss_points', $tournament->round_robin_config['loss_points'] ?? 0) }}" @disabled($structureLocked)></div>
    </div>
</div>

<div class="format-config-panel full compact" data-format-panel="SINGLE_ELIMINATION" @if($selectedFormat !== 'SINGLE_ELIMINATION') hidden @endif>
    <div class="format-config-head"><span class="format-config-icon">1×</span><div><strong>{{ __('ui.single_elimination_settings') }}</strong><span>{{ __('ui.single_elimination_format_help') }}</span></div></div>
</div>

<div class="format-config-panel full compact" data-format-panel="DOUBLE_ELIMINATION" @if($selectedFormat !== 'DOUBLE_ELIMINATION') hidden @endif>
    <div class="format-config-head"><span class="format-config-icon">2×</span><div><strong>{{ __('ui.double_elimination_settings') }}</strong><span>{{ __('ui.double_elimination_format_help') }}</span></div></div>
</div>
</div></section>

<div class="actions"><button class="btn">{{ $editing ? __('ui.save_settings') : __('ui.create') }}</button><a class="btn secondary" href="{{ $editing ? route('tournaments.show', $tournament) : route('tournaments.index') }}">{{ __('ui.cancel') }}</a></div>
</form>

@if($editing)
<section class="card" style="margin-top:34px;border-color:#fecaca"><h2 style="color:#991b1b">{{ __('ui.danger_zone') }}</h2><div class="actions" style="justify-content:space-between"><div><strong>{{ __('ui.delete_competition') }}</strong><div class="muted">{{ __('ui.delete_competition_help') }}</div></div><form method="post" action="{{ route('tournaments.destroy', $tournament) }}" data-confirm="{{ __('ui.delete_competition_confirm', ['name' => $tournament->name]) }}">@csrf @method('DELETE')<button class="btn danger">{{ __('ui.delete_button') }}</button></form></div></section>
@endif
@endsection

@push('styles')
<style>
    .format-config-panel { padding: 15px; border: 1px solid #dbe3ee; border-radius: 9px; background: #f8fafc; }
    .format-config-panel[hidden] { display: none; }
    .format-config-panel.compact { padding: 14px 15px; }
    .format-config-head { display: flex; gap: 11px; align-items: flex-start; }
    .format-config-head > div { display: flex; flex-direction: column; min-width: 0; }
    .format-config-head strong { font-size: 13px; }
    .format-config-head span:not(.format-config-icon) { margin-top: 1px; color: var(--muted); font-size: 12px; }
    .format-config-icon { display: inline-flex; flex: 0 0 auto; align-items: center; justify-content: center; width: 30px; height: 30px; border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; color: #475569; font-size: 12px; font-weight: 800; }
    .format-settings-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; margin-top: 14px; }
    .format-settings-grid.three { grid-template-columns: repeat(3,minmax(0,1fr)); }
    .format-settings-grid .field { margin: 0; }
    @media (max-width: 680px) { .format-settings-grid, .format-settings-grid.three { grid-template-columns: 1fr; } }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const format = document.getElementById('format');
    const panels = Array.from(document.querySelectorAll('[data-format-panel]'));
    const structureLocked = @js($structureLocked);
    if (!format || !panels.length) return;

    const updateFormatFields = () => {
        panels.forEach((panel) => {
            const active = panel.dataset.formatPanel === format.value;
            panel.hidden = !active;
            panel.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = structureLocked || !active;
                if (control.matches('select')) control.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    };

    format.addEventListener('change', updateFormatFields);
    updateFormatFields();
})();
</script>
@endpush
