<p>
    <label for="">{{ $title }}</label>
    {!! Form::text($name, $value, [
        'class' => 'form-control',
        'placeholder' => $placeholder,
    ]) !!}
</p>