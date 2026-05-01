<?php

namespace TranslationsClient;

class LangReader
{
    public function __construct(protected string $langPath) {}

    /**
     * Discover all locale codes from subdirectories.
     *
     * @return string[]
     */
    public function discoverLocales(): array
    {
        if (! is_dir($this->langPath)) {
            return [];
        }

        $locales = [];

        foreach (scandir($this->langPath) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir($this->langPath.'/'.$entry) && $entry !== 'vendor') {
                $locales[] = $entry;
            }
        }

        return $locales;
    }

    /**
     * Read all PHP translation files for a given locale.
     * Returns an array of group => translations.
     *
     * @param  string[]  $excludeFiles
     * @return array<string, array<string, string>>
     */
    public function readPhpGroups(string $locale, array $excludeFiles = []): array
    {
        $localePath = $this->langPath.'/'.$locale;

        if (! is_dir($localePath)) {
            return [];
        }

        $groups = [];

        foreach (glob($localePath.'/*.php') as $filePath) {
            $fileName = basename($filePath);

            if (in_array($fileName, $excludeFiles)) {
                continue;
            }

            $groupName = pathinfo($fileName, PATHINFO_FILENAME);
            $translations = require $filePath;

            if (is_array($translations)) {
                $groups[$groupName] = $this->flatten($translations);
            }
        }

        return $groups;
    }

    /**
     * Read the JSON file for a given locale (if it exists).
     * Returns translations keyed by the JSON key.
     *
     * @return array<string, string>
     */
    public function readJsonGroup(string $locale): array
    {
        $filePath = $this->langPath.'/'.$locale.'.json';

        if (! file_exists($filePath)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($filePath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Flatten a nested array into dot-notation keys.
     *
     * @return array<string, string>
     */
    protected function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix.'.'.$key : (string) $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $fullKey));
            } else {
                $result[$fullKey] = (string) $value;
            }
        }

        return $result;
    }
}
