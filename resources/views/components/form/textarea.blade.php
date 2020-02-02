<div class="flex flex-wrap mb-6">
    <label for="{{ $name }}" class="block text-gray-700 text-sm font-bold mb-2">
        {{ $label }}:
    </label>

    <div class="text-sm text-gray-600 w-full mb-2">{{ $helper ?? '' }}</div>

    <textarea
        id="{{ $name }}"
        class="form-textarea w-full @error($name) border-red-500 @enderror {{ $classes ?? '' }}"
        name="{{ $name }}"
        value="{{ old($name) }}"
        rows="10"
        {{ $required ? 'required' : ''}}></textarea>

    @error($name)
        <p class="text-red-500 text-xs italic mt-4">
            {{ $message }}
        </p>
    @enderror
</div>
