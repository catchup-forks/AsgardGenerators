<p>
    @if(isset($is_translation) && $is_translation === 1)
        {!! Form::label("{$lang}[{$name}]", $title) !!}
        {!! Form::date("{$lang}[{$name}]",
            Input::old("{$lang}[{$name}]"),
            ['class' => 'form-control', 'placeholder' => $placeholder ]
            ) !!}
    @else
        {!! Form::label($name, $title) !!}
        {!! Form::date($name, $value, [
            'class' => 'form-control',
            'placeholder' => $placeholder,
        ]) !!}
    @endif
</p>