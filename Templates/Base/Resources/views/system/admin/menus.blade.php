<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.menus.title') }}</title>
</head>
<body>
<main>
    <h1>{{ $text('admin.menus.title') }}</h1>
    <p><a href="{{ route('admin.dashboard') }}">{{ $text('admin.dashboard.title') }}</a> · <a href="{{ route('admin.pages.index') }}">{{ $text('admin.pages.title') }}</a></p>

    @if($errors->any())
        <p role="alert">{{ $errors->first() }}</p>
    @endif

    <h2>{{ $text('admin.menus.create') }}</h2>
    <form method="POST" action="{{ route('admin.menus.store') }}">
        @csrf
        <label for="identifier">Identifier</label>
        <input id="identifier" name="identifier" required maxlength="100">
        <button type="submit">{{ $text('admin.menus.create') }}</button>
    </form>

    <h2>{{ $text('admin.menus.items') }}</h2>
    <form method="POST" action="{{ route('admin.menus.items.store') }}">
        @csrf
        <label for="menu">Menu</label>
        <select id="menu" name="menu">
            @foreach($menus as $menu)
                <option value="{{ $menu->identifier }}">{{ $menu->identifier }}</option>
            @endforeach
        </select>
        <label for="locale">{{ $text('admin.pages.language') }}</label>
        <select id="locale" name="locale">
            @foreach($languages as $language)
                <option value="{{ $language->locale }}">{{ $language->locale }}</option>
            @endforeach
        </select>
        <label for="page_id">Page</label>
        <select id="page_id" name="page_id">
            @foreach($pages as $page)
                <option value="{{ $page->id }}">#{{ $page->id }}</option>
            @endforeach
        </select>
        <label for="label">Label</label>
        <input id="label" name="label" required maxlength="255">
        <button type="submit">{{ $text('admin.menus.items') }}</button>
    </form>

    @foreach($menus as $menu)
        <h3>{{ $menu->identifier }}</h3>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ $text('admin.pages.language') }}</th>
                <th>Label</th>
                <th>Page</th>
                <th>{{ $text('admin.pages.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($items->where('menu_id', $menu->id) as $item)
                @php($itemLocale = $languages->firstWhere('id', $item->language_id)?->locale)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>{{ $itemLocale }}</td>
                    <td>{{ $item->label }}</td>
                    <td>#{{ $item->page_id }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.menu-items.update', $item->id) }}">
                            @csrf
                            <label for="item-page-{{ $item->id }}">Page</label>
                            <select id="item-page-{{ $item->id }}" name="page_id">
                                @foreach($pages as $page)
                                    <option value="{{ $page->id }}" @if($item->page_id === $page->id) selected @endif>#{{ $page->id }}</option>
                                @endforeach
                            </select>
                            <label for="item-label-{{ $item->id }}">Label</label>
                            <input id="item-label-{{ $item->id }}" name="label" required maxlength="255" value="{{ $item->label }}">
                            <button type="submit">Save</button>
                        </form>
                        <form method="POST" action="{{ route('admin.menu-items.move', $item->id) }}">
                            @csrf
                            <label for="item-parent-{{ $item->id }}">Parent</label>
                            <select id="item-parent-{{ $item->id }}" name="parent_id">
                                <option value=""></option>
                                @foreach($items->where('menu_id', $menu->id)->where('language_id', $item->language_id) as $candidate)
                                    @if($candidate->id !== $item->id)
                                        <option value="{{ $candidate->id }}" @if($item->parent_id === $candidate->id) selected @endif>#{{ $candidate->id }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <label for="item-position-{{ $item->id }}">Position</label>
                            <input id="item-position-{{ $item->id }}" name="position" required min="1" type="number" value="{{ $item->position }}">
                            <button type="submit">Move</button>
                        </form>
                        @if(! $homes->firstWhere('menu_item_id', $item->id))
                            <form method="POST" action="{{ route('admin.menu-items.home', $item->id) }}" style="display:inline">
                                @csrf
                                <button type="submit">{{ $text('admin.menus.mark-home') }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.menu-items.delete', $item->id) }}" style="display:inline">
                            @csrf
                            <button type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endforeach

    <h2>{{ $text('admin.menus.unmark-home') }}</h2>
    <form method="POST" action="{{ route('admin.home.unmark') }}">
        @csrf
        <label for="home-locale">{{ $text('admin.pages.language') }}</label>
        <select id="home-locale" name="locale">
            @foreach($languages as $language)
                <option value="{{ $language->locale }}">{{ $language->locale }}</option>
            @endforeach
        </select>
        <label for="confirm">
            <input id="confirm" name="confirm" type="checkbox" value="1">
            {{ $text('admin.menus.unmark-home') }}
        </label>
        <button type="submit">{{ $text('admin.menus.unmark-home') }}</button>
    </form>
</main>
</body>
</html>
