<?php

namespace App\Providers;

use App\View\Composers\SeoComposer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Enforce SQLite pragmas required by biblical_core.db:
     *  - foreign_keys=ON (referential integrity for lexicons/aribic_concordance)
     *  - journal_mode=WAL (concurrent read performance)
     *  - synchronous=NORMAL (WAL durability balance)
     *  - busy_timeout=5000ms (graceful handling of locked reads)
     *  - encoding=UTF-8 (Arabic/Hebrew/Greek integrity)
     */
    public function boot(): void
    {
        if (config('database.default') === 'sqlite' || config('database.connections.biblical_core')) {
            try {
                // These pragmas are idempotent and safe to run on each request via the default connection.
                // They are also applied directly to the underlying PDO for SQLite.
                DB::statement('PRAGMA foreign_keys = ON');
                DB::statement('PRAGMA journal_mode = WAL');
                DB::statement('PRAGMA synchronous = NORMAL');
                DB::statement('PRAGMA busy_timeout = 5000');
                // encoding pragma is read-only after creation, but we verify it.
            } catch (\Throwable $e) {
                // Silently ignore during early boot (e.g. artisan optimize without DB)
            }
        }

        // Global SEO composer — injects defaults into every view (controllers can override via $seo)
        View::composer('*', SeoComposer::class);

        // Share navigation books for header Book/Chapter selector (cached 24h) — also for welcome/home
        // Fallback to hardcoded 66 books if DB is missing/empty (server without biblical_core.db)
        View::composer(['layouts.app', 'layouts.*', 'bible.*', 'read.*', 'welcome', 'home', '*'], function ($view): void {
            try {
                $books = \Illuminate\Support\Facades\Cache::remember('nav_books', 86400, function () {
                    $dbBooks = \App\Models\Book::orderBy('id')->get(['id','code','slug','name_ar','name_en','testament']);
                    if ($dbBooks->isNotEmpty()) {
                        return $dbBooks;
                    }
                    // Fallback: hardcoded 66 books (server without DB file)
                    return collect([
                        ['id'=>1,'code'=>'Gen','slug'=>'al-takwin','name_ar'=>'التكوين','name_en'=>'Genesis','testament'=>'OT','book_number'=>1],
                        ['id'=>2,'code'=>'Exo','slug'=>'al-khuruj','name_ar'=>'الخروج','name_en'=>'Exodus','testament'=>'OT','book_number'=>2],
                        ['id'=>3,'code'=>'Lev','slug'=>'al-lawyin','name_ar'=>'اللاويين','name_en'=>'Leviticus','testament'=>'OT','book_number'=>3],
                        ['id'=>4,'code'=>'Num','slug'=>'al-adad','name_ar'=>'العدد','name_en'=>'Numbers','testament'=>'OT','book_number'=>4],
                        ['id'=>5,'code'=>'Deu','slug'=>'al-tathniya','name_ar'=>'التثنية','name_en'=>'Deuteronomy','testament'=>'OT','book_number'=>5],
                        ['id'=>6,'code'=>'Jos','slug'=>'yashua','name_ar'=>'يشوع','name_en'=>'Joshua','testament'=>'OT','book_number'=>6],
                        ['id'=>7,'code'=>'Jdg','slug'=>'al-qudat','name_ar'=>'القضاة','name_en'=>'Judges','testament'=>'OT','book_number'=>7],
                        ['id'=>8,'code'=>'Rut','slug'=>'raouth','name_ar'=>'راعوث','name_en'=>'Ruth','testament'=>'OT','book_number'=>8],
                        ['id'=>9,'code'=>'1Sa','slug'=>'samuel-al-awwal','name_ar'=>'صموئيل الأول','name_en'=>'1 Samuel','testament'=>'OT','book_number'=>9],
                        ['id'=>10,'code'=>'2Sa','slug'=>'samuel-al-thani','name_ar'=>'صموئيل الثاني','name_en'=>'2 Samuel','testament'=>'OT','book_number'=>10],
                        ['id'=>11,'code'=>'1Ki','slug'=>'al-muluk-al-awwal','name_ar'=>'الملوك الأول','name_en'=>'1 Kings','testament'=>'OT','book_number'=>11],
                        ['id'=>12,'code'=>'2Ki','slug'=>'al-muluk-al-thani','name_ar'=>'الملوك الثاني','name_en'=>'2 Kings','testament'=>'OT','book_number'=>12],
                        ['id'=>13,'code'=>'1Ch','slug'=>'akhbar-al-ayyam-al-awwal','name_ar'=>'أخبار الأيام الأول','name_en'=>'1 Chronicles','testament'=>'OT','book_number'=>13],
                        ['id'=>14,'code'=>'2Ch','slug'=>'akhbar-al-ayyam-al-thani','name_ar'=>'أخبار الأيام الثاني','name_en'=>'2 Chronicles','testament'=>'OT','book_number'=>14],
                        ['id'=>15,'code'=>'Ezr','slug'=>'azra','name_ar'=>'عزرا','name_en'=>'Ezra','testament'=>'OT','book_number'=>15],
                        ['id'=>16,'code'=>'Neh','slug'=>'nahmya','name_ar'=>'نحميا','name_en'=>'Nehemiah','testament'=>'OT','book_number'=>16],
                        ['id'=>17,'code'=>'Est','slug'=>'astir','name_ar'=>'أستير','name_en'=>'Esther','testament'=>'OT','book_number'=>17],
                        ['id'=>18,'code'=>'Job','slug'=>'ayyub','name_ar'=>'أيوب','name_en'=>'Job','testament'=>'OT','book_number'=>18],
                        ['id'=>19,'code'=>'Psa','slug'=>'al-mazamir','name_ar'=>'المزامير','name_en'=>'Psalms','testament'=>'OT','book_number'=>19],
                        ['id'=>20,'code'=>'Pro','slug'=>'al-amthal','name_ar'=>'الأمثال','name_en'=>'Proverbs','testament'=>'OT','book_number'=>20],
                        ['id'=>21,'code'=>'Ecc','slug'=>'al-jamia','name_ar'=>'الجامعة','name_en'=>'Ecclesiastes','testament'=>'OT','book_number'=>21],
                        ['id'=>22,'code'=>'Sng','slug'=>'nashid-al-anshad','name_ar'=>'نشيد الأنشاد','name_en'=>'Song of Songs','testament'=>'OT','book_number'=>22],
                        ['id'=>23,'code'=>'Isa','slug'=>'ishaia','name_ar'=>'إشعياء','name_en'=>'Isaiah','testament'=>'OT','book_number'=>23],
                        ['id'=>24,'code'=>'Jer','slug'=>'irmya','name_ar'=>'إرميا','name_en'=>'Jeremiah','testament'=>'OT','book_number'=>24],
                        ['id'=>25,'code'=>'Lam','slug'=>'marathi-irmya','name_ar'=>'مراثي إرميا','name_en'=>'Lamentations','testament'=>'OT','book_number'=>25],
                        ['id'=>26,'code'=>'Ezk','slug'=>'hazqyal','name_ar'=>'حزقيال','name_en'=>'Ezekiel','testament'=>'OT','book_number'=>26],
                        ['id'=>27,'code'=>'Dan','slug'=>'danyal','name_ar'=>'دانيال','name_en'=>'Daniel','testament'=>'OT','book_number'=>27],
                        ['id'=>28,'code'=>'Hos','slug'=>'hushai','name_ar'=>'هوشع','name_en'=>'Hosea','testament'=>'OT','book_number'=>28],
                        ['id'=>29,'code'=>'Jol','slug'=>'yuil','name_ar'=>'يوئيل','name_en'=>'Joel','testament'=>'OT','book_number'=>29],
                        ['id'=>30,'code'=>'Amo','slug'=>'amous','name_ar'=>'عاموس','name_en'=>'Amos','testament'=>'OT','book_number'=>30],
                        ['id'=>31,'code'=>'Oba','slug'=>'oubadya','name_ar'=>'عوبديا','name_en'=>'Obadiah','testament'=>'OT','book_number'=>31],
                        ['id'=>32,'code'=>'Jon','slug'=>'yunan','name_ar'=>'يونان','name_en'=>'Jonah','testament'=>'OT','book_number'=>32],
                        ['id'=>33,'code'=>'Mic','slug'=>'mikha','name_ar'=>'ميخا','name_en'=>'Micah','testament'=>'OT','book_number'=>33],
                        ['id'=>34,'code'=>'Nam','slug'=>'nahum','name_ar'=>'ناحوم','name_en'=>'Nahum','testament'=>'OT','book_number'=>34],
                        ['id'=>35,'code'=>'Hab','slug'=>'habquq','name_ar'=>'حبقوق','name_en'=>'Habakkuk','testament'=>'OT','book_number'=>35],
                        ['id'=>36,'code'=>'Zep','slug'=>'safanya','name_ar'=>'صفنيا','name_en'=>'Zephaniah','testament'=>'OT','book_number'=>36],
                        ['id'=>37,'code'=>'Hag','slug'=>'haji','name_ar'=>'حجي','name_en'=>'Haggai','testament'=>'OT','book_number'=>37],
                        ['id'=>38,'code'=>'Zec','slug'=>'zakariya','name_ar'=>'زكريا','name_en'=>'Zechariah','testament'=>'OT','book_number'=>38],
                        ['id'=>39,'code'=>'Mal','slug'=>'malakhi','name_ar'=>'ملاخي','name_en'=>'Malachi','testament'=>'OT','book_number'=>39],
                        ['id'=>40,'code'=>'Mat','slug'=>'matta','name_ar'=>'متى','name_en'=>'Matthew','testament'=>'NT','book_number'=>40],
                        ['id'=>41,'code'=>'Mrk','slug'=>'murqus','name_ar'=>'مرقس','name_en'=>'Mark','testament'=>'NT','book_number'=>41],
                        ['id'=>42,'code'=>'Luk','slug'=>'luqa','name_ar'=>'لوقا','name_en'=>'Luke','testament'=>'NT','book_number'=>42],
                        ['id'=>43,'code'=>'Jhn','slug'=>'yuhanna','name_ar'=>'يوحنا','name_en'=>'John','testament'=>'NT','book_number'=>43],
                        ['id'=>44,'code'=>'Act','slug'=>'aamal-al-rusul','name_ar'=>'أعمال الرسل','name_en'=>'Acts','testament'=>'NT','book_number'=>44],
                        ['id'=>45,'code'=>'Rom','slug'=>'rumya','name_ar'=>'رومية','name_en'=>'Romans','testament'=>'NT','book_number'=>45],
                        ['id'=>46,'code'=>'1Co','slug'=>'kurunthus-al-ula','name_ar'=>'كورنثوس الأولى','name_en'=>'1 Corinthians','testament'=>'NT','book_number'=>46],
                        ['id'=>47,'code'=>'2Co','slug'=>'kurunthus-al-thaniya','name_ar'=>'كورنثوس الثانية','name_en'=>'2 Corinthians','testament'=>'NT','book_number'=>47],
                        ['id'=>48,'code'=>'Gal','slug'=>'ghalatya','name_ar'=>'غلاطية','name_en'=>'Galatians','testament'=>'NT','book_number'=>48],
                        ['id'=>49,'code'=>'Eph','slug'=>'afsas','name_ar'=>'أفسس','name_en'=>'Ephesians','testament'=>'NT','book_number'=>49],
                        ['id'=>50,'code'=>'Php','slug'=>'filibbi','name_ar'=>'فيلبي','name_en'=>'Philippians','testament'=>'NT','book_number'=>50],
                        ['id'=>51,'code'=>'Col','slug'=>'kulussi','name_ar'=>'كولوسي','name_en'=>'Colossians','testament'=>'NT','book_number'=>51],
                        ['id'=>52,'code'=>'1Th','slug'=>'tasaluniki-al-ula','name_ar'=>'تسالونيكي الأولى','name_en'=>'1 Thessalonians','testament'=>'NT','book_number'=>52],
                        ['id'=>53,'code'=>'2Th','slug'=>'tasaluniki-al-thaniya','name_ar'=>'تسالونيكي الثانية','name_en'=>'2 Thessalonians','testament'=>'NT','book_number'=>53],
                        ['id'=>54,'code'=>'1Ti','slug'=>'timuthawus-al-ula','name_ar'=>'تيموثاوس الأولى','name_en'=>'1 Timothy','testament'=>'NT','book_number'=>54],
                        ['id'=>55,'code'=>'2Ti','slug'=>'timuthawus-al-thaniya','name_ar'=>'تيموثاوس الثانية','name_en'=>'2 Timothy','testament'=>'NT','book_number'=>55],
                        ['id'=>56,'code'=>'Tit','slug'=>'titus','name_ar'=>'تيطس','name_en'=>'Titus','testament'=>'NT','book_number'=>56],
                        ['id'=>57,'code'=>'Phm','slug'=>'filimun','name_ar'=>'فليمون','name_en'=>'Philemon','testament'=>'NT','book_number'=>57],
                        ['id'=>58,'code'=>'Heb','slug'=>'al-ibranyin','name_ar'=>'العبرانيين','name_en'=>'Hebrews','testament'=>'NT','book_number'=>58],
                        ['id'=>59,'code'=>'Jas','slug'=>'yaqub','name_ar'=>'يعقوب','name_en'=>'James','testament'=>'NT','book_number'=>59],
                        ['id'=>60,'code'=>'1Pe','slug'=>'butrus-al-ula','name_ar'=>'بطرس الأولى','name_en'=>'1 Peter','testament'=>'NT','book_number'=>60],
                        ['id'=>61,'code'=>'2Pe','slug'=>'butrus-al-thaniya','name_ar'=>'بطرس الثانية','name_en'=>'2 Peter','testament'=>'NT','book_number'=>61],
                        ['id'=>62,'code'=>'1Jn','slug'=>'yuhanna-al-ula','name_ar'=>'يوحنا الأولى','name_en'=>'1 John','testament'=>'NT','book_number'=>62],
                        ['id'=>63,'code'=>'2Jn','slug'=>'yuhanna-al-thaniya','name_ar'=>'يوحنا الثانية','name_en'=>'2 John','testament'=>'NT','book_number'=>63],
                        ['id'=>64,'code'=>'3Jn','slug'=>'yuhanna-al-thalitha','name_ar'=>'يوحنا الثالثة','name_en'=>'3 John','testament'=>'NT','book_number'=>64],
                        ['id'=>65,'code'=>'Jud','slug'=>'yahutha','name_ar'=>'يهوذا','name_en'=>'Jude','testament'=>'NT','book_number'=>65],
                        ['id'=>66,'code'=>'Rev','slug'=>'al-ruya','name_ar'=>'الرؤيا','name_en'=>'Revelation','testament'=>'NT','book_number'=>66],
                    ])->map(fn($a) => (object)$a);
                });
                $view->with('navBooks', $books);
            } catch (\Throwable $e) {
                $view->with('navBooks', collect());
            }
        });
    }
}
