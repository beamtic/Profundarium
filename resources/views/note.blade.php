<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ($title ?? '') !== '' ? $title : 'Note' }}</title>
    @foreach ($openGraph as $tag)
        <meta property="{{ $tag['property'] }}" content="{{ $tag['content'] }}">
    @endforeach
    <link rel="stylesheet" href="/profundarium/assets/css/notes.css">
    @if (str_contains($html, 'mathjax-process'))
        <script src="/profundarium/assets/js/mathjax-config.js"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/mathjax@4.0.0/tex-svg.js"></script>
    @endif
</head>
<body>
    <main class="note mathjax-ignore">
        {!! $html !!}
    </main>
</body>
</html>
