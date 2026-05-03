<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RSS feed URLs
    |--------------------------------------------------------------------------
    |
    | URLs of RSS/Atom feeds to fetch. Each item will be stored as an Article
    | if not already imported (matched by source_url).
    |
    */
    'rss_feeds' => array_filter(explode(',', env('RSS_FEED_URLS', ''))),

    /*
    |--------------------------------------------------------------------------
    | User ID for imported articles
    |--------------------------------------------------------------------------
    |
    | The user_id to assign to articles imported from RSS. Defaults to the
    | first user when null.
    |
    */
    'rss_import_user_id' => env('RSS_IMPORT_USER_ID'),
];
