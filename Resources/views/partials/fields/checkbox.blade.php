<div class="col-xs-12">
    <div class="checkbox">
        @if(isset($is_translation) && $is_translation === 1)
            {!! Form::label("{$lang}[{$name}]", $title) !!}
            {!! Form::checkbox("{$lang}[{$name}]", $value, old("{$lang}[{$name}]")) !!}
        @else
            {!! Form::label($name, $title) !!}
            {!! Form::checkbox($name, $value, old($name)) !!}
        @endif
    </div>
</div>