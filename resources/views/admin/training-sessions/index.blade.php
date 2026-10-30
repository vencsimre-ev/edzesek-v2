@extends('layouts.app')
@section('title', 'Edzések | Edzések v2')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <h1 class="h3">Edzések</h1>
    <a class="btn btn-primary" href="{{ route('admin.training-sessions.create') }}">Új edzés</a>
</div>
<details class="mb-4" @if(filled($filters['status'] ?? null) || filled($filters['starts_from'] ?? null) || filled($filters['starts_before'] ?? null)) open @endif>
    <summary class="text-secondary">Szűrés</summary>
<form method="GET" class="row g-3 align-items-end mt-1">
    <div class="col-md-3">
        <label for="status" class="form-label">Állapot</label>
        <select id="status" name="status" class="form-select">
            <option value="">Összes állapot</option>
            @foreach(\App\Enums\TrainingSessionStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="starts_from" class="form-label">Kezdés ettől (beleértve)</label>
        <input id="starts_from" name="starts_from" type="datetime-local" step="60" class="form-control" value="{{ $filters['starts_from'] ?? '' }}">
    </div>
    <div class="col-md-3">
        <label for="starts_before" class="form-label">Kezdés eddig (kizárva)</label>
        <input id="starts_before" name="starts_before" type="datetime-local" step="60" class="form-control" value="{{ $filters['starts_before'] ?? '' }}">
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-outline-primary">Szűrés</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.training-sessions.index') }}">Összes</a>
    </div>
</form>
</details>
<div class="table-responsive">
<table class="table align-middle">
    <thead><tr><th>Időpont</th><th>Állapot</th><th>Művelet</th></tr></thead>
    <tbody>
    @forelse($sessions as $session)
        <tr>
            <td>
                <a href="{{ route('admin.training-sessions.show', $session) }}">{{ $session->starts_at->setTimezone('Europe/Budapest')->format('Y. m. d. H:i') }}–{{ $session->ends_at->setTimezone('Europe/Budapest')->format('H:i') }}</a>
                <div class="small text-secondary">{{ $session->title }}</div>
            </td>
            <td>{{ $session->status->label() }}</td>
            <td>
                @if($session->status === \App\Enums\TrainingSessionStatus::Scheduled)
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.training-sessions.show', $session) }}#cancel">Elmaradás jelzése</a>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="3">Nincs megjeleníthető edzés.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $sessions->links() }}
@endsection
