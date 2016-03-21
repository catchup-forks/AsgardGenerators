<p>
    @if(isset($is_translation) && $is_translation === 1)
        {!! Form::label("{$lang}[{$name}]", $title) !!}
        {!! Form::text("{$lang}[{$name}]",
            Input::old("{$lang}[{$name}]", isset($value[$lang])?$value[$lang]:'') ,
            ['class' => 'form-control', 'placeholder' => $placeholder ]
            ) !!}
    @else
        {!! Form::label($name, $title) !!}
        {!! Form::text($name, $value, [
            'class' => 'form-control',
            'placeholder' => $placeholder,
        ]) !!}
    @endif
</p>