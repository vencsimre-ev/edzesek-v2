@extends('layouts.app')
@section('title', 'Admin jóváhagyások | Edzések v2')
@section('content')
<h1 class="h3 mb-4">Jóváhagyásra váró felhasználók</h1>
<div class="table-responsive">
<table class="table align-middle">
    <thead><tr><th scope="col">Név</th><th scope="col">Emailcím</th><th scope="col">Megerősítés</th><th scope="col">Művelet</th></tr></thead>
    <tbody>
    @forelse($users as $user)
        <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->email_verified_at->format('Y.m.d. H:i') }}</td>
            <td><form method="POST" action="{{ route('admin.approvals.update', $user) }}">
                @csrf @method('PATCH')
                <button class="btn btn-success btn-sm" type="submit" aria-label="{{ $user->name }} jóváhagyása">Jóváhagyás</button>
            </form></td>
        </tr>
    @empty
        <tr><td colspan="4">Nincs jóváhagyásra váró, megerősített felhasználó.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $users->links() }}
@endsection
