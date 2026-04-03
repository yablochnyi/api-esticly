@extends('layouts.marketing')

@push('head')
  <style>
    .legal-shell { padding: 50px 0 80px; background: #fbfafe; min-height: 100vh; }
    .legal-container { width: min(1100px, calc(100% - 32px)); margin: 0 auto; }
    .legal-card { background: #fff; border: 1px solid rgba(115, 96, 170, 0.14); border-radius: 28px; padding: 32px; box-shadow: 0 24px 70px rgba(43, 31, 82, 0.08); }
    .legal-breadcrumbs { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:18px; font-size:14px; color:#7d7893; }
    .legal-breadcrumbs a { color:#7d7893; text-decoration:none; }
    .legal-top { display:flex; justify-content:space-between; gap:20px; align-items:flex-start; margin-bottom:20px; }
    .legal-title { margin:0; font-size:40px; line-height:1.1; color:#261d48; }
    .legal-meta { color:#8b85a1; font-weight:600; font-size:14px; }
    .legal-content h2 { margin:28px 0 12px; font-size:22px; color:#261d48; }
    .legal-content p, .legal-content li { color:#59536d; line-height:1.72; font-size:16px; }
    .legal-content ul { margin:0; padding-left:22px; }
    .legal-content a { color:#4e3d7f; }
    @media (max-width: 767px) {
      .legal-shell { padding-top: 110px; }
      .legal-card { padding: 22px; border-radius: 22px; }
      .legal-top { flex-direction: column; }
      .legal-title { font-size: 30px; }
    }
  </style>
@endpush

@section('content')
  <main class="legal-shell">
    <div class="legal-container">
      <div class="legal-card">
        <div class="legal-breadcrumbs">
          <a href="{{ $landingHomeUrl }}">Esticly</a>
          <span>/</span>
          <span>{{ $title }}</span>
        </div>

        <div class="legal-top">
          <div>
            <h1 class="legal-title">{{ $title }}</h1>
            <div class="legal-meta">{{ $metaUpdatedLabel }}: {{ $updated }}</div>
          </div>
        </div>

        <div class="legal-content">
          <h2>{{ $copy['h1'] }}</h2>
          <p>{!! str_replace(':email', '<a href="mailto:'.$email.'">'.$email.'</a>', e($copy['p1'])) !!}</p>

          <h2>{{ $copy['h2'] }}</h2>
          <ul>
            @foreach($copy['l2'] as $row)
              <li>{{ $row }}</li>
            @endforeach
          </ul>

          <h2>{{ $copy['h3'] }}</h2>
          <ul>
            @foreach($copy['l3'] as $row)
              <li>{{ $row }}</li>
            @endforeach
          </ul>

          <h2>{{ $copy['h4'] }}</h2>
          <p>{{ $copy['p4'] }}</p>

          <h2>{{ $copy['h5'] }}</h2>
          <p>{{ $copy['p5'] }}</p>

          <h2>{{ $copy['h6'] }}</h2>
          <p>{!! str_replace(':email', '<a href="mailto:'.$email.'">'.$email.'</a>', e($copy['p6'])) !!}</p>
        </div>
      </div>
    </div>
  </main>
@endsection
