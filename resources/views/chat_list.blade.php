<ul>
    @foreach ($dialogs as $chat)
        <li>
            <a href="{{ route('telegram.chat.history', ['id' => $chat['id']]) }}">
                {{ $chat['title'] ?? ($chat['username'] ?? 'Private Chat') }}
            </a>
        </li>
    @endforeach
</ul>
