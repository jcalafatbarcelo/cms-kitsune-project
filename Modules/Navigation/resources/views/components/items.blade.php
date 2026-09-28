<ul>
    @foreach ($items as $item)
        <li>
            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
            @if ($item['children'] !== [])
                @include('navigation::components.items', ['items' => $item['children']])
            @endif
        </li>
    @endforeach
</ul>
