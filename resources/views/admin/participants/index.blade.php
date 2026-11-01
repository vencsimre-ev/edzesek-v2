@extends('layouts.app')
@section('title', 'Résztvevők | '.config('app.public_name'))
@section('content')
<div class="d-flex flex-wrap gap-3 justify-content-between mb-4">
    <h1 class="h3">Résztvevők</h1>
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
    <thead><tr><th>Név</th><th>Email</th></tr></thead>
    <tbody>
    @forelse($participants as $participant)
        <tr>
            <td>
                <a href="{{ route('admin.participants.show', $participant) }}">{{ $participant->name }}</a>
                @if($participant->user_id)
                    <span class="text-secondary ms-1" title="Kapcsolt bejelentkezési fiók">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="7" r="4" />
                            <path d="M4 21v-2a8 8 0 0 1 16 0v2" />
                        </svg>
                        <span class="visually-hidden">Kapcsolt bejelentkezési fiók</span>
                    </span>
                @endif
            </td>
            <td>{{ $participant->email ?? '–' }}</td>
        </tr>
    @empty
        <tr><td colspan="2">Nincs találat.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $participants->links() }}
@endsection
