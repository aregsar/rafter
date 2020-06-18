@if ($type == 'laravel')
    <x-icon-laravel class="{{ $class ?? '' }}" />
@elseif ($type == 'nodejs')
    <x-icon-nodejs class="{{ $class ?? '' }}" />
@else
    <x-heroicon-o-desktop-computer class="{{ $class ?? '' }}" />
@endif
