@extends('layouts.app')
@section('title', 'Student fiók összekapcsolása | '.config('app.public_name'))
@section('content')
<h1 class="h3">Fiók kapcsolása: {{ $participant->name }}</h1>
<p>Válassz student fiókot. Foglalt fióknál hibát kapsz; automatikus összevonás nem történik. A jogosultság és a jóváhagyás nem változik.</p>
<form method="GET" class="d-flex gap-2 mb-4">
    <label for="search" class="visually-hidden">Fiók neve vagy emailcíme</label>
    <input id="search" name="search" type="search" class="form-control" maxlength="255" value="{{ $search }}" placeholder="Fiók neve vagy emailcíme">
    <button class="btn btn-outline-primary">Keresés</button>
</form>
<div class="table-responsive"><table class="table align-middle">
<thead><tr><th>Név</th><th>Email</th><th>Kiválasztás</th></tr></thead><tbody>
@forelse($users as $user)
<tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>
<form method="POST" action="{{ route('admin.participants.link-user.store', $participant) }}">
    @csrf <input type="hidden" name="user_id" value="{{ $user->id }}">
    <button class="btn btn-outline-primary btn-sm">Összekapcsolás</button>
</form></td></tr>
@empty <tr><td colspan="3">Nincs találat.</td></tr>
@endforelse
</tbody></table></div>
{{ $users->links() }}
<a href="{{ route('admin.participants.show', $participant) }}">Vissza a résztvevőhöz</a>
@endsection
