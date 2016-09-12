<?php
// check if the options are actually an array
// if not init an empty array
$options = is_array($options) ? $options : [];
$classes = isset($classes) ? $classes : '';
?>

<p>
    @if(isset($is_translation) && $is_translation === 1)
        <label for="{{ $lang }}[{{ $name }}]">{{ $title }}</label>
        <select id="{{ $lang }}[{{ $name }}]" class="form-control multiselect {{ $classes }}" multiple="multiple"
                name="{{ $lang }}[{{ $name }}][]">
            @if(is_array($options))
                @foreach($options as $optionKey => $option)
                    <option value="{{ $optionKey }}"
                            @if(is_array($selected) && in_array($optionKey, $selected))selected="selected"@endif
                    >{{ $option }}</option>
                @endforeach
            @endif
        </select>
    @else
        <label for="{{ $name }}">{{ $title }}</label>
        <select id="{{ $name }}" class="form-control multiselect {{ $classes }}" multiple="multiple" name="{{ $name }}[]">
            @if(is_array($options))
                @foreach($options as $optionKey => $option)
                    <option value="{{ $optionKey }}"
                            @if(is_array($selected) && in_array($optionKey, $selected))selected="selected"@endif
                    >{{ $option }}</option>
                @endforeach
            @endif
        </select>
    @endif
</p>
