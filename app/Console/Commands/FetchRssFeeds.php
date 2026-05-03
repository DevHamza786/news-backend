<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FetchRssFeeds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:fetch-rss
                            {--feed= : Single feed URL to fetch (optional)}
                            {--dry-run : Parse and log without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch news articles from configured RSS feeds and store them in the Articles table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $feedUrls = $this->getFeedUrls();
        if (empty($feedUrls)) {
            $this->warn('No RSS feed URLs configured. Set RSS_FEED_URLS in .env (comma-separated) or use --feed=<url>.');

            return self::FAILURE;
        }

        $userId = $this->resolveImportUserId();
        if (! $userId) {
            $this->error('No user found for importing. Create a user or set RSS_IMPORT_USER_ID in .env.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        foreach ($feedUrls as $feedUrl) {
            $feedUrl = trim($feedUrl);
            if ($feedUrl === '') {
                continue;
            }

            $this->components->info("Fetching: {$feedUrl}");
            $items = $this->fetchAndParseFeed($feedUrl);
            if ($items === null) {
                $this->components->error("Failed to fetch or parse feed: {$feedUrl}");
                continue;
            }

            foreach ($items as $item) {
                $existing = Article::where('source_url', $item['source_url'])->first();
                if ($existing) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->components->twoColumnDetail('Would create', $item['title']);
                    $created++;

                    continue;
                }

                $article = $this->createArticleFromItem($item, $userId);
                if ($article) {
                    $created++;
                    $this->components->twoColumnDetail('Created', $article->title);
                }
            }
        }

        $this->components->info("Done. Created: {$created}, Skipped (already exist): {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function getFeedUrls(): array
    {
        $single = $this->option('feed');
        if (is_string($single) && $single !== '') {
            return [$single];
        }

        return config('news.rss_feeds', []);
    }

    private function resolveImportUserId(): ?int
    {
        $id = config('news.rss_import_user_id');
        if ($id !== null && $id !== '') {
            return (int) $id;
        }

        return User::query()->value('id');
    }

    /**
     * @return array<int, array{title: string, body: string, excerpt: string|null, source_url: string, published_at: string|null}>|null
     */
    private function fetchAndParseFeed(string $url): ?array
    {
        $response = Http::timeout(15)->get($url);
        if (! $response->successful()) {
            return null;
        }

        $xml = @simplexml_load_string($response->body());
        if ($xml === false) {
            return null;
        }

        $items = [];
        $namespaces = $xml->getNamespaces(true);

        if (isset($xml->channel)) {
            $items = $this->parseRss2($xml, $namespaces);
        } elseif (isset($xml->entry)) {
            $items = $this->parseAtom($xml, $namespaces);
        } else {
            foreach ($xml->children() as $child) {
                if ($child->getName() === 'channel') {
                    $items = $this->parseRss2($child, $namespaces);
                    break;
                }
                if ($child->getName() === 'entry') {
                    $items = $this->parseAtom($xml, $namespaces);
                    break;
                }
            }
        }

        return empty($items) ? null : $items;
    }

    /**
     * @param \SimpleXMLElement $xml
     * @param array<string, string> $namespaces
     * @return array<int, array{title: string, body: string, excerpt: string|null, source_url: string, published_at: string|null}>
     */
    private function parseRss2(\SimpleXMLElement $xml, array $namespaces): array
    {
        $items = [];
        $channel = $xml->channel ?? $xml;
        foreach ($channel->item as $item) {
            $link = (string) ($item->link ?? $item->guid ?? '');
            if ($link === '') {
                continue;
            }
            $title = $this->trimText((string) $item->title);
            if ($title === '') {
                $title = Str::limit($link, 80);
            }
            $description = $this->stripHtml((string) ($item->description ?? $item->children($namespaces['content'] ?? '')->encoded ?? ''));
            $pubDate = $this->parseDate((string) ($item->pubDate ?? $item->children($namespaces['dc'] ?? '')->date ?? ''));

            $items[] = [
                'title' => $title,
                'body' => $description ?: $title,
                'excerpt' => Str::limit($description, 500) ?: null,
                'source_url' => $link,
                'published_at' => $pubDate,
            ];
        }

        return $items;
    }

    /**
     * @param \SimpleXMLElement $xml
     * @param array<string, string> $namespaces
     * @return array<int, array{title: string, body: string, excerpt: string|null, source_url: string, published_at: string|null}>
     */
    private function parseAtom(\SimpleXMLElement $xml, array $namespaces): array
    {
        $items = [];
        $entries = $xml->entry ?? $xml->children($namespaces['atom'] ?? '')->entry ?? [];
        if (! is_iterable($entries)) {
            $entries = $entries ? [$entries] : [];
        }
        foreach ($entries as $entry) {
            $link = '';
            foreach ($entry->link as $l) {
                $attrs = $l->attributes();
                if ((string) ($attrs['rel'] ?? 'self') === 'alternate' || (string) ($attrs['type'] ?? '') === 'text/html') {
                    $link = (string) ($attrs['href'] ?? '');
                    if ($link !== '') {
                        break;
                    }
                }
            }
            if ($link === '' && isset($entry->link)) {
                $link = (string) ($entry->link[0]->attributes()->href ?? '');
            }
            if ($link === '') {
                continue;
            }
            $title = $this->trimText((string) ($entry->title ?? ''));
            if ($title === '') {
                $title = Str::limit($link, 80);
            }
            $content = (string) ($entry->content ?? $entry->summary ?? '');
            $body = $this->stripHtml($content) ?: $title;
            $pubDate = $this->parseDate((string) ($entry->updated ?? $entry->published ?? ''));

            $items[] = [
                'title' => $title,
                'body' => $body,
                'excerpt' => Str::limit($body, 500) ?: null,
                'source_url' => $link,
                'published_at' => $pubDate,
            ];
        }

        return $items;
    }

    private function trimText(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function stripHtml(string $s): string
    {
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->trimText($s);
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    /**
     * @param array{title: string, body: string, excerpt: string|null, source_url: string, published_at: string|null} $item
     */
    private function createArticleFromItem(array $item, int $userId): ?Article
    {
        $slug = Str::slug($item['title']);
        if (Article::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . substr(md5($item['source_url']), 0, 8);
        }
        if (Article::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . now()->timestamp;
        }

        return Article::create([
            'user_id' => $userId,
            'title' => $item['title'],
            'slug' => $slug,
            'body' => $item['body'],
            'excerpt' => $item['excerpt'],
            'status' => 'published',
            'published_at' => $item['published_at'],
            'source_url' => $item['source_url'],
        ]);
    }
}
