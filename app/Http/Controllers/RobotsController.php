<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * `robots.txt` dinamik verilir: məzmun `SEO_INDEXING` bayrağından asılıdır.
 *
 * Bayraq bağlı olanda bütün sayt robotlara bağlanır və sitemap GÖSTƏRİLMİR —
 * əks halda robot sitemap-dakı ünvanları tapıb indeksləməyə cəhd edərdi.
 */
class RobotsController extends Controller
{
    /** Robotlara bağlı olan bölmələr (indeksləşmə açıq olanda da) */
    private const DISALLOWED = ['/admin', '/student', '/teacher', '/profile', '/payments'];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (! Seo::indexable()) {
            $lines[] = 'Disallow: /';
        } else {
            foreach (self::DISALLOWED as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
