@php($flash = session('admin_flash'))
@php($flashType = is_array($flash) && in_array($flash['type'] ?? null, ['success', 'warning', 'error'], true) ? $flash['type'] : null)
@if($flashType !== null && isset($flash['key']))
    <p class="c-adminalert c--{{ $flashType }}" role="{{ $flashType === 'success' ? 'status' : 'alert' }}">
        {{ $text($flash['key']) }}
    </p>
@endif
@if(isset($error))
    <p class="c-adminalert c--error" role="alert">{{ $error }}</p>
@endif
@if($errors->any())
    <p class="c-adminalert c--error" role="alert">{{ implode(' ', $errors->all()) }}</p>
@endif
