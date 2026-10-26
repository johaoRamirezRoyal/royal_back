<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <meta name="color-scheme" content="light dark" />
  <title>{{ $etiqueta }} · OMNIA</title>
  {{-- Página de PaginaAccesoNavegador: una ruta privada de la API abierta en el navegador.
       Mismo diseño que el aviso de sin conexión del frontend (public/offline.html). Todo en
       línea: la API no sirve recursos estáticos propios. --}}
  <style>
    :root { --bg: #eef1f6; --placa: #fff; --borde: rgba(15,23,42,.1); --texto: #0f172a; --suave: #55627a;
            --btn-bg: #0f172a; --btn: #fff; --btn-hover: #1e293b; --linea: rgba(15,23,42,.08);
            --ambar-bg: #fffbeb; --ambar: #92400e; --ambar-punto: #f59e0b;
            --rojo-bg: #fef2f2; --rojo: #991b1b; --rojo-punto: #ef4444; --neutro-bg: #f1f5f9; }
    @media (prefers-color-scheme: dark) {
      :root { --bg: #020617; --placa: #0f172a; --borde: rgba(255,255,255,.08); --texto: #e2e8f0; --suave: #9aa6b8;
              --btn-bg: #e8eef6; --btn: #0f172a; --btn-hover: #fff; --linea: rgba(255,255,255,.08);
              --ambar-bg: rgba(251,191,36,.1); --ambar: #fcd34d; --rojo-bg: rgba(248,113,113,.1); --rojo: #fca5a5;
              --neutro-bg: rgba(255,255,255,.06); }
    }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 16px;
           background: var(--bg); color: var(--texto); font-family: Outfit, system-ui, -apple-system, "Segoe UI", sans-serif; }
    main { width: 100%; max-width: 440px; padding: 32px; border: 1px solid var(--borde); border-radius: 12px; background: var(--placa); }
    .firma { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; letter-spacing: .14em; }
    .firma img { width: 28px; height: 28px; border-radius: 6px; }
    .chip { margin-top: 32px; display: inline-flex; align-items: center; gap: 8px; padding: 4px 8px; border-radius: 6px;
            font-size: 12.5px; font-weight: 600; }
    .chip.ambar { background: var(--ambar-bg); color: var(--ambar); }
    .chip.rojo { background: var(--rojo-bg); color: var(--rojo); }
    .punto { width: 8px; height: 8px; border-radius: 50%; }
    .chip.neutro { background: var(--neutro-bg); color: var(--suave); }
    .neutro .punto { background: var(--suave); }
    .ambar .punto { background: var(--ambar-punto); }
    .rojo .punto { background: var(--rojo-punto); }
    h1 { margin: 16px 0 0; font-size: 26px; line-height: 1.15; letter-spacing: -.015em; }
    p { margin: 8px 0 0; font-size: 14.5px; line-height: 1.6; color: var(--suave); }
    a.boton { margin-top: 28px; display: flex; align-items: center; justify-content: center; width: 100%; min-height: 46px;
              border-radius: 8px; background: var(--btn-bg); color: var(--btn); font-size: 15px; font-weight: 600; text-decoration: none; }
    a.boton:hover { background: var(--btn-hover); }
    a.boton:focus-visible { outline: 2px solid var(--btn-bg); outline-offset: 2px; }
    footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--linea); font-size: 12.5px; color: var(--suave);
             font-variant-numeric: tabular-nums; }
    @media (max-width: 400px) { main { padding: 24px; } h1 { font-size: 23px; } }
  </style>
</head>
<body>
  <main role="alert" aria-labelledby="titulo">
    <div class="firma"><img src="{{ $logo }}" alt="" onerror="this.remove()" />OMNIA</div>
    <span class="chip {{ $tono }}"><span class="punto" aria-hidden="true"></span>{{ $etiqueta }}</span>
    <h1 id="titulo">{{ $titulo }}</h1>
    <p>{{ $texto }}</p>
    <a class="boton" href="{{ $url }}">{{ $accion }}</a>
    <footer>Código {{ $codigo }}</footer>
  </main>
</body>
</html>
