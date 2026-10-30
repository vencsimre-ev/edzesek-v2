@extends('layouts.app')
@section('title', 'Edzés részletei | Edzések v2')
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
@if($trainingSession->status === \App\Enums\TrainingSessionStatus::Scheduled)
    <div id="cancel" class="border-top pt-4">
        <h2 class="h5">Elmarad az edzés?</h2>
        <form method="POST" action="{{ route('admin.training-sessions.cancel', $trainingSession) }}" class="mb-4">
            @csrf
            <div class="mb-3">
                <label for="cancellation_reason" class="form-label">Indok (nem kötelező)</label>
                <textarea id="cancellation_reason" name="cancellation_reason" class="form-control" rows="2" maxlength="2000">{{ old('cancellation_reason') }}</textarea>
            </div>
            <button class="btn btn-outline-danger">Elmaradást jelzek</button>
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
        <button class="btn btn-outline-danger btn-sm">Edzés végleges törlése</button>
    </form>
</details>
@endsection
