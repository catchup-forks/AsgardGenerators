<p>
    <label for="">{{ $title }}</label>
    <?php
        //add an empty item to the array
        $options = is_array($options) ? $options : [];
    ?>


    {!! Form::select($name, $options, $selected, [
        'class' => 'form-control'
    ]) !!}
</p>