@extends('layouts.app')

@section('title', 'Kezdőlap | Edzések v2')

@section('content')
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-3">Mászóedzések</h1>

            <p class="text-secondary">
                Az edzések és részvételek kezelésére szolgáló alkalmazás
                fejlesztés alatt áll.
            </p>

            <button
                class="btn btn-outline-primary"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#project-info"
                aria-expanded="false"
                aria-controls="project-info"
            >
                További információ
            </button>

            <div class="collapse mt-3" id="project-info">
                <p class="mb-0">
                    Itt lesznek majd elérhetők az edzések, a részvételek
                    és a bérletalkalmak.
                </p>
            </div>
        </div>
    </div>
@endsection