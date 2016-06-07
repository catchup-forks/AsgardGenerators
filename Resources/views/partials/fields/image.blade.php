<table>
    <tr>
        @if($value)
        <td width="60px">
            <a href="{{$value}}" target="_blank">
                <img src="{{$value}}" alt="" width="50px" height="50px">
            </a>
        </td>
        @endif
        <td>
            @if(isset($is_translation) && $is_translation === 1)

                {!! Form::label("{$lang}[{$name}]", $title) !!}
                <input name="{{$lang}}[{{$name}}]" type="file" accept='image/*'>

            @else
                {!! Form::label($name, $title) !!}
                <input name="{{$name}}" type="file" accept='image/*'>
            @endif
        </td>
    </tr>
</table>
