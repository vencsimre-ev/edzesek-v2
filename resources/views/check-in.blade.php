@extends('layouts.public')
@section('title', 'Edzésre jöttem | '.config('app.public_name'))
@section('content')
    <div class="landing-card public-form-card mx-auto text-center">
        <span class="landing-eyebrow">Fless!Buda · mászóedzés</span>
        <h1 class="h3 mb-4">Edzésre jöttem</h1>
        @if($selected)
            <div class="mb-4">
                <p class="fs-5 mb-1">{{ $selected->starts_at->setTimezone(config('training.timezone'))->format('Y. m. d. H:i') }}–{{ $selected->ends_at->setTimezone(config('training.timezone'))->format('H:i') }}</p>
                <p>{{ $selected->location }}</p>
            </div>
            <form method="GET" action="{{ route('check-in.index') }}" class="mb-4">
                <label for="training_session_id" class="form-label">Edzés</label>
                <div class="d-flex gap-2">
                    <select id="training_session_id" name="training_session_id" class="form-select">
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" @selected($session->id === $selected->id)>{{ $session->starts_at->setTimezone(config('training.timezone'))->format('Y. m. d. H:i') }}–{{ $session->ends_at->setTimezone(config('training.timezone'))->format('H:i') }} · {{ $session->location }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-secondary">Kiválasztás</button>
                </div>
            </form>
            @if($already_recorded)
                @if($withdrawn_at)
                    <p class="alert alert-secondary">Erre az edzésre visszavontad a részvételedet. Az eredeti rögzítés megmaradt.</p>
                @else
                    <p class="alert alert-success">Erre az edzésre már rögzítettük a részvételedet.</p>
                    @auth
                        <form method="POST" action="{{ route('check-in.withdraw') }}" class="mb-4">
                            @csrf
                            <input type="hidden" name="training_session_id" value="{{ $selected->id }}">
                            <button class="btn btn-outline-danger" type="submit">Mégsem veszek részt</button>
                        </form>
                    @endauth
                @endif
            @endif
            <form method="POST" action="{{ route('check-in.store') }}" id="check-in-form" data-already-recorded="{{ $already_recorded ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="training_session_id" value="{{ $selected->id }}">
                @auth
                    <p class="fs-5 fw-semibold">{{ $name }}</p>
                @else
                    <input type="hidden" name="submission_id" value="{{ old('submission_id', $submissionId) }}">
                    <div class="mb-3">
                        <label for="check-in-name" class="form-label fw-semibold">Név</label>
                        <input id="check-in-name" name="name" class="form-control" maxlength="255" autocomplete="name" required value="{{ old('name') }}">
                        <div class="form-text">Segíts, hogy megjegyezzelek! :)</div>
                    </div>
                @endauth
                <button class="btn btn-primary btn-lg" type="submit" @disabled($retry_after > 0 || $already_recorded)>Részt veszek!</button>
                <p class="small text-secondary mt-3" data-check-in-cooldown="{{ $retry_after }}" @if($retry_after === 0) hidden @endif>
                    Új beküldés <span data-check-in-seconds>{{ $retry_after }}</span> másodperc múlva.
                </p>
            </form>
        @else
            <p class="alert alert-info">Most nincs választható edzés. Kérj segítséget az edzőtől.</p>
        @endif
    </div>
@endsection
