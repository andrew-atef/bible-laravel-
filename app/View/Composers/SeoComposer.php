<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\View\View;

/**
 * SeoComposer — injects global SEO defaults into every view
 *
 * Ensures even fallback pages have canonical & OG without controller boilerplate
 */
class SeoComposer
{
    public function compose(View $view): void
    {
        // Only inject if view hasn't already provided $seo variable
        if ($view->offsetExists('seo')) {
            return;
        }

        $view->with('seo', [
            'title' => config('app.name', 'المنصة الكتابية — دراسة كتابية عربية') . ' | BibleHub عربي',
            'description' => 'منصة كتابية عربية بديلة لـ BibleHub: دراسة كلمة بكلمة بالعبرية واليونانية مع الترجمة العربية، النطق، والمُقابلات والتفاسير.',
            'canonical' => url()->current(),
            'og' => [
                'title' => config('app.name', 'المنصة الكتابية'),
                'description' => 'دراسة كتابية عربية متعمقة',
                'url' => url()->current(),
                'type' => 'website',
                'locale' => 'ar_AR',
                'site_name' => config('app.name', 'المنصة الكتابية'),
                'image' => url('/images/og-default.jpg'),
            ],
            'jsonLd' => null,
            'breadcrumbs' => [
                ['name' => 'الرئيسية', 'url' => url('/')],
            ],
        ]);
    }
}
