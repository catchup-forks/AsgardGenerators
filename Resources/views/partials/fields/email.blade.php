<p>
    @if(isset($is_translation) && $is_translation === 1)
        {!! Form::label("{$lang}[{$name}]", $title) !!}
        {!! Form::email("{$lang}[{$name}]", $value,
            ['class' => 'form-control',
            'placeholder' => $placeholder
        ]) !!}
    @else
        {!! Form::label($name, $title) !!}
        {!! Form::email($name, $value, [
            'class' => 'form-control',
            'placeholder' => $placeholder,
        ]) !!}
    @endif
</p>
