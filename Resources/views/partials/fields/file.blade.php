{!! Form::label(null, $title) !!}
<table>
    <tr>
        @if(!empty($value))
            <tr>
                <td>
                    <a target="_blank" href="{{$value}}">Current sheet</a>
                </td>
            </tr>
        @endif
        <td>
            @if(isset($is_translation) && $is_translation === 1)

                <input name="{{$lang}}[{{$name}}]" type="file">

            @else
                <input name="{{$name}}" type="file">
            @endif
        </td>
    </tr>
</table>
