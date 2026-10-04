<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Error {{ $status }}</title>
</head>
<body>
<main>
    <h1>Error {{ $status }}</h1>
    @php($current = $exception)
    @while($current)
        <section>
            <h2>{{ $current::class }}</h2>
            <p>{{ $current->getMessage() }}</p>
            <p>{{ $current->getFile() }}:{{ $current->getLine() }}</p>
            <ol>
                @foreach($current->getTrace() as $index => $frame)
                    <li>
                        #{{ $index }}
                        {{ $frame['file'] ?? '[internal]' }}:{{ $frame['line'] ?? '' }}
                        {{ ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '') }}()
                    </li>
                @endforeach
            </ol>
        </section>
        @php($current = $current->getPrevious())
    @endwhile
</main>
</body>
</html>
