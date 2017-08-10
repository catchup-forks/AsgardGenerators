<?php
// check if the options are actually an array
// if not init an empty array
$options = is_array($options) ? $options : [];
$classes = isset($classes) ? $classes : '';
?>

<p>
    @if(isset($is_translation) && $is_translation === 1)
        {!! Form::label("{$lang}[{$name}]", $title) !!}
        {!! Form::select("{$lang}[{$name}]", $options, $selected, [
            'class' => "form-control $classes"
        ]) !!}
    @else
        @if(!empty($title))
            {!! Form::label($name, $title) !!}
        @endif
        {!! Form::select($name, $options, $selected, [
         'class' => "form-control $classes"
     ]) !!}
    @endif
</p>
