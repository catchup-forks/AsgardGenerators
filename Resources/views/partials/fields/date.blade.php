<p>
    <label for="">{{ $title }}</label>
    {!! Form::date($name, $value, [
        'class' => 'form-control',
        'placeholder' => $placeholder,
    ]) !!}
</p>