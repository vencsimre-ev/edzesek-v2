@extends('layouts.app')
@section('title', 'Fless!Buda mászóedzés')
@section('body-class', 'public-home')
@section('main-class', 'landing-page')
@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
@endpush
@section('content')
<div class="landing-container">
    <section class="landing-card" aria-labelledby="landing-title">
        <div class="landing-climbing-hold landing-hold-1" aria-hidden="true"></div>
        <div class="landing-climbing-hold landing-hold-2" aria-hidden="true"></div>
        <span class="landing-eyebrow">Fless!Buda · mászóedzés</span>
        <h1 id="landing-title">Mászóedzések a Fless!Budában</h1>
        <p class="landing-intro">
            Csütörtöki kezdő és haladó mászóedzések a Fless!Budában.<br>
            Az edzésekkel kapcsolatban e-mailben tudsz érdeklődni.
        </p>
        <a class="landing-email-button" href="mailto:vencsimre.ev@gmail.com?subject=Fless%20Buda%20edzés">Írj e-mailt, ha bármi kérdésed felmerülne</a>
        <div class="landing-grid">
            <article class="landing-info" aria-labelledby="sessions-title">
                <h2 class="landing-info-label" id="sessions-title">Következő edzések</h2>
                <ul class="landing-sessions">
                    @forelse($sessions as $session)
                        <li class="landing-session">
                            <div>
                                <time datetime="{{ $session->starts_at->setTimezone(config('training.timezone'))->format('c') }}">{{ $session->starts_at->setTimezone(config('training.timezone'))->translatedFormat('Y. F j., l H:i') }}–{{ $session->ends_at->setTimezone(config('training.timezone'))->format('H:i') }}</time>
                                <div class="landing-session-location">{{ $session->location }}</div>
                            </div>
                            @if($session->status === \App\Enums\TrainingSessionStatus::Cancelled)
                                <span class="landing-cancelled">Elmarad</span>
                            @else
                                <a class="landing-email-button" href="{{ route('check-in.index', ['training_session_id' => $session->id]) }}">Részt veszek!</a>
                            @endif
                        </li>
                    @empty
                        <li class="landing-small">Jelenleg nincs meghirdetett edzés.</li>
                    @endforelse
                </ul>
            </article>
            <article class="landing-info">
                <h2 class="landing-info-label">Edzőjegyek</h2>
                <div class="landing-price"><strong>Bevezető edzőjegy</strong><br>2 500 Ft / alkalom</div>
                <div class="landing-price"><strong>10 alkalmas bérlet</strong><br>22 000 Ft</div>
                <div class="landing-small">Az edzőjegy megváltásával kapcsolatban a recepción tudnak tájékoztatást adni.</div>
            </article>
        </div>
        <section class="landing-about" aria-labelledby="about-title">
            <img
                class="landing-about-photo"
                src="{{ asset('images/vencsimi.jpg') }}"
                alt="Vencsellői Imre mászóedző"
                loading="lazy"
            >

            <div class="landing-about-content">
                <h2 id="about-title">Rólam</h2>

                <p>
                    2016 februárjában, egy életmódváltást követően kezdtem el mászni.
                    Azóta töretlen a lelkesedésem, és egy percig sem bántam meg,
                    hogy a konditerem helyett a mászótermet választottam.
                </p>

                <p>
                    Az elmúlt években a mászás több területén is sokféle tapasztalatot
                    szereztem, az edzéstartástól és oktatástól kezdve a versenyeken és
                    szövetségi programokban való részvételig. Sportmászó oktatói
                    képesítéssel rendelkezem.
                </p>

                <p>
                    2021 óta tartok rendszeresen edzéseket a Váci úti Mountex
                    boulderteremben, elsősorban kezdő és haladó mászóknak. A Bethesda
                    Gyermekkórházzal való együttműködés keretében kamaszoknak is tartok
                    mászóedzéseket, ahol mászóinstruktorként dolgozom együtt a
                    foglalkozásokat segítő szakemberekkel.
                </p>

                <p>
                    A budapesti Olympic Qualifier Series eseményén biztosítóemberként,
                    ifjúsági nemzetközi versenyeken pedig bíróként vettem részt. Az
                    Iskolai Mászófal Programban testnevelő tanárok felkészítését segítettem.
                </p>

                <p class="landing-about-invite">
                    Szeretettel várok mindenkit, aki most ismerkedne meg a mászással,
                    valamint azokat is, akik már rendelkeznek tapasztalattal, de úgy
                    érzik, elakadtak a fejlődésben, és szeretnének magabiztosan
                    továbblépni a következő szintre.
                </p>
            </div>
        </section>
    </section>
    <footer>Fless Buda mászóedzés · vencsimre.ev@gmail.com</footer>
</div>
@endsection
