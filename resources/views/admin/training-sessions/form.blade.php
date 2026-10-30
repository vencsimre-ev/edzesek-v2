@extends('layouts.app')
@section('title', 'Edzés adatai | Edzések v2')
@section('content')
<h1 class="h3 mb-3">{{ $trainingSession->exists ? 'Edzés szerkesztése' : 'Új edzés' }}</h1>
<p class="mb-4">{{ $trainingSession->title }} · {{ $trainingSession->location }}</p>
<form method="POST" action="{{ $trainingSession->exists ? route('admin.training-sessions.update', $trainingSession) : route('admin.training-sessions.store') }}">
    @csrf
    @if($trainingSession->exists) @method('PATCH') @endif
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="starts_at" class="form-label">Kezdés</label>
            <input id="starts_at" name="starts_at" type="datetime-local" step="60" class="form-control" required value="{{ old('starts_at', $trainingSession->starts_at?->setTimezone(config('training.timezone'))->format('Y-m-d\TH:i')) }}">
        </div>
        <div class="col-md-6 mb-3">
            <label for="ends_at" class="form-label">Befejezés</label>
            <input id="ends_at" name="ends_at" type="datetime-local" step="60" class="form-control" required value="{{ old('ends_at', $trainingSession->ends_at?->setTimezone(config('training.timezone'))->format('Y-m-d\TH:i')) }}">
        </div>
    </div>
    <details class="mb-4" @if($errors->hasAny(['title', 'location', 'status'])) open @endif>
        <summary class="text-secondary">További adatok</summary>
        <div class="mt-3 mb-3">
            <label for="title" class="form-label">Cím</label>
            <input id="title" name="title" class="form-control" required maxlength="255" value="{{ old('title', $trainingSession->title) }}">
        </div>
        <div class="mb-3">
            <label for="location" class="form-label">Helyszín</label>
            <input id="location" name="location" class="form-control" required maxlength="255" value="{{ old('location', $trainingSession->location) }}">
        </div>
        @if(!$trainingSession->exists)
            <div class="mb-3">
                <label for="status" class="form-label">Állapot</label>
                <select id="status" name="status" class="form-select">
                    <option value="scheduled" @selected(old('status', 'scheduled') === 'scheduled')>Tervezett</option>
                    <option value="completed" @selected(old('status') === 'completed')>Megtartott (korábbi edzés)</option>
                </select>
            </div>
        @endif
    </details>
    <div class="d-flex gap-2">
        <button class="btn btn-primary">Mentés</button>
        <a class="btn btn-outline-secondary" href="{{ $trainingSession->exists ? route('admin.training-sessions.show', $trainingSession) : route('admin.training-sessions.index') }}">Mégse</a>
    </div>
</form>
@endsection
