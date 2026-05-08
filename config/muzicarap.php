<?php

return [
    'admin' => [
        'name' => env('MUZICARAP_ADMIN_NAME', 'MuzicaRap Admin'),
        'email' => env('MUZICARAP_ADMIN_EMAIL', 'admin@muzicarap.test'),
        'password' => env('MUZICARAP_ADMIN_PASSWORD', 'password'),
    ],
    'crawl' => [
        'searxng_url' => env('MUZICARAP_SEARXNG_URL', 'http://192.168.0.115:8388/search'),
        'max_candidates' => (int) env('MUZICARAP_MAX_CANDIDATES', 5),
        'fetch_top_results' => (int) env('MUZICARAP_FETCH_TOP_RESULTS', 3),
        'request_timeout' => (int) env('MUZICARAP_REQUEST_TIMEOUT', 15),
        'connect_timeout' => (int) env('MUZICARAP_CONNECT_TIMEOUT', 5),
        'minimum_confidence' => (float) env('MUZICARAP_MINIMUM_LYRICS_CONFIDENCE', 0.55),
    ],
    'song_audio' => [
        'searxng_url' => env('MUZICARAP_SONG_AUDIO_SEARXNG_URL', env('MUZICARAP_SEARXNG_URL', 'http://192.168.0.115:8388/search')),
        'searxng_categories' => env('MUZICARAP_SONG_AUDIO_SEARXNG_CATEGORIES', 'videos'),
        'max_candidates' => (int) env('MUZICARAP_SONG_AUDIO_MAX_CANDIDATES', 5),
        'request_timeout' => (int) env('MUZICARAP_SONG_AUDIO_REQUEST_TIMEOUT', env('MUZICARAP_REQUEST_TIMEOUT', 15)),
        'connect_timeout' => (int) env('MUZICARAP_SONG_AUDIO_CONNECT_TIMEOUT', env('MUZICARAP_CONNECT_TIMEOUT', 5)),
        'process_timeout' => (int) env('MUZICARAP_SONG_AUDIO_PROCESS_TIMEOUT', 75),
        'directory' => env('MUZICARAP_SONG_AUDIO_DIRECTORY', 'songs'),
        'yt_dlp_binary' => env('MUZICARAP_YT_DLP_BINARY', 'yt-dlp'),
        'ffmpeg_location' => env('MUZICARAP_FFMPEG_LOCATION'),
        'temporary_directory' => env('MUZICARAP_SONG_AUDIO_TEMPORARY_DIRECTORY', storage_path('app/tmp/song-audio-downloads')),
    ],
];
