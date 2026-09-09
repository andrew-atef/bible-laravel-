<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Commentary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CommentarySeeder — demonstrates 3-tier commentary integration
 *
 * 1. St. John Chrysostom on Jhn.1.1 (verse-level, early Church Father)
 * 2. Fr. Matta El-Meskeen on Jhn.1.1-1.5 Prologue (passage-level, theological study)
 * 3. Word-level note anchored to H7225 "في البدء" (רֵאשִׁית)
 */
class CommentarySeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        // Clean previous seeded samples (keep idempotent: delete where source_book = 'seeded')
        Commentary::whereIn('source_book', ['Patrologia Graeca', 'المسكين - الإنجيل بحسب يوحنا', 'دراسة كلمة - H7225'])->delete();

        // 1. Early Church Father — St. John Chrysostom on John 1:1 (verse-level)
        Commentary::create([
            'author_name' => 'القديس يوحنا ذهبي الفم',
            'era' => 'آبائي — القرن الرابع',
            'verse_start' => 'Jhn.1.1',
            'verse_end' => null,
            'target_phrase' => null,
            'strong_id' => null,
            'level' => 'verse',
            'title' => 'في البدء كان الكلمة — أزلية الابن',
            'body_html' => <<<'HTML'
<p>يقول الذهبي الفم: <em>«في البدء كان الكلمة»</em> — لم يقل <em>«صار»</em> بل <em>«كان»</em>، ليعلن أن الابن أزلي بلا بداية. كما أن الآب لم يبدأ، هكذا الابن أيضاً. قوله <em>«في البدء»</em> يرفع الذهن فوق كل خلق وزمن.</p>
<p>ويضيف: <em>«وكان الكلمة عند الله»</em> — يعلن التمايز الأقنومي دون انفصال في الجوهر، فالكلمة ليس صفة بل أقنوم حي متمايز <em>عند</em> الآب، وفي الوقت نفسه <em>«كان الكلمة الله»</em> بلا تحول أو امتزاج.</p>
<p class="text-sm text-stone-500">المصدر: عظة 4 على إنجيل يوحنا، <span dir="ltr">PG 59.30</span>.</p>
HTML,
            'source_book' => 'Patrologia Graeca',
        ]);

        // 2. Theological passage study — Fr. Matta El-Meskeen on Prologue Jhn.1.1-1.5
        Commentary::create([
            'author_name' => 'الأب متى المسكين',
            'era' => 'معاصر — القرن العشرون',
            'verse_start' => 'Jhn.1.1',
            'verse_end' => 'Jhn.1.5',
            'target_phrase' => null,
            'strong_id' => null,
            'level' => 'passage',
            'title' => 'مقدمة يوحنا (1:1-5) — النور والحياة',
            'body_html' => <<<'HTML'
<p>يرى الأب متى أن مقدمة يوحنا هي <strong>نشيد لاهوتي</strong> يلخّص التدبير كله: <em>الكلمة</em> هو نفسه <em>النور</em> الذي <em>«يضيء في الظلمة»</em>، والحياة التي <em>«كانت نور الناس»</em>. الظلمة لم تدركه لأنها رفضت أن تُضاء.</p>
<p>ويشرح أن <em>«به كان كل شيء»</em> ليست مجرد خلق أولي، بل <strong>حفظ مستمر</strong> للوجود — فبدون الكلمة ينهار كل كيان. لذلك فدخول الكلمة إلى العالم ليس إضافة خارجية، بل عودة الخالق إلى خليقته ليجددها.</p>
<p>وعن <em>«والظلمة لم تدركه»</em> يقول: الظلمة ليست كياناً بل <strong>غياب النور</strong> حين يرفض الإنسان أن يفتح عينيه.</p>
<p class="text-sm text-stone-500">المصدر: شرح إنجيل يوحنا، الجزء الأول، ص 45-58.</p>
HTML,
            'source_book' => 'المسكين - الإنجيل بحسب يوحنا',
        ]);

        // 3. Word-level note anchored to H7225 "في البدء" (רֵאשִׁית — rêʼshîyth)
        Commentary::create([
            'author_name' => 'دراسة معجمية — طاقم المنصة',
            'era' => 'معاصر',
            'verse_start' => 'Gen.1.1',
            'verse_end' => null,
            'target_phrase' => 'فِي الْبَدْءِ',
            'strong_id' => 'H7225',
            'level' => 'word',
            'title' => 'رِئشيت (H7225) — «في البدء» ليست زمناً بل رأساً',
            'body_html' => <<<'HTML'
<p><span style="font-family:'SBL Hebrew',serif" dir="ltr">רֵאשִׁית (rêʼshîyth) — H7225</span> من الجذر <span style="font-family:'SBL Hebrew',serif" dir="ltr">רֹאשׁ (H7218 — رأس)</span>. لا تعني مجرد <em>بداية زمنية</em> بل <strong>الرأس، الباكورة، الأفضل، الرئيس</strong>. في <em>أمثال 8:22</em> تُستخدم عن الحكمة: <em>«الرب قناني أول طريقه»</em>.</p>
<p>لذلك فـ <em>«في البدء خلق»</em> تعني: <strong>بالرأس/بالباكورة خلق الله</strong> — وهو ما فسّره الآباء مسيانياً: <em>بالمسيح، بوصفه رأس الخليقة وباكورة كل خليقة (كو 1:15)، خلق كل شيء</em>.</p>
<p>الترجمات العربية في الفانديك: <strong>أَوَائِلَ، أَوَّلُ، فِي ابْتِدَاءِ، مِنْ أَوَّلِ</strong> — كلها تدور حول معنى <em>الأولوية والسيادة</em> لا مجرد التسلسل.</p>
<ul class="list-disc ps-5 mt-2 text-sm">
<li>51 موضع في العهد القديم</li>
<li>النطق: <span style="font-family:Gentium,serif">rêʼshîyth</span> — رِيشِيت</li>
<li>الترجمة الحرفية: <em>في الرأس/في الباكورة</em></li>
</ul>
HTML,
            'source_book' => 'دراسة كلمة - H7225',
        ]);

        DB::statement('PRAGMA foreign_keys = ON');

        $this->command?->info('CommentarySeeder: 3 samples seeded (Chrysostom Jhn.1.1, Matta Prologue 1:1-5, H7225 word).');
    }
}
