@if(config('services.google.client_id') && config('services.google.client_secret'))
    @if(auth()->user()->google_id)
        <span class="dropdown-item-text">A Google-fiókod össze van kapcsolva.</span>
    @else
        <form method="POST" action="{{ route('google.link') }}">
            @csrf
            <button type="submit" class="dropdown-item">Google-fiók összekapcsolása</button>
        </form>
    @endif
@endif
