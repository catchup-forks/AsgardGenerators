<p>
    <?php $classes = isset($classes) ? $classes : 'form-control'; ?>
    @if(isset($is_translation) && $is_translation === 1)
        {!! Form::label("{$lang}[{$name}]", $title) !!}
        <textarea class="{{$classes}}" name="{{$lang}}[{{$name}}]">{{Input::old("{$lang}[{$name}]", $value)}}</textarea>
    @else
        {!! Form::label($name, $title) !!}
        <textarea class="{{$classes}}" name="{{$name}}">{{Input::old("$name", $value)}}</textarea>
    @endif
</p>
