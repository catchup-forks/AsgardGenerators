<div class="col-xs-12">
    <div class="checkbox">
        @if(isset($is_translation) && $is_translation === 1)
            <input type="hidden" name="{{ $lang }}[{{ $name }}]" value="0">

            <label>
                <input
                        type="checkbox"
                        name="{{ $lang }}[{{ $name }}]"
                        value="{{ $value }}"
                        @if($checked === true)checked="checked"@endif
                > {{ $title }}
            </label>
        @else
            <input type="hidden" name="{{ $name }}" value="0">
            <label>
                <input
                        type="checkbox"
                        name="{{ $name }}"
                        value="{{ $value }}"
                @if($checked === true)checked="checked"@endif
                > {{ $title }}
            </label>

        @endif
    </div>
</div>