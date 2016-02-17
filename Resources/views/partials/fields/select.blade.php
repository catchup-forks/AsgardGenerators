<p>
    <label for="">{{ $title }}</label>
    {!! Form::select($name, $options->pluck($primary_key, $primary_key), $selected, [
        'class' => 'form-control'
    ]) !!}
</p>