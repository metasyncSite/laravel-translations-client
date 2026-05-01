<?php

namespace TranslationsClient\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use TranslationsClient\LangReader;

class PushTranslationsCommand extends Command
{
    protected $signature = 'translations:push
                            {--locale= : Only push a specific locale (e.g. en)}
                            {--overwrite : Overwrite existing translations (default from config)}
                            {--dry-run : Show what would be pushed without sending}';

    protected $description = 'Push translations from this project to the Translation Manager';

    public function handle(): int
    {
        $url = config('translations-client.url');
        $token = config('translations-client.token');

        if (! $url || ! $token) {
            $this->error('TRANSLATIONS_URL and TRANSLATIONS_TOKEN must be set in your .env file.');

            return self::FAILURE;
        }

        $langPath = config('translations-client.lang_path') ?? lang_path();
        $excludeFiles = config('translations-client.exclude_files', []);
        $overwrite = $this->option('overwrite') ?? config('translations-client.overwrite', true);
        $dryRun = $this->option('dry-run');

        $reader = new LangReader($langPath);
        $locales = $this->option('locale')
            ? [$this->option('locale')]
            : $reader->discoverLocales();

        if (empty($locales)) {
            $this->warn('No locales found in: '.$langPath);

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%shing %d locale(s): %s',
            $dryRun ? '[DRY RUN] Would pus' : 'Pus',
            count($locales),
            implode(', ', $locales),
        ));

        $totalNew = 0;
        $totalUpdated = 0;
        $totalKeys = 0;

        foreach ($locales as $locale) {
            $groups = $reader->readPhpGroups($locale, $excludeFiles);
            $jsonTranslations = $reader->readJsonGroup($locale);

            if ($jsonTranslations) {
                $groups['_json'] = $jsonTranslations;
            }

            if (empty($groups)) {
                $this->line("  <comment>{$locale}</comment>: no files found, skipping.");

                continue;
            }

            $keyCount = array_sum(array_map('count', $groups));

            if ($dryRun) {
                $this->line("  <comment>{$locale}</comment>: {$keyCount} keys across ".count($groups).' groups.');

                continue;
            }

            $response = Http::withToken($token)
                ->acceptJson()
                ->post(rtrim($url, '/').'/api/v1/import', [
                    'locale' => $locale,
                    'groups' => $groups,
                    'overwrite' => $overwrite,
                ]);

            if ($response->failed()) {
                $this->error("  {$locale}: failed — HTTP {$response->status()}: ".$response->body());

                continue;
            }

            $data = $response->json();
            $new = $data['new'] ?? 0;
            $updated = $data['updated'] ?? 0;
            $total = $data['total'] ?? $keyCount;

            $totalNew += $new;
            $totalUpdated += $updated;
            $totalKeys += $total;

            $this->line("  <info>{$locale}</info>: {$total} keys — {$new} new, {$updated} updated.");
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info("Done. Total: {$totalKeys} keys — {$totalNew} new, {$totalUpdated} updated.");
        }

        return self::SUCCESS;
    }
}
