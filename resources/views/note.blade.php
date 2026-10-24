<!DOCTYPE html>
<html lang="{{ $language }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ($title ?? '') !== '' ? $title : 'Note' }}</title>
    @if ($description !== null)
        <meta name="description" content="{{ $description }}">
    @endif
    @foreach ($openGraph as $tag)
        <meta property="{{ $tag['property'] }}" content="{{ $tag['content'] }}">
    @endforeach
    <link rel="stylesheet" href="/profundarium/assets/css/notes.css">
    @if (str_contains($html, 'class="pygments"'))
        <link rel="stylesheet" href="/profundarium/assets/css/pygments.css">
    @endif
    @if (str_contains($html, 'mathjax-process'))
        <script src="/profundarium/assets/js/mathjax-config.js"></script>
        <script defer src="/profundarium/assets/vendor/mathjax/tex-svg.js"></script>
    @endif
    @if (str_contains($html, 'data-diagram='))
        <script defer src="/profundarium/assets/js/diagrams.js"></script>
    @endif
</head>
<body>
    <main class="note mathjax-ignore">
        {!! $html !!}
    </main>
</body>
</html>
