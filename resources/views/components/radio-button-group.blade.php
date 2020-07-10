<fieldset class="mb-8">
    @if ($label ?? false)
        <legend class="block text-gray-700 text-sm font-medium mb-1">{{ $label }}</legend>
    @endif

    @if ($helper ?? false)
        <x-helper-text>{{ $helper }}</x-helper-text>
    @endif

    <div {{ $attributes->merge([ 'class' => "flex flex-col md:flex-row md:flex-wrap radio-group -mt-2" ]) }}>
        {{ $slot }}
    </div>

    @if ($name ?? false)
        @error($name)
            <x-validation-error id="{{ $name }}-error">
                {{ $message }}
            </x-validation-error>
        @enderror
    @endif
</fieldset>
