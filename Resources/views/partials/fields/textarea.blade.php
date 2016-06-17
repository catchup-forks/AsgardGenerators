<p>
    <?php $classes = isset($classes) ? $classes : 'form-control'; ?>
    @if(isset($is_translation) && $is_translation === 1)
        {!! Form::label("{$lang}[{$name}]", $title) !!}
        {!! Form::textarea("{$lang}[{$name}]",
            Input::old("{$lang}[{$name}]", isset($value[$lang])?$value[$lang]:'') ,
            ['class' => $classes, 'placeholder' => $placeholder ]
            ) !!}
    @else
        {!! Form::label($name, $title) !!}
        {!! Form::textarea($name, $value, [
            'class' => $classes,
            'placeholder' => $placeholder,
        ]) !!}
    @endif
</p>
