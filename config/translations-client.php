<?php

return [
    /*
     | The base URL of your Translation Manager instance.
     | Example: https://translations.yourapp.com
     */
    'url' => env('TRANSLATIONS_URL'),

    /*
     | The API token generated in Translation Manager → API Tokens.
     | The token is tied to a project — all imports will go to that project.
     */
    'token' => env('TRANSLATIONS_TOKEN'),

    /*
     | The path to your lang directory.
     | Defaults to Laravel's lang_path().
     */
    'lang_path' => null,

    /*
     | File names to exclude from the push (without locale prefix).
     | Example: ['validation.php', 'passwords.php']
     */
    'exclude_files' => [],

    /*
     | Whether to overwrite existing translations in the Translation Manager.
     */
    'overwrite' => true,
];
