@extends('layouts.app')
@section('title', 'Résztvevő részletei | Edzések v2')
@section('content')
<h1 class="h3">{{ $participant->name }}</h1>
<dl class="row">
    <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $participant->email ?? '–' }}</dd>
    <dt class="col-sm-3">Telefon</dt><dd class="col-sm-9">{{ $participant->phone ?? '–' }}</dd>
    <dt class="col-sm-3">Belső megjegyzés</dt><dd class="col-sm-9" style="white-space: pre-wrap">{{ $participant->notes ?? '–' }}</dd>
    <dt class="col-sm-3">Bejelentkezési fiók</dt><dd class="col-sm-9">{{ $participant->user ? $participant->user->name.' ('.$participant->user->email.')' : 'Vendég, nincs kapcsolt fiók' }}</dd>
    <dt class="col-sm-3">Létrehozva</dt><dd class="col-sm-9">{{ $participant->created_at }}</dd>
    <dt class="col-sm-3">Módosítva</dt><dd class="col-sm-9">{{ $participant->updated_at }}</dd>
</dl>
<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn btn-primary" href="{{ route('admin.participants.edit', $participant) }}">Szerkesztés</a>
    @if(!$participant->user_id)
        <a class="btn btn-outline-primary" href="{{ route('admin.participants.link-user.create', $participant) }}">Student fiók összekapcsolása</a>
    @endif
    <a class="btn btn-outline-secondary" href="{{ route('admin.participants.index') }}">Vissza a listához</a>
</div>
<details class="mt-4">
    <summary class="text-secondary">Archiválás</summary>
    <form method="POST" action="{{ route('admin.participants.destroy', $participant) }}" class="mt-3">
        @csrf @method('DELETE')
        <p class="small text-secondary">A résztvevő kikerül a listából, de adatai megmaradnak. A kapcsolt bejelentkezési fiók nem törlődik.</p>
        <div class="form-check mb-2">
            <input id="confirm-archive" type="checkbox" class="form-check-input" required>
            <label for="confirm-archive" class="form-check-label">Archiválom a résztvevőt.</label>
        </div>
        <button class="btn btn-outline-danger btn-sm">Résztvevő archiválása</button>
    </form>
</details>
@endsection
