<div class="col-xs-12">
    <div class="checkbox">
        @if(isset($is_translation) && boolval($is_translation) === true)
            <input type="hidden" name="{{ $lang }}[{{ $name }}]" value="0">

            <label>
                <input
                        type="checkbox"
                        name="{{ $lang }}[{{ $name }}]"
                        value="{{ $value }}"
                        @if(boolval($checked) === true)checked="checked"@endif
                > {{ $title }}
            </label>
        @else
            <input type="hidden" name="{{ $name }}" value="0">
            <label>
                <input
                        type="checkbox"
                        name="{{ $name }}"
                        value="{{ $value }}"
                @if(boolval($checked) === true)checked="checked"@endif
                > {{ $title }}
            </label>

        @endif
    </div>
</div>