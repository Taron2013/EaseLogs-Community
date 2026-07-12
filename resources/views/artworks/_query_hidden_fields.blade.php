@foreach ($params as $name => $value)
    @if (is_array($value))
        @foreach ($value as $item)
            <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
        @endforeach
    @else
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endif
@endforeach
