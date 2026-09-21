<?php

namespace App\Support;

class Seo
{
    /**
     * Sayt indeksləşə bilərmi.
     *
     * Müvəqqəti domendə və staging-də bayraq bağlıdır; əsl domenə keçəndə
     * `.env`-də `SEO_INDEXING=true` qoyulur. Produksiyadan kənarda (staging, lokal)
     * bayraqdan asılı olmayaraq həmişə bağlıdır.
     */
    public static function indexable(): bool
    {
        return (bool) config('seo.indexing') && app()->isProduction();
    }

    /** Bağlı olanda robotlara verilən göstəriş */
    public static function robotsDirective(): string
    {
        return 'noindex, nofollow';
    }
}
