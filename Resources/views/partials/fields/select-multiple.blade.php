<p>
    <label for="">{{ $title }}</label>

    <select class="form-control multiselect" multiple="multiple" name="{{ $name }}[]">
        @foreach($options->pluck($primary_key, $primary_key) as $optionKey => $option)
            <option value="{{ $optionKey }}"
                    @if(in_array($optionKey, $selected))selected="selected"@endif
            >{{ $option }}</option>
        @endforeach
    </select>
</p>