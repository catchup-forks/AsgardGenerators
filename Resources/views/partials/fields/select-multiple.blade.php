<p>
    <label for="">{{ $title }}</label>

    <select class="form-control multiselect" multiple="multiple" name="{{ $name }}[]">


        @if(is_array($options))
            @foreach($options as $optionKey => $option)
                <option value="{{ $optionKey }}"
                        @if(is_array($selected) && in_array($optionKey, $selected))selected="selected"@endif
                >{{ $option }}</option>
            @endforeach
        @endif
    </select>
</p>