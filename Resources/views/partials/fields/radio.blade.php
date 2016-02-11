<p>
    <label for="">
        {{ $title }}
    </label>

<div class="form-group">
    @foreach($options as $optionValue => $optionText)
        <?php $isSelected = ($optionValue === $value); ?>
        {!! Form::radio($name, $optionValue, $isSelected) !!}&nbsp;{{ $optionText }}
    @endforeach
</div>
</p>