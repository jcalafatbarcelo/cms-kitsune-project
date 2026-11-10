<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.menus.detail') }}: {{ $menu->identifier }}</title>
    @vite(['resources/css/admin.css', 'resources/js/admin/menu-tabs.js'])
</head>
<body>
@include('cms-template-base::system.admin._navbar')
<main class="c-adminshell">
    <h1>{{ $text('admin.menus.detail') }}: {{ $menu->identifier }}</h1>
    @include('cms-template-base::system.admin._alerts')

    <section class="c-adminsection js-menu-tabs" data-menu-tabs @if($activeTab !== null) data-active-tab="{{ $activeTab }}" @endif>
        <div class="c-admintabs" role="tablist" aria-label="{{ $text('admin.menus.items') }}">
            <button class="c-admintabs__tab" type="button" role="tab" id="items-tab" aria-controls="items-panel" aria-selected="true">{{ $text('admin.menus.items') }}</button>
            <button class="c-admintabs__tab" type="button" role="tab" id="add-item-tab" aria-controls="add-item-panel" aria-selected="false">{{ $text('admin.menus.add-item') }}</button>
            <button class="c-admintabs__tab" type="button" role="tab" id="remove-home-tab" aria-controls="remove-home-panel" aria-selected="false">{{ $text('admin.menus.remove-home') }}</button>
        </div>

        <div class="c-admintabs__panel" id="items-panel" role="tabpanel" aria-labelledby="items-tab" aria-hidden="false">
            <div class="c-admintable" role="region" aria-label="{{ $text('admin.menus.items') }}" tabindex="0">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">{{ $text('admin.menus.position') }}</th>
                        <th scope="col">{{ $text('admin.menus.label') }}</th>
                        <th scope="col">{{ $text('admin.pages.language') }}</th>
                        <th scope="col">{{ $text('admin.pages.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($items as $item)
                        @php($row = $itemRows->get($item->id))
                        <tr data-menu-item-id="{{ $item->id }}" data-depth="{{ $item->presentation_depth }}">
                            <td>{{ $item->position }}</td>
                            <td>
                                <div class="c-adminmenu-tree" style="--c-adminmenu-tree-depth: {{ (int) $item->presentation_depth }}">
                                    @if($item->presentation_depth > 0)<span class="c-adminmenu-tree__branch" aria-hidden="true"></span>@endif
                                    <span class="c-adminmenu-tree__content">
                                        <span class="c-adminmenu-tree__label">{{ $item->label }}</span>
                                        <span class="c-adminbadge-group">
                                            @if($homeItemIds->has($item->id))
                                                <span class="c-adminbadge-group__item c-adminbadge c--home">{{ $text('admin.menus.home-badge') }}</span>
                                            @endif
                                            @if($row['page'] !== null && ! $row['pageIsPublic'])
                                                <span class="c-adminbadge-group__item c-adminbadge c--unpublished">{{ $text('admin.pages.unpublished-badge') }}</span>
                                            @endif
                                        </span>
                                        <span class="c-adminmenu-tree__meta">{{ $row['page']?->name }}</span>
                                    </span>
                                </div>
                            </td>
                            <td>{{ $row['locale'] }}</td>
                            <td>
                                <form class="c-admininline" method="POST" action="{{ route('admin.menu-items.update', $item->id) }}">
                                    @csrf
                                    <input type="hidden" name="form_context" value="update-item">
                                    <label for="item-page-{{ $item->id }}">{{ $text('admin.menus.page') }}<select id="item-page-{{ $item->id }}" name="page_id">@foreach($pages as $page)<option value="{{ $page->id }}" @selected($item->page_id === $page->id)>{{ $page->name }}</option>@endforeach</select></label>
                                    <label for="item-label-{{ $item->id }}">{{ $text('admin.menus.label') }}<input id="item-label-{{ $item->id }}" name="label" required maxlength="255" value="{{ $item->label }}"></label>
                                    <button class="c-adminbutton" type="submit">{{ $text('admin.pages.save') }}</button>
                                </form>
                                <form class="c-admininline" method="POST" action="{{ route('admin.menu-items.move', $item->id) }}" data-menu-position-form data-locale="{{ $row['locale'] }}" data-item-id="{{ $item->id }}" data-excluded-ids="{{ json_encode($row['excludedIds']) }}">
                                    @csrf
                                    <input type="hidden" name="form_context" value="move-item">
                                    <label for="item-parent-{{ $item->id }}">{{ $text('admin.pages.parent') }}
                                        <select id="item-parent-{{ $item->id }}" name="parent_id" data-menu-parent>
                                            <option value="">{{ $text('admin.pages.root') }}</option>
                                            @foreach($row['parentCandidates'] as $candidate)
                                                @php($candidateLabel = $candidate->label.' (#'.$candidate->id.')')
                                                <option value="{{ $candidate->id }}" data-parent-option="{{ $candidateLabel }}" @selected($item->parent_id === $candidate->id)>{{ $candidateLabel }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label for="item-position-{{ $item->id }}">{{ $text('admin.menus.position') }}
                                        <select id="item-position-{{ $item->id }}" name="position" required data-menu-position>
                                            @if($row['destinationSiblings']->isEmpty())
                                                <option value="1">{{ $text('admin.menus.position-last') }}</option>
                                            @else
                                                <option value="1" @selected($item->position === 1)>{{ $text('admin.menus.position-first') }}</option>
                                                @foreach($row['destinationSiblings']->slice(0, -1) as $index => $sibling)
                                                    <option value="{{ $index + 2 }}" @selected($item->position === $index + 2)>{{ str_replace(':item', $sibling->label.' (#'.$sibling->id.')', $text('admin.menus.position-after')) }}</option>
                                                @endforeach
                                                @php($lastSibling = $row['destinationSiblings']->last())
                                                <option value="{{ $row['destinationSiblings']->count() + 1 }}" @selected($item->position === $row['destinationSiblings']->count() + 1)>{{ str_replace(':item', $lastSibling->label.' (#'.$lastSibling->id.')', $text('admin.menus.position-last-after')) }}</option>
                                            @endif
                                        </select>
                                    </label>
                                    <button class="c-adminbutton" type="submit">{{ $text('admin.pages.move') }}</button>
                                </form>
                                <div class="c-adminactions">
                                    @if(! $homeItemIds->has($item->id))<form method="POST" action="{{ route('admin.menu-items.home', $item->id) }}">@csrf<button class="c-adminbutton c--secondary" type="submit">{{ $text('admin.menus.mark-home') }}</button></form>@endif
                                    <form method="POST" action="{{ route('admin.menu-items.delete', $item->id) }}">@csrf<button class="c-adminbutton c--danger" type="submit">{{ $text('admin.pages.delete') }}</button></form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="c-admintabs__panel" id="add-item-panel" role="tabpanel" aria-labelledby="add-item-tab" aria-hidden="false">
            <form class="c-adminform" method="POST" action="{{ route('admin.menus.items.store') }}" data-menu-position-form data-excluded-ids="[]">
                @csrf
                <input type="hidden" name="form_context" value="add-item">
                <input type="hidden" name="menu" value="{{ $menu->identifier }}">
                <label for="locale">{{ $text('admin.pages.language') }}</label>
                <select id="locale" name="locale" data-menu-locale>
                    @foreach($languages as $language)
                        <option value="{{ $language->locale }}" @selected($addItem['locale'] === $language->locale)>{{ $language->locale }}</option>
                    @endforeach
                </select>
                <label for="page_id">{{ $text('admin.menus.page') }}</label>
                <select id="page_id" name="page_id">
                    @foreach($pages as $page)
                        <option value="{{ $page->id }}" @selected($addItem['pageId'] === $page->id)>{{ $page->name }}</option>
                    @endforeach
                </select>
                <label for="label">{{ $text('admin.menus.label') }}</label>
                <input id="label" name="label" required maxlength="255" value="{{ $addItem['label'] }}">
                <label for="parent_id">{{ $text('admin.pages.parent') }}</label>
                <select id="parent_id" name="parent_id" data-menu-parent>
                    <option value="">{{ $text('admin.pages.root') }}</option>
                    @foreach($addItem['parentCandidates'] as $candidate)
                        @php($candidateLabel = $candidate->label.' (#'.$candidate->id.')')
                        <option value="{{ $candidate->id }}" data-parent-option="{{ $candidateLabel }}" @selected($addItem['parentId'] === $candidate->id)>{{ $candidateLabel }}</option>
                    @endforeach
                </select>
                <label for="position">{{ $text('admin.menus.position') }}</label>
                <select id="position" name="position" required data-menu-position>
                    @if($addItem['siblings']->isEmpty())
                        <option value="1" @selected($addItem['position'] === 1)>{{ $text('admin.menus.position-last') }}</option>
                    @else
                        <option value="1" @selected($addItem['position'] === 1)>{{ $text('admin.menus.position-first') }}</option>
                        @foreach($addItem['siblings']->slice(0, -1) as $index => $sibling)
                            <option value="{{ $index + 2 }}" @selected($addItem['position'] === $index + 2)>{{ str_replace(':item', $sibling->label.' (#'.$sibling->id.')', $text('admin.menus.position-after')) }}</option>
                        @endforeach
                        @php($lastSibling = $addItem['siblings']->last())
                        <option value="{{ $addItem['siblings']->count() + 1 }}" @selected($addItem['position'] === $addItem['siblings']->count() + 1)>{{ str_replace(':item', $lastSibling->label.' (#'.$lastSibling->id.')', $text('admin.menus.position-last-after')) }}</option>
                    @endif
                </select>
                <button class="c-adminbutton" type="submit">{{ $text('admin.menus.add-item') }}</button>
            </form>
        </div>

        <div class="c-admintabs__panel" id="remove-home-panel" role="tabpanel" aria-labelledby="remove-home-tab" aria-hidden="false">
            <form class="c-adminform" method="POST" action="{{ route('admin.home.unmark') }}">
                @csrf
                <input type="hidden" name="menu" value="{{ $menu->identifier }}">
                <label for="home-locale">{{ $text('admin.pages.language') }}</label>
                <select id="home-locale" name="locale">
                    @foreach($languages as $language)
                        <option value="{{ $language->locale }}">{{ $language->locale }}</option>
                    @endforeach
                </select>
                <label for="confirm">{{ $text('admin.menus.remove-home') }}</label>
                <input id="confirm" name="confirm" type="checkbox" value="1">
                <button class="c-adminbutton c--danger" type="submit">{{ $text('admin.menus.remove-home') }}</button>
            </form>
        </div>

        <script type="application/json" data-menu-items>{!! json_encode($menuItemsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        <script type="application/json" data-menu-position-labels>{!! json_encode([
            'root' => $text('admin.pages.root'),
            'first' => $text('admin.menus.position-first'),
            'after' => $text('admin.menus.position-after'),
            'last' => $text('admin.menus.position-last'),
            'lastAfter' => $text('admin.menus.position-last-after'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    </section>
</main>
</body>
</html>
