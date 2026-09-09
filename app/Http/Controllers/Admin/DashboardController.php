<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Lexicon;
use App\Models\Verse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $booksIndexed = Book::where('is_indexed', true)->count();
        $versesIndexed = Verse::where('is_indexed', true)->count();
        $lexIndexed = Lexicon::where('is_indexed', true)->count();
        $totalIndexed = $booksIndexed + $versesIndexed + $lexIndexed + 1; // +1 homepage

        return view('admin.dashboard', compact('booksIndexed', 'versesIndexed', 'lexIndexed', 'totalIndexed'));
    }
}
