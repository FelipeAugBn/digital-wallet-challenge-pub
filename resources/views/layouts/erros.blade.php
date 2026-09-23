@if ($errors->any())
    <ul class="erros" role="alert">
        @foreach ($errors->all() as $erro)
            <li>{{ $erro }}</li>
        @endforeach
    </ul>
@endif
