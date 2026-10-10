<?php
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Book;
use Illuminate\Support\Facades\Cache;
Cache::forget('nav_books');
echo "Books count: ".Book::count()."\n";
echo "Sample:\n";
foreach(Book::orderBy('id')->limit(3)->get(['id','code','name_ar','testament']) as $b) print_r($b->toArray());
echo "OT count: ".Book::where('testament','OT')->count()."\n";
echo "NT count: ".Book::where('testament','NT')->count()."\n";
echo "All with testament null: ".Book::whereNull('testament')->count()."\n";
$nav = Cache::remember('nav_books', 86400, function(){ return Book::orderBy('id')->get(['id','code','slug','name_ar','name_en','testament']); });
echo "navBooks OT: ".$nav->where('testament','OT')->count()." NT: ".$nav->where('testament','NT')->count()."\n";
