<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Support\Localization;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * `/sitemap.xml` — hər səhifə hər iki dildə, xhtml:link ilə qarşılıqlı hreflang.
 *
 * Siyahıya düşənlər: ana səhifə, qaydalar, aktiv kateqoriyalar və imtahanı olan
 * mövzu sınağı/rüb səhifələri. İmtahanın özü ictimai deyil (şagird panelindədir),
 * ona görə sitemap-a düşmür.
 *
 * Nəticə 1 saat keşlənir: kateqoriya ağacı nadir hallarda dəyişir.
 */
class SitemapController extends Controller
{
    private const CACHE_KEY = 'sitemap.xml';

    private const CACHE_TTL = 3600;

    private const TOPIC_TRIAL_SEGMENT = 'movzu-sinagi';

    public function __invoke(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Keş kateqoriya/imtahan dəyişəndə əl ilə təmizlənir (admin panel və seeder) */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function build(): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
                .'xmlns:xhtml="http://www.w3.org/1999/xhtml">',
        ];

        foreach ($this->entries() as $entry) {
            $lines[] = $this->urlNode($entry['urls'], $entry['lastmod'] ?? null);
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * Hər bənd: dil → tam URL. Bir "url" düyünü hər dil üçün ayrıca yazılır,
     * içində bütün dillərin alternativləri göstərilir (Google belə tələb edir).
     *
     * @return Collection<int, array{urls: array<string, string>, lastmod?: string}>
     */
    private function entries(): Collection
    {
        $entries = collect();

        // Statik səhifələr
        foreach (['home', 'terms'] as $name) {
            $entries->push(['urls' => $this->localized(fn (string $locale) => Localization::route($name, [], true, $locale))]);
        }

        $categories = Category::active()->orderBy('path')->get();

        foreach ($categories as $category) {
            $entries->push([
                'urls' => $this->localized(fn (string $locale) => $category->urlFor($locale)),
                'lastmod' => $category->updated_at?->toAtomString(),
            ]);
        }

        foreach ($this->topicTrialNodes($categories) as [$category, $quarters]) {
            $entries->push([
                'urls' => $this->localized(fn (string $locale) => $category->urlFor($locale).'/'.self::TOPIC_TRIAL_SEGMENT),
            ]);

            foreach ($quarters as $quarter) {
                $entries->push([
                    'urls' => $this->localized(fn (string $locale) => $category->urlFor($locale)
                        .'/'.self::TOPIC_TRIAL_SEGMENT.'/'.$quarter.'-ci-rub'),
                ]);
            }
        }

        return $entries;
    }

    /**
     * Hansı kateqoriyalarda mövzu sınağı var və hansı rüblərdə.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<int, array{0: Category, 1: array<int, int>}>
     */
    private function topicTrialNodes(Collection $categories): array
    {
        $trials = Exam::query()
            ->where('kind', Exam::KIND_TOPIC_TRIAL)
            ->where('is_published', true)
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->get(['category_id', 'quarter']);

        if ($trials->isEmpty()) {
            return [];
        }

        $result = [];

        foreach ($categories as $category) {
            $ids = $category->subtreeIds();
            $quarters = $trials->whereIn('category_id', $ids)
                ->pluck('quarter')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($quarters !== []) {
                $result[] = [$category, $quarters];
            }
        }

        return $result;
    }

    /**
     * @param  callable(string): string  $url
     * @return array<string, string>
     */
    private function localized(callable $url): array
    {
        $urls = [];

        foreach (Localization::supported() as $locale) {
            $urls[$locale] = $url($locale);
        }

        return $urls;
    }

    /** @param  array<string, string>  $urls */
    private function urlNode(array $urls, ?string $lastmod): string
    {
        $alternates = '';

        foreach ($urls as $locale => $url) {
            $alternates .= sprintf(
                "\n        <xhtml:link rel=\"alternate\" hreflang=\"%s\" href=\"%s\"/>",
                $locale,
                e($url),
            );
        }

        $alternates .= sprintf(
            "\n        <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\"/>",
            e($urls[Localization::default()]),
        );

        $nodes = '';

        foreach ($urls as $url) {
            $nodes .= sprintf(
                "    <url>\n        <loc>%s</loc>%s%s\n    </url>\n",
                e($url),
                $lastmod ? "\n        <lastmod>".e($lastmod).'</lastmod>' : '',
                $alternates,
            );
        }

        return rtrim($nodes, "\n");
    }
}
