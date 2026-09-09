<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Verse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class VerseAdminController extends Controller
{
    public function index(Request $request): View
    {
        $bookId = $request->query('book_id');
        $chapter = $request->query('chapter');
        $q = $request->query('q');

        $query = Verse::query()
            ->with(['book:id,code,slug,name_ar,name_en'])
            ->withCount('tokens')
            ->orderBy('book_id')
            ->orderBy('chapter')
            ->orderBy('verse');

        if ($bookId) {
            $query->where('book_id', $bookId);
        }
        if ($chapter) {
            $query->where('chapter', (int) $chapter);
        }
        if ($q) {
            $query->where('osis_id', 'like', "%{$q}%");
        }

        $verses = $query->paginate(30)->withQueryString();
        $books = Book::orderBy('book_number')->get(['id', 'code', 'name_ar', 'name_en']);

        // For Arabic text preview, we need to fetch one token per verse (first word)
        // Use eager? We'll fetch via relationship in view via $verse->tokens->first() but that would be N+1, so we preload via with tokens limit 1
        // Instead we can load via separate query for current page's verse_osis
        $osisList = $verses->pluck('osis_id')->toArray();
        $firstTokens = \App\Models\ArabicConcordance::whereIn('verse_osis', $osisList)
            ->where('word_pos', 1)
            ->get(['verse_osis', 'arabic_word', 'strong_id'])
            ->keyBy('verse_osis');

        return view('admin.verses.index', compact('verses', 'books', 'firstTokens'));
    }

    public function toggle(Request $request, Verse $verse): RedirectResponse
    {
        $verse->is_indexed = ! (bool) $verse->is_indexed;
        $verse->save();
        $this->flushSitemapCache();

        return back()->with('success', ($verse->is_indexed ? '✅ مفعل: ' : '🚫 noindex: ') . $verse->osis_id);
    }

    public function bulkEnable(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('warning', 'لم يتم اختيار أي آية');
        }
        Verse::whereIn('id', $ids)->update(['is_indexed' => true]);
        $this->flushSitemapCache();

        return back()->with('success', '✅ تم تفعيل فهرسة '.count($ids).' آية');
    }

    public function bulkDisable(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('warning', 'لم يتم اختيار أي آية');
        }
        Verse::whereIn('id', $ids)->update(['is_indexed' => false]);
        $this->flushSitemapCache();

        return back()->with('warning', '🚫 تم إلغاء فهرسة '.count($ids).' آية');
    }

    private function flushSitemapCache(): void
    {
        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['bible', 'commentaries', 'sitemap'])->flush();
            } else {
                Cache::flush();
            }
        } catch (\Throwable $e) {
            Cache::flush();
        }
    }
}
