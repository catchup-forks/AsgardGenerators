<p>
    <label for="">{{ $title }}</label>
    {!! Form::select($name, $options, $selected, [
        'class' => 'form-control'
    ]) !!}
</p>