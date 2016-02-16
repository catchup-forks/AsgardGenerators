
<p>
    <label for="">{{ $title }}</label>

    <select class="form-control multiselect" multiple="multiple" name="{{ $name }}[]">
        @foreach($options->pluck($primary_key) as $optionKey => $option)
            <option value="{{ $optionKey }}" @if(array_key_exists($optionKey, $selected))selected="selected"@endif>{{ $option }}</option>
        @endforeach
    </select>

</p>