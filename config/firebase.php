<?php

return [
    'credentials' => storage_path(env('FIREBASE_CREDENTIALS', 'app/firebase.json')),
    'database_url' => env('FIREBASE_DATABASE_URL', 'https://ahmeed-realtime-chat-default-rtdb.firebaseio.com'),
];
