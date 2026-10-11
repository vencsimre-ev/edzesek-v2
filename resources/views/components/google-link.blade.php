@if(config('services.google.client_id') && config('services.google.client_secret'))
    @if(auth()->user()->google_id)
        <p class="mt-3">A Google-fiókod össze van kapcsolva.</p>
    @else
        <form class="mt-3" method="POST" action="{{ route('google.link') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary">Google-fiók összekapcsolása</button>
        </form>
    @endif
@endif
