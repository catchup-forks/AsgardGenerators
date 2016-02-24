<p>
@if(isset($is_translation) && $is_translation === 1)
    <div class="form-group">
        @foreach($options as $optionValue => $optionText)
            <?php $isSelected = ($optionValue === $value); ?>
            {!! Form::radio($name, $optionValue, $isSelected) !!}&nbsp;{{ $optionText }}
        @endforeach
    </div>
@else
    {!! Form::label($name, $title) !!}
    <div class="form-group">
        @foreach($options as $optionValue => $optionText)
            <?php $isSelected = ($optionValue === $value); ?>
            {!! Form::radio($name, $optionValue, $isSelected) !!}&nbsp;{{ $optionText }}
        @endforeach
    </div>
    @endif
    </p>