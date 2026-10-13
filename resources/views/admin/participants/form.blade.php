@extends('layouts.app')
@section('title', 'Résztvevő adatai | Edzések v2')
@section('content')
<h1 class="h3 mb-4">{{ $participant->exists ? 'Résztvevő szerkesztése' : 'Új vendég résztvevő' }}</h1>
<p>A résztvevő adatai különállóak; a bejelentkezési fiók adatai és jogosultságai nem változnak.</p>
<form method="POST" action="{{ $participant->exists ? route('admin.participants.update', $participant) : route('admin.participants.store') }}">
    @csrf
    @if($participant->exists) @method('PATCH') @endif
    <div class="mb-3"><label for="name" class="form-label">Név *</label><input id="name" name="name" class="form-control" required maxlength="255" value="{{ old('name', $participant->name) }}"></div>
    <div class="mb-3"><label for="email" class="form-label">Kapcsolattartási email</label><input id="email" name="email" type="email" class="form-control" maxlength="255" value="{{ old('email', $participant->email) }}" aria-describedby="email-help"><div id="email-help" class="form-text">Regisztrációkor automatikusan kitöltjük. Módosítása nem változtatja meg a bejelentkezési emailcímet.</div></div>
    <div class="mb-3"><label for="phone" class="form-label">Telefonszám</label><input id="phone" name="phone" type="tel" class="form-control" maxlength="50" value="{{ old('phone', $participant->phone) }}"></div>
    <div class="mb-3"><label for="notes" class="form-label">Belső megjegyzés (csak admin)</label><textarea id="notes" name="notes" class="form-control" rows="4" maxlength="10000">{{ old('notes', $participant->notes) }}</textarea></div>
    <button class="btn btn-primary">Mentés</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.participants.index') }}">Vissza a listához</a>
</form>
@endsection
