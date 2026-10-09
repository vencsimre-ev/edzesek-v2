@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'value' => null])
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        class="form-control @error($name) is-invalid @enderror"
        @if($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif required
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @error($name)<div id="{{ $name }}-error" class="invalid-feedback">{{ $message }}</div>@enderror
</div>
