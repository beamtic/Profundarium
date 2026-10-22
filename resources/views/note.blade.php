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
</head>
<body>
    <main class="note">
        {!! $html !!}
    </main>
</body>
</html>