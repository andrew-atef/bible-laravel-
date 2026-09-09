<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lexicon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class LexiconAdminController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->query('q');
        $filterTop500 = $request->query('top500');

        $query = Lexicon::query()
            ->select(['strong_id', 'language', 'lemma', 'transliteration', 'strongs_definition', 'kjv_translations', 'is_indexed']);

        if ($q) {
            $qTrim = trim($q);
            $query->where(function ($w) use ($qTrim): void {
                $w->where('strong_id', 'like', "%{$qTrim}%")
                    ->orWhere('lemma', 'like', "%{$qTrim}%")
                    ->orWhere('transliteration', 'like', "%{$qTrim}%")
                    ->orWhere('strongs_definition', 'like', "%{$qTrim}%");
            });
        }

        // Preset Top 500 theological seeds: we define top by occurrences in arabic_concordance
        if ($filterTop500) {
            $topIds = Cache::remember('lexicon_top500_ids', 86400, function (): array {
                return DB::table('arabic_concordance')
                    ->select('strong_id', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('strong_id')
                    ->orderByDesc('cnt')
                    ->limit(500)
                    ->pluck('strong_id')
                    ->toArray();
            });
            $query->whereIn('strong_id', $topIds);
        }

        // Add occurrences count via withCount? Use subquery for performance
        $lexicons = $query->orderBy('strong_id')->paginate(30)->withQueryString();

        // Batch occurrences for current page
        $ids = $lexicons->pluck('strong_id')->toArray();
        $occMap = [];
        if ($ids) {
            $counts = DB::table('arabic_concordance')
                ->select('strong_id', DB::raw('COUNT(*) as cnt'))
                ->whereIn('strong_id', $ids)
                ->groupBy('strong_id')
                ->pluck('cnt', 'strong_id');
            foreach ($ids as $id) {
                $occMap[$id] = (int) ($counts[$id] ?? 0);
            }
        }

        return view('admin.lexicon.index', compact('lexicons', 'occMap'));
    }

    public function toggle(Request $request, Lexicon $lexicon): RedirectResponse
    {
        $lexicon->is_indexed = ! (bool) $lexicon->is_indexed;
        $lexicon->save();
        $this->flushSitemapCache();

        return back()->with('success', ($lexicon->is_indexed ? '✅ مفعل: ' : '🚫 noindex: ') . $lexicon->strong_id);
    }

    public function bulkEnable(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        // Support Top500 bulk via filter
        if (empty($ids) && $request->input('top500_bulk')) {
            $topIds = DB::table('arabic_concordance')
                ->select('strong_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('strong_id')
                ->orderByDesc('cnt')
                ->limit(500)
                ->pluck('strong_id')
                ->toArray();
            Lexicon::whereIn('strong_id', $topIds)->update(['is_indexed' => true]);
            $this->flushSitemapCache();
            return back()->with('success', '✅ تم تفعيل أهم 500 كلمة لاهوتية (Top 500 Seeds)');
        }

        if (empty($ids)) {
            return back()->with('warning', 'لم يتم اختيار أي كلمة');
        }
        // Lexicon primary is strong_id string, ids are strong_ids
        Lexicon::whereIn('strong_id', $ids)->update(['is_indexed' => true]);
        $this->flushSitemapCache();

        return back()->with('success', '✅ تم تفعيل فهرسة '.count($ids).' كلمة');
    }

    public function bulkDisable(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('warning', 'لم يتم اختيار أي كلمة');
        }
        Lexicon::whereIn('strong_id', $ids)->update(['is_indexed' => false]);
        $this->flushSitemapCache();

        return back()->with('warning', '🚫 تم إلغاء فهرسة '.count($ids).' كلمة');
    }

    private function flushSitemapCache(): void
    {
        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['bible', 'lexicon', 'sitemap'])->flush();
            } else {
                Cache::flush();
            }
        } catch (\Throwable $e) {
            Cache::flush();
        }
    }
}
