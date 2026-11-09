<header class="c-adminnav">
    <div class="c-adminnav__inner">
        <a class="c-adminnav__brand" href="{{ route('admin.dashboard') }}">{{ $text('admin.brand') }}</a>
        <nav class="c-adminnav__links" aria-label="{{ $text('admin.navigation') }}">
            <a href="{{ route('admin.dashboard') }}"@if(request()->routeIs('admin.dashboard')) class="is-active" aria-current="page"@endif>{{ $text('admin.dashboard.title') }}</a>
            <a href="{{ route('admin.pages.index') }}"@if(request()->routeIs('admin.pages.*')) class="is-active" aria-current="page"@endif>{{ $text('admin.pages.title') }}</a>
            <a href="{{ route('admin.menus.index') }}"@if(request()->routeIs('admin.menus.*')) class="is-active" aria-current="page"@endif>{{ $text('admin.menus.title') }}</a>
            <a href="{{ route('admin.audit') }}"@if(request()->routeIs('admin.audit')) class="is-active" aria-current="page"@endif>{{ $text('admin.audit.title') }}</a>
        </nav>
        <form class="c-adminnav__logout" method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit">{{ $text('admin.dashboard.logout') }}</button>
        </form>
    </div>
</header>
