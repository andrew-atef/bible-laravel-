<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class BookAdminController extends Controller
{
    public function index(): View
    {
        $books = Book::orderBy('book_number')->get();

        return view('admin.books.index', compact('books'));
    }

    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $book->is_indexed = ! (bool) $book->is_indexed;
        $book->save();

        $this->flushSitemapCache();

        return back()->with('success', $book->is_indexed
            ? "✅ تم تفعيل فهرسة السفر: {$book->name_ar} ({$book->code})"
            : "🚫 تم إلغاء فهرسة السفر: {$book->name_ar}");
    }

    public function enableBook(Request $request, Book $book): RedirectResponse
    {
        \DB::transaction(function () use ($book): void {
            $book->update(['is_indexed' => true]);
            // Cascade to all verses of this book
            \App\Models\Verse::where('book_id', $book->id)->update(['is_indexed' => true]);
        });

        $this->flushSitemapCache();

        $count = $book->verses()->count();

        return back()->with('success', "✅ تم تفعيل فهرسة السفر كاملاً: {$book->name_ar} + {$count} آية");
    }

    public function disableBook(Request $request, Book $book): RedirectResponse
    {
        \DB::transaction(function () use ($book): void {
            $book->update(['is_indexed' => false]);
            \App\Models\Verse::where('book_id', $book->id)->update(['is_indexed' => false]);
        });

        $this->flushSitemapCache();

        return back()->with('warning', "🚫 تم إلغاء فهرسة {$book->name_ar} وجميع آياته (مخفي من جوجل)");
    }

    public function bulkEnable(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('warning', 'لم يتم اختيار أي سفر');
        }

        $testament = $request->input('testament');
        // Warning modal already shown client-side; server double-check for whole testament
        if ($testament && in_array($testament, ['OT', 'NT'], true) && count($ids) >= 20) {
            // Allow but log warning
        }

        \DB::transaction(function () use ($ids): void {
            Book::whereIn('id', $ids)->update(['is_indexed' => true]);
            \App\Models\Verse::whereIn('book_id', $ids)->update(['is_indexed' => true]);
        });

        $this->flushSitemapCache();

        return back()->with('success', '✅ تم تفعيل فهرسة '.count($ids).' سفر (مع آياتها)');
    }

    public function bulkDisable(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('warning', 'لم يتم اختيار أي سفر');
        }

        \DB::transaction(function () use ($ids): void {
            Book::whereIn('id', $ids)->update(['is_indexed' => false]);
            \App\Models\Verse::whereIn('book_id', $ids)->update(['is_indexed' => false]);
        });

        $this->flushSitemapCache();

        return back()->with('warning', '🚫 تم إلغاء فهرسة '.count($ids).' سفر');
    }

    private function flushSitemapCache(): void
    {
        // Invalidate all sitemap-related cache tags
        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['bible', 'commentaries', 'lexicon', 'sitemap'])->flush();
                Cache::tags(['bible'])->flush();
            } else {
                Cache::flush();
            }
        } catch (\Throwable $e) {
            Cache::flush();
        }
        // Also clear view cache that may contain sitemap urls
        try {
            \Illuminate\Support\Facades\Artisan::call('view:clear');
        } catch (\Throwable $e) {
        }
    }
}
