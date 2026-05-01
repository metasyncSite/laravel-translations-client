<?php

namespace TranslationsClient\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PullTranslationsCommand extends Command
{
    protected $signature = 'translations:pull
                            {--locale= : Only pull a specific locale (e.g. en)}
                            {--format=php : Output format: php or json}
                            {--dry-run : Show what would be written without touching files}';

    protected $description = 'Pull translations from the Translation Manager into this project\'s lang directory';

    public function handle(): int
    {
        $url = config('translations-client.url');
        $token = config('translations-client.token');

        if (! $url || ! $token) {
            $this->error('TRANSLATIONS_URL and TRANSLATIONS_TOKEN must be set in your .env file.');

            return self::FAILURE;
        }

        $langPath = config('translations-client.lang_path') ?? lang_path();
        $format = $this->option('format');
        $dryRun = $this->option('dry-run');
        $base = rtrim($url, '/');

        $langData = Http::withToken($token)->acceptJson()->get("{$base}/api/v1/languages");

        if ($langData->failed()) {
            $this->error('Failed to fetch languages: '.$langData->body());

            return self::FAILURE;
        }

        $locales = collect($langData->json('data'))->pluck('code')->all();

        if ($onlyLocale = $this->option('locale')) {
            $locales = array_filter($locales, fn ($c) => $c === $onlyLocale);

            if (empty($locales)) {
                $this->warn("Locale \"{$onlyLocale}\" not found in Translation Manager.");

                return self::SUCCESS;
            }
        }

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Pulling ".count($locales).' locale(s): '.implode(', ', $locales));

        foreach ($locales as $locale) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->get("{$base}/api/v1/translations/{$locale}?format=nested");

            if ($response->failed()) {
                $this->error("  {$locale}: failed — ".$response->body());

                continue;
            }

            $groups = $response->json('data') ?? [];
            $keyCount = array_sum(array_map('count', $groups));

            if ($dryRun) {
                $this->line("  <comment>{$locale}</comment>: {$keyCount} keys across ".count($groups).' group(s).');

                continue;
            }

            $written = $this->writeLocale($langPath, $locale, $groups, $format);
            $this->line("  <info>{$locale}</info>: {$keyCount} keys → {$written} file(s) written.");
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info('Done.');
        }

        return self::SUCCESS;
    }

    private function writeLocale(string $langPath, string $locale, array $groups, string $format): int
    {
        $written = 0;

        foreach ($groups as $groupName => $flatKeys) {
            $nested = $this->unflatten($flatKeys);

            if ($groupName === '_json') {
                $filePath = "{$langPath}/{$locale}.json";
                $this->ensureDir(dirname($filePath));
                file_put_contents($filePath, json_encode($nested, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
            } elseif ($format === 'json') {
                $filePath = "{$langPath}/{$locale}/{$groupName}.json";
                $this->ensureDir(dirname($filePath));
                file_put_contents($filePath, json_encode($nested, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
            } else {
                $filePath = "{$langPath}/{$locale}/{$groupName}.php";
                $this->ensureDir(dirname($filePath));
                file_put_contents($filePath, "<?php\n\nreturn ".$this->exportPhp($nested).";\n");
            }

            $written++;
        }

        return $written;
    }

    /**
     * Restore dot-notation flat keys back to a nested array.
     * ['auth.login' => 'Login'] → ['auth' => ['login' => 'Login']]
     */
    private function unflatten(array $flat): array
    {
        $result = [];

        foreach ($flat as $dotKey => $value) {
            $parts = explode('.', (string) $dotKey);
            $cursor = &$result;

            foreach (array_slice($parts, 0, -1) as $part) {
                if (! isset($cursor[$part]) || ! is_array($cursor[$part])) {
                    $cursor[$part] = [];
                }
                $cursor = &$cursor[$part];
            }

            $cursor[end($parts)] = $value;
        }

        return $result;
    }

    /**
     * Export a PHP array as a var_export-style string with short array syntax.
     */
    private function exportPhp(array $data, int $depth = 0): string
    {
        $indent = str_repeat('    ', $depth);
        $innerIndent = str_repeat('    ', $depth + 1);
        $lines = ['['];

        foreach ($data as $key => $value) {
            $exportedKey = is_int($key) ? $key : "'{$key}'";
            $exportedValue = is_array($value)
                ? $this->exportPhp($value, $depth + 1)
                : "'".addslashes((string) $value)."'";

            $lines[] = "{$innerIndent}{$exportedKey} => {$exportedValue},";
        }

        $lines[] = "{$indent}]";

        return implode("\n", $lines);
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
