@extends('layouts.app')
@section('title', 'Résztvevők | Edzések v2')
@section('content')
<div class="d-flex flex-wrap gap-3 justify-content-between mb-4">
    <h1 class="h3">Aktív résztvevők</h1>
    <a class="btn btn-primary" href="{{ route('admin.participants.create') }}">Új vendég</a>
</div>
<form method="GET" class="mb-4 d-flex gap-2">
    <label for="search" class="visually-hidden">Név, email vagy telefonszám</label>
    <input id="search" name="search" type="search" class="form-control" maxlength="255" value="{{ $search }}" placeholder="Név, email vagy telefonszám">
    <button class="btn btn-outline-primary">Keresés</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.participants.index') }}">Összes</a>
</form>
<div class="table-responsive">
<table class="table align-middle">
    <thead><tr><th>Név</th><th>Email</th><th>Telefon</th><th>Fiók</th><th>Részletek</th></tr></thead>
    <tbody>
    @forelse($participants as $participant)
        <tr><td>{{ $participant->name }}</td><td>{{ $participant->email ?? '–' }}</td><td>{{ $participant->phone ?? '–' }}</td><td>{{ $participant->user_id ? 'Kapcsolt' : 'Vendég' }}</td><td><a href="{{ route('admin.participants.show', $participant) }}">Megnyitás</a></td></tr>
    @empty
        <tr><td colspan="5">Nincs találat.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $participants->links() }}
@endsection
