<ul>
    @foreach ($items as $item)
        <li>
            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
            @if ($item['children'] !== [])
                @include('base::public.navigation.items', ['items' => $item['children']])
            @endif
        </li>
    @endforeach
</ul>
