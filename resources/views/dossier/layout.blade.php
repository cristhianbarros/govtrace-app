{{-- US-056-LEG: la hoja de los documentos del expediente. DejaVu Sans viene con dompdf y trae las tildes. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 2.2cm 2cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; line-height: 1.45; color: #111; }
        h1 { font-size: 15pt; margin: 0 0 6pt; }
        h2 { font-size: 11pt; margin: 16pt 0 5pt; text-transform: uppercase; }
        h2, h3 { page-break-after: avoid; }
        h3 { font-size: 10pt; margin: 10pt 0 3pt; }
        p { margin: 0 0 7pt; }
        ol, ul { margin: 0 0 7pt; padding-left: 18pt; }
        li { margin: 0 0 3pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0 0 8pt; page-break-inside: avoid; }
        th, td { border: 0.5pt solid #999; padding: 3pt 5pt; vertical-align: top; text-align: left; font-size: 9pt; }
        th { width: 27%; background: #f1f1f1; }
        .hash { font-family: "DejaVu Sans Mono", monospace; font-size: 7.5pt; word-wrap: break-word; }
        .note { font-size: 8.5pt; color: #333; border: 0.5pt solid #999; padding: 5pt 7pt; margin: 0 0 14pt; }
        .small { font-size: 8.5pt; color: #333; }
        .signature { margin-top: 28pt; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
