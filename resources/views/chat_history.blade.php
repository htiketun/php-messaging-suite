<ul>
    @foreach ($messages as $msg)
        @dd($msg);
        <li>
            {{ $msg['message'] ?? '[Non-text message]' }}
            <br>
            <small>{{ $msg['date'] ? date('Y-m-d H:i:s', $msg['date']) : '' }}</small>
        </li>
    @endforeach
</ul>
