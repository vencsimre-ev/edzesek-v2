@extends('layouts.app')
@section('title', 'Edzés részletei | '.config('app.public_name'))
@section('content')
<h1 class="h3 mb-4">{{ $trainingSession->title }}</h1>
<dl class="row">
    <dt class="col-sm-3">Állapot</dt><dd class="col-sm-9">{{ $trainingSession->status->label() }}</dd>
    <dt class="col-sm-3">Helyszín</dt><dd class="col-sm-9">{{ $trainingSession->location }}</dd>
    <dt class="col-sm-3">Kezdés</dt><dd class="col-sm-9">{{ $trainingSession->starts_at->setTimezone(config('training.timezone'))->format('Y. m. d. H:i') }}</dd>
    <dt class="col-sm-3">Befejezés</dt><dd class="col-sm-9">{{ $trainingSession->ends_at->setTimezone(config('training.timezone'))->format('Y. m. d. H:i') }}</dd>
    @if($trainingSession->cancellation_reason !== null)
        <dt class="col-sm-3">Elmaradás indoka</dt><dd class="col-sm-9" style="white-space: pre-wrap">{{ $trainingSession->cancellation_reason }}</dd>
    @endif
</dl>
<div class="d-flex gap-2 mb-4">
    @if($trainingSession->status === \App\Enums\TrainingSessionStatus::Scheduled)
        <a class="btn btn-primary" href="{{ route('admin.training-sessions.edit', $trainingSession) }}">Szerkesztés</a>
    @endif
    <a class="btn btn-outline-secondary" href="{{ route('admin.training-sessions.index') }}">Vissza a listához</a>
</div>
<section id="attendances" class="mb-4 border-top pt-4">
    <h2 class="h5">Résztvevők ({{ $activeAttendanceCount }} fő)</h2>
    <ul class="list-group mb-3">
        @forelse($attendances as $attendance)
            <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-3">
                <span>
                    {{ $attendance->participant->name }}
                    <span class="small text-secondary">
                        #{{ $attendance->participant_id }}
                        @if($attendance->participant->trashed())
                            · Archivált
                        @endif
                    </span>
                </span>
                @if($attendance->withdrawn_at)
                    <span class="small text-secondary">Visszavonva: {{ $attendance->withdrawn_at->setTimezone(config('training.timezone'))->format('Y. m. d. H:i') }}</span>
                @else
                    <form method="POST" action="{{ route('admin.training-sessions.attendances.destroy', [$trainingSession, $attendance]) }}" data-attendance-remove>
                        @csrf @method('DELETE')
                        <input type="hidden" name="participant_picker_open" value="{{ session('participant_picker_open', $participantSearch !== '' || $errors->any()) ? '1' : '0' }}">
                        <input type="hidden" name="participant_search" value="{{ $participantSearch }}">
                        <button class="btn btn-outline-danger btn-sm">Részvétel eltávolítása</button>
                    </form>
                @endif
            </li>
        @empty
            <li class="list-group-item text-secondary">Még nincs rögzített résztvevő.</li>
        @endforelse
    </ul>
    {{ $attendances->links() }}
    @if($participants !== null)
        <details id="participant-picker" @if(session('participant_picker_open', $participantSearch !== '' || $errors->any())) open @endif class="mb-3">
            <summary>Meglévő résztvevő hozzáadása</summary>
            <form method="GET" action="{{ route('admin.training-sessions.show', $trainingSession) }}#attendances" class="d-flex gap-2 my-3">
                <label for="participant_search" class="visually-hidden">Résztvevő neve</label>
                <input id="participant_search" name="participant_search" type="search" maxlength="255" class="form-control" placeholder="Keresés név alapján" value="{{ $participantSearch }}">
                <button class="btn btn-outline-primary">Keresés</button>
            </form>
            <ul class="list-group mb-3">
                @forelse($participants as $participant)
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                        <span>{{ $participant->name }} <span class="small text-secondary">#{{ $participant->id }}</span></span>
                        <form method="POST" action="{{ route('admin.training-sessions.attendances.store', $trainingSession) }}">
                            @csrf
                            <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                            <input type="hidden" name="participant_search" value="{{ $participantSearch }}">
                            <button class="btn btn-outline-primary btn-sm">Hozzáadás</button>
                        </form>
                    </li>
                @empty
                    <li class="list-group-item text-secondary">Nincs választható résztvevő.</li>
                @endforelse
            </ul>
            {{ $participants->fragment('attendances')->links() }}
        </details>
        <details @if($errors->hasAny(['name', 'submission_id'])) open @endif>
            <summary>Új vendég hozzáadása</summary>
            <form method="POST" action="{{ route('admin.training-sessions.attendances.store', $trainingSession) }}" class="mt-3">
                @csrf
                <input type="hidden" name="submission_id" value="{{ old('submission_id', $guestSubmissionId) }}">
                <label for="guest_name" class="form-label">Vendég neve</label>
                <div class="d-flex gap-2">
                    <input id="guest_name" name="name" class="form-control" maxlength="255" required value="{{ old('name') }}">
                    <button class="btn btn-outline-primary text-nowrap">Vendég hozzáadása</button>
                </div>
            </form>
        </details>
    @else
        <p class="text-secondary">Elmaradt edzéshez nem adható részvétel.</p>
    @endif
</section>
@if($trainingSession->status === \App\Enums\TrainingSessionStatus::Scheduled)
    <div id="cancel" class="border-top pt-4">
        <h2 class="h5">Elmarad az edzés?</h2>
        <form method="POST" action="{{ route('admin.training-sessions.cancel', $trainingSession) }}" class="mb-4">
            @csrf
            <div class="mb-3">
                <label for="cancellation_reason" class="form-label">Indok (nem kötelező)</label>
                <textarea id="cancellation_reason" name="cancellation_reason" class="form-control" rows="2" maxlength="2000">{{ old('cancellation_reason') }}</textarea>
            </div>
            <button class="btn btn-outline-danger" @disabled($activeAttendanceCount > 0)>Elmaradást jelzek</button>
        </form>
        <details>
            <summary class="text-secondary">Megtartott edzés lezárása</summary>
            <form method="POST" action="{{ route('admin.training-sessions.complete', $trainingSession) }}" class="mt-3">
                @csrf
                <p class="small text-secondary">A befejezési időpont után rögzíthető. Lezárás után az edzés adatai nem szerkeszthetők.</p>
                <button class="btn btn-outline-success btn-sm">Megtartottként rögzítem</button>
            </form>
        </details>
    </div>
@endif
@if($activeAttendanceCount > 0)
    <p class="text-secondary">Elmaradás jelzése előtt rendezd a névsort: aktív részvételek mellett nem végezhető el.</p>
@endif
@if($attendances->total() > 0)
    <p class="text-secondary">Az edzés a részvételi előzmények megőrzése miatt nem törölhető.</p>
@endif
<details class="mt-4 border-top pt-3">
    <summary class="text-secondary">Végleges törlés</summary>
    <form method="POST" action="{{ route('admin.training-sessions.destroy', $trainingSession) }}" class="mt-3">
        @csrf
        @method('DELETE')
        <p class="small text-secondary">Az edzés véglegesen törlődik, nem állítható vissza. Elmaradt edzés megőrzéséhez az elmaradást jelezd.</p>
        <div class="form-check mb-3">
            <input id="confirm_delete" name="confirm_delete" type="checkbox" value="1" class="form-check-input" required>
            <label for="confirm_delete" class="form-check-label">Véglegesen törölni szeretném ezt az edzést.</label>
        </div>
        <button class="btn btn-outline-danger btn-sm" @disabled($attendances->total() > 0)>Edzés végleges törlése</button>
    </form>
</details>
@endsection
