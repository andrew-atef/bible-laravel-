# 📖 Biblical Core Database — التوثيق الشامل

> **الملف:** `database/biblical_core.db`  
> **المسار الكامل:** `C:\Users\Andrew Atef\Downloads\bible-app\database\biblical_core.db`  
> **الحجم:** 107.78 MB (113,016,832 bytes)  
> **تاريخ التعديل:** 1789040603.6081545  

## ⚙️ إعدادات SQLite (Pragmas)

| الخاصية | القيمة | الوصف |
|---|---|---|
| `journal_mode` | `wal` | |
| `synchronous` | `2` | |
| `foreign_keys` | `0` | |
| `encoding` | `UTF-8` | |
| `page_size` | `4096` | |
| `auto_vacuum` | `0` | |

## 📋 قائمة الجداول

| # | الجدول | النوع | عدد الصفوف | الوصف |
|---|---|---|---|---|
| 1 | `arabic_concordance` | table | 382,535 | 382,535 كلمة — ربط عربي/عبري/يوناني كلمة بكلمة |
| 2 | `books` | table | 66 | 66 سفر قانوني (39 عهد قديم + 27 عهد جديد) |
| 3 | `cache` | table | 3 | كاش لارافيل |
| 4 | `cache_locks` | table | 0 |  |
| 5 | `commentaries` | table | 4 | تفاسير آبائية ورعوية |
| 6 | `failed_jobs` | table | 0 |  |
| 7 | `job_batches` | table | 0 |  |
| 8 | `jobs` | table | 0 |  |
| 9 | `lexicons` | table | 14,298 | 14,298 مدخل سترونغ (عبري/آرامي/يوناني) |
| 10 | `migrations` | table | 11 | تتبع الترحيلات |
| 11 | `password_reset_tokens` | table | 0 |  |
| 12 | `sessions` | table | 8 |  |
| 13 | `sqlite_sequence` | table | 5 | عداد AUTOINCREMENT |
| 14 | `users` | table | 0 | مستخدمي لارافيل |
| 15 | `verses` | table | 31,102 | 31,102 آية — العمود الفقري القانوني |

## 🔑 الفهارس (Indexes)

| الفهرس | الجدول | الأعمدة | النوع |
|---|---|---|---|
| `idx_conc_strong` | `arabic_concordance` | `strong_id` | INDEX |
| `idx_conc_verse` | `arabic_concordance` | `verse_osis` | INDEX |
| `idx_verse_pos` | `arabic_concordance` | `verse_osis, word_pos` | UNIQUE |
| `books_book_number_unique` | `books` | `"book_number"` | UNIQUE |
| `books_code_unique` | `books` | `"code"` | UNIQUE |
| `books_is_indexed_index` | `books` | `is_indexed` | INDEX |
| `books_slug_unique` | `books` | `"slug"` | UNIQUE |
| `books_testament_book_number_index` | `books` | `"testament", "book_number"` | INDEX |
| `books_testament_index` | `books` | `"testament"` | INDEX |
| `sqlite_autoindex_cache_1` | `cache` | `autoindex` | PRIMARY KEY (auto) |
| `sqlite_autoindex_cache_locks_1` | `cache_locks` | `autoindex` | PRIMARY KEY (auto) |
| `commentaries_level_index` | `commentaries` | `"level"` | INDEX |
| `commentaries_strong_id_index` | `commentaries` | `"strong_id"` | INDEX |
| `commentaries_strong_id_level_index` | `commentaries` | `"strong_id", "level"` | INDEX |
| `commentaries_verse_start_index` | `commentaries` | `"verse_start"` | INDEX |
| `commentaries_verse_start_verse_end_index` | `commentaries` | `"verse_start", "verse_end"` | INDEX |
| `failed_jobs_uuid_unique` | `failed_jobs` | `"uuid"` | UNIQUE |
| `sqlite_autoindex_job_batches_1` | `job_batches` | `autoindex` | PRIMARY KEY (auto) |
| `jobs_queue_index` | `jobs` | `"queue"` | INDEX |
| `idx_lex_clean` | `lexicons` | `lemma_clean` | INDEX |
| `lexicons_is_indexed_index` | `lexicons` | `"is_indexed"` | INDEX |
| `sqlite_autoindex_lexicons_1` | `lexicons` | `autoindex` | PRIMARY KEY (auto) |
| `sqlite_autoindex_password_reset_tokens_1` | `password_reset_tokens` | `autoindex` | PRIMARY KEY (auto) |
| `sessions_last_activity_index` | `sessions` | `"last_activity"` | INDEX |
| `sessions_user_id_index` | `sessions` | `"user_id"` | INDEX |
| `sqlite_autoindex_sessions_1` | `sessions` | `autoindex` | PRIMARY KEY (auto) |
| `users_email_unique` | `users` | `"email"` | UNIQUE |
| `idx_verse_text_ar` | `verses` | `text_ar` | INDEX |
| `idx_verse_text_clean` | `verses` | `text_clean` | INDEX |
| `verses_book_chapter_verse_unique` | `verses` | `"book_id", "chapter", "verse"` | UNIQUE |
| `verses_book_id_chapter_verse_index` | `verses` | `"book_id", "chapter", "verse"` | INDEX |
| `verses_is_indexed_index` | `verses` | `"is_indexed"` | INDEX |
| `verses_osis_id_index` | `verses` | `"osis_id"` | INDEX |
| `verses_osis_id_unique` | `verses` | `"osis_id"` | UNIQUE |

---
## 🗃️ جدول `books`

```sql
CREATE TABLE "books" ("id" integer primary key autoincrement not null, "code" varchar not null, "testament" varchar check ("testament" in ('OT', 'NT')) not null, "book_number" integer not null, "name_ar" varchar not null, "name_en" varchar not null, "slug" varchar not null, "created_at" datetime, "updated_at" datetime, "is_indexed" tinyint(1) not null default '0')
```

| العمود | النوع | NOT NULL | افتراضي | PK | الوصف |
|---|---|---|---|---|---|
| `id` | `INTEGER` | ✓ | `` | ✓ | معرف داخلي AUTOINCREMENT |
| `code` | `varchar` | ✓ | `` |  | كود OSIS مثل Gen, Jhn, 1Sa (فريد) |
| `testament` | `varchar` | ✓ | `` |  | OT أو NT |
| `book_number` | `INTEGER` | ✓ | `` |  | 1..66 ترتيب قانوني (فريد) |
| `name_ar` | `varchar` | ✓ | `` |  | الاسم العربي مثل التكوين |
| `name_en` | `varchar` | ✓ | `` |  | الاسم الإنجليزي مثل Genesis |
| `slug` | `varchar` | ✓ | `` |  | للـ URL مثل al-takwin (فريد) |
| `created_at` | `datetime` |  | `` |  | لارافيل timestamps |
| `updated_at` | `datetime` |  | `` |  | لارافيل timestamps |
| `is_indexed` | `tinyint(1)` | ✓ | `'0'` |  | هل مفهرس لجوجل؟ (staged indexation) |

**فهارس `books`:**
- `books_testament_book_number_index`: `CREATE INDEX "books_testament_book_number_index" on "books" ("testament", "book_number")`
- `books_code_unique`: `CREATE UNIQUE INDEX "books_code_unique" on "books" ("code")`
- `books_testament_index`: `CREATE INDEX "books_testament_index" on "books" ("testament")`
- `books_book_number_unique`: `CREATE UNIQUE INDEX "books_book_number_unique" on "books" ("book_number")`
- `books_slug_unique`: `CREATE UNIQUE INDEX "books_slug_unique" on "books" ("slug")`
- `books_is_indexed_index`: `CREATE INDEX books_is_indexed_index ON books(is_indexed)`

**عدد الصفوف:** `66`

### أمثلة حقيقية — `books`

- **1. التكوين** (`Gen`) — Genesis — `testament=OT` `slug=al-takwin` `is_indexed=0`
- **2. الخروج** (`Exo`) — Exodus — `testament=OT` `slug=al-khuruj` `is_indexed=0`
- **3. اللاويين** (`Lev`) — Leviticus — `testament=OT` `slug=al-lawyin` `is_indexed=0`
- **Jhn (يوحنا) pilot:** `code=Jhn` `slug=yuhanna` `testament=NT` `book_number=43` `is_indexed=1`
---
## 🗃️ جدول `verses`

```sql
CREATE TABLE "verses" ("id" integer primary key autoincrement not null, "book_id" integer not null, "chapter" integer not null, "verse" integer not null, "osis_id" varchar not null, "created_at" datetime, "updated_at" datetime, "is_indexed" tinyint(1) not null default '0', text_ar TEXT, text_clean TEXT, foreign key("book_id") references "books"("id") on delete cascade on update cascade)
```

| العمود | النوع | NOT NULL | افتراضي | PK | الوصف |
|---|---|---|---|---|---|
| `id` | `INTEGER` | ✓ | `` | ✓ | معرف داخلي |
| `book_id` | `INTEGER` | ✓ | `` |  | FK → books.id (CASCADE) |
| `chapter` | `INTEGER` | ✓ | `` |  | رقم الإصحاح |
| `verse` | `INTEGER` | ✓ | `` |  | رقم الآية |
| `osis_id` | `varchar` | ✓ | `` |  | مرجع OSIS فريد مثل Gen.1.1 |
| `created_at` | `datetime` |  | `` |  | timestamps |
| `updated_at` | `datetime` |  | `` |  | timestamps |
| `is_indexed` | `tinyint(1)` | ✓ | `'0'` |  | مفهرس؟ |
| `text_ar` | `TEXT` |  | `` |  | نص الآية كامل مشكول (من arabic_concordance) |
| `text_clean` | `TEXT` |  | `` |  | نص مجرد للبحث (بدون تشكيل) |

**فهارس `verses`:**
- `verses_book_id_chapter_verse_index`: `CREATE INDEX "verses_book_id_chapter_verse_index" on "verses" ("book_id", "chapter", "verse")`
- `verses_osis_id_index`: `CREATE INDEX "verses_osis_id_index" on "verses" ("osis_id")`
- `verses_book_chapter_verse_unique`: `CREATE UNIQUE INDEX "verses_book_chapter_verse_unique" on "verses" ("book_id", "chapter", "verse")`
- `verses_osis_id_unique`: `CREATE UNIQUE INDEX "verses_osis_id_unique" on "verses" ("osis_id")`
- `verses_is_indexed_index`: `CREATE INDEX "verses_is_indexed_index" on "verses" ("is_indexed")`
- `idx_verse_text_clean`: `CREATE INDEX idx_verse_text_clean ON verses(text_clean)`
- `idx_verse_text_ar`: `CREATE INDEX idx_verse_text_ar ON verses(text_ar)`

**مفاتيح خارجية `verses`:**
- `book_id` → `books`.`id` (on_delete=CASCADE, on_update=CASCADE)

**عدد الصفوف:** `31,102`

### أمثلة حقيقية — `verses`

- **أول آية في الكتاب (Gen.1.1):** `id=1` `book_id=1` (التكوين Gen) `chapter=1` `verse=1` `osis_id=Gen.1.1` `is_indexed=1`
  - `text_ar`: `فِي الْبَدْءِ خَلَقَ اللهُ السَّمَاوَاتِ وَالأَرْضَ.`
  - `text_clean`: `في البدء خلق الله السماوات والارض.`
- **Jhn.1.1:** `osis_id=Jhn.1.1` `text_ar=فِي الْبَدْءِ كَانَ الْكَلِمَةُ، وَالْكَلِمَةُ كَانَ عِنْدَ اللهِ، وَكَانَ الْكَلِمَةُ اللهَ.` `is_indexed=1`
- **NULL check:** `0` آية بدون `text_clean` (يجب أن يكون 0 بعد السكربت)
---
## 🗃️ جدول `lexicons`

```sql
CREATE TABLE lexicons (
    strong_id TEXT PRIMARY KEY,
    language TEXT CHECK(language IN ('hebrew', 'aramaic', 'greek')),
    lemma TEXT NOT NULL,
    lemma_clean TEXT NOT NULL,
    transliteration TEXT,
    pronunciation TEXT,
    part_of_speech TEXT,
    word_origin TEXT,
    root_strong TEXT,
    strongs_definition TEXT,
    kjv_translations TEXT,
    outline_json TEXT
, "is_indexed" tinyint(1) not null default '0', "strongs_definition_ar" text, "kjv_translations_ar" text, "outline_json_ar" text, "short_gloss_ar" varchar, "translated_at" datetime)
```

| العمود | النوع | NOT NULL | افتراضي | PK | الوصف |
|---|---|---|---|---|---|
| `strong_id` | `TEXT` |  | `` | ✓ | PK نصي مثل H7225, G2424 (H1-8674, G1-5624) |
| `language` | `TEXT` |  | `` |  | hebrew | aramaic | greek |
| `lemma` | `TEXT` | ✓ | `` |  | اللفظ الأصلي بالحركات (רֵאשִׁית) |
| `lemma_clean` | `TEXT` | ✓ | `` |  | مجرد للبحث (ראשית) |
| `transliteration` | `TEXT` |  | `` |  | نقحرة لاتينية rêʼshîyth |
| `pronunciation` | `TEXT` |  | `` |  | النطق ray-sheeth' |
| `part_of_speech` | `TEXT` |  | `` |  | نوع الكلمة Noun Feminine إلخ |
| `word_origin` | `TEXT` |  | `` |  | اشتقاق مثل from the same as H7218 |
| `root_strong` | `TEXT` |  | `` |  | جذر self-ref مثل H7218 |
| `strongs_definition` | `TEXT` |  | `` |  | تعريف سترونغ الإنجليزي المطول |
| `kjv_translations` | `TEXT` |  | `` |  | ترجمات KJV الإنجليزية |
| `outline_json` | `TEXT` |  | `` |  | JSON مصفوفة تفريعات المعنى |
| `is_indexed` | `tinyint(1)` | ✓ | `'0'` |  | مفهرس؟ |
| `strongs_definition_ar` | `TEXT` |  | `` |  | ترجمة عربية لاهوتية (Gemini) |
| `kjv_translations_ar` | `TEXT` |  | `` |  | ترجمات عربية |
| `outline_json_ar` | `TEXT` |  | `` |  | تفريعات عربية JSON |
| `short_gloss_ar` | `varchar` |  | `` |  | 1-3 معاني عربية مختصرة |
| `translated_at` | `datetime` |  | `` |  | وقت الترجمة |

**فهارس `lexicons`:**
- `sqlite_autoindex_lexicons_1`: `None`
- `idx_lex_clean`: `CREATE INDEX idx_lex_clean ON lexicons(lemma_clean)`
- `lexicons_is_indexed_index`: `CREATE INDEX "lexicons_is_indexed_index" on "lexicons" ("is_indexed")`

**عدد الصفوف:** `14,298`

### أمثلة حقيقية — `lexicons`

- **H7225** `lemma=רֵאשִׁית` `lemma_clean=ראשית` `transliteration=rêʼshîyth` `pronunciation=ray-sheeth'` `language=hebrew` `pos=Noun Feminine` `is_indexed=1`
  - `strongs_definition`: `the first, in place, time, order or rank (specifically, a firstfruit)...`
  - `strongs_definition_ar`: `الأول، في المكان أو الزمان أو الترتيب أو الرتبة (تحديداً، باكورة أو ثمر أول)`
  - `kjv_translations_ar`: `البداية، الأعظم، الأول، الباكورات، الجزء الأول، الشيء الرئيسي`
  - `outline_json_ar`: `["1) \u0623\u0648\u0644\u060c \u0628\u062f\u0627\u064a\u0629\u060c \u0623\u0641\`
  - `short_gloss_ar`: `بداية، أول، باكورة، أفضل`
  - `translated_at`: `2026-09-07 17:57:09`
- **H8414** `lemma=תֹּהוּ` `lemma_clean=תהו` `transliteration=tôhûw` `pronunciation=to'-hoo` `language=hebrew` `pos=Noun Masculine` `is_indexed=1`
  - `strongs_definition`: `a desolation (of surface), i.e. desert; figuratively, a worthless thing; adverbially, in vain...`
  - `strongs_definition_ar`: `NULL`
  - `kjv_translations_ar`: `NULL`
  - `outline_json_ar`: `NULL`
  - `short_gloss_ar`: `NULL`
  - `translated_at`: `NULL`
- **G2424** `lemma=Ἰησοῦς` `lemma_clean=Ιησους` `transliteration=Iēsoûs` `pronunciation=ee-ay-sooce'` `language=greek` `pos=` `is_indexed=1`
  - `strongs_definition`: `Jesus (i.e. Jehoshua), the name of our Lord
 and two (three) other Israelites...`
  - `strongs_definition_ar`: `NULL`
  - `kjv_translations_ar`: `NULL`
  - `outline_json_ar`: `NULL`
  - `short_gloss_ar`: `NULL`
  - `translated_at`: `NULL`
- **G3056** `lemma=λόγος` `lemma_clean=λογος` `transliteration=lógos` `pronunciation=log'-os` `language=greek` `pos=` `is_indexed=1`
  - `strongs_definition`: `something said (including the thought); by implication, a
 topic (subject of discourse), also reason...`
  - `strongs_definition_ar`: `NULL`
  - `kjv_translations_ar`: `NULL`
  - `outline_json_ar`: `NULL`
  - `short_gloss_ar`: `NULL`
  - `translated_at`: `NULL`
- **H410** `lemma=אֵל` `lemma_clean=אל` `transliteration=ʼêl` `pronunciation=ale` `language=hebrew` `pos=Noun Masculine` `is_indexed=0`
  - `strongs_definition`: `strength; as adjective, mighty; especially the Almighty (but used also of any deity)...`
  - `strongs_definition_ar`: `NULL`
  - `kjv_translations_ar`: `NULL`
  - `outline_json_ar`: `NULL`
  - `short_gloss_ar`: `NULL`
  - `translated_at`: `NULL`
- **NULL short_gloss_ar:** `14,297` / 14,298 (غير مترجم بعد)
---
## 🗃️ جدول `arabic_concordance`

```sql
CREATE TABLE arabic_concordance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    verse_osis TEXT NOT NULL,
    word_pos INTEGER NOT NULL,
    strong_id TEXT NOT NULL,       -- The PRIMARY lexical Strong number (H1-H8674 or G1-G5624). Never H9000+!
    source_word TEXT NOT NULL,     -- Vocalized original word (בְּרֵאשִׁ֖ית, תֹ֨הוּ֙, Βίβλος)
    source_clean TEXT NOT NULL,    -- Diacritics stripped for search
    transliteration TEXT NOT NULL, -- Latin phonetic transliteration (bərêšîṯ, ṯōhū, Biblos)
    arabic_word TEXT NOT NULL,     -- Vocalized SVD Arabic word (فِي الْبَدْءِ, خَرِبَةً, كِتَابُ)
    arabic_clean TEXT NOT NULL,    -- Arabic stripped of Tashkeel
    gloss_en TEXT,                 -- English short meaning (beginning, formless, book)
    morphology TEXT,               -- Grammar parsing string (Prep-b | N-fs, N-ms, N-NSF)
    all_strongs TEXT               -- Full raw string of IDs (e.g. 'H9003;H7225')
)
```

| العمود | النوع | NOT NULL | افتراضي | PK | الوصف |
|---|---|---|---|---|---|
| `id` | `INTEGER` |  | `` | ✓ | PK AUTOINCREMENT |
| `verse_osis` | `TEXT` | ✓ | `` |  | مرجع الآية مثل Gen.1.1 (مفهرس) |
| `word_pos` | `INTEGER` | ✓ | `` |  | ترتيب الكلمة في الآية (1..n) — مع UNIQUE(verse_osis, word_pos) |
| `strong_id` | `TEXT` | ✓ | `` |  | رقم سترونغ الأساسي <9000 (مفهرس) |
| `source_word` | `TEXT` | ✓ | `` |  | الكلمة الأصلية بالحركات בְּרֵאשִׁית |
| `source_clean` | `TEXT` | ✓ | `` |  | مجردة בראשית |
| `transliteration` | `TEXT` | ✓ | `` |  | نقحرة لاتينية be.;re.Shit |
| `arabic_word` | `TEXT` | ✓ | `` |  | الكلمة العربية بالحركات فِي الْبَدْءِ |
| `arabic_clean` | `TEXT` | ✓ | `` |  | مجردة في البدء |
| `gloss_en` | `TEXT` |  | `` |  | معنى إنجليزي مختصر in;{beginning} |
| `morphology` | `TEXT` |  | `` |  | تحليل صرفي HR/Ncfsa |
| `all_strongs` | `TEXT` |  | `` |  | كل الأرقام الخام مثل H9003;H7225 |

**فهارس `arabic_concordance`:**
- `idx_conc_strong`: `CREATE INDEX idx_conc_strong ON arabic_concordance(strong_id)`
- `idx_conc_verse`: `CREATE INDEX idx_conc_verse ON arabic_concordance(verse_osis)`
- `idx_verse_pos`: `CREATE UNIQUE INDEX idx_verse_pos ON arabic_concordance(verse_osis, word_pos)`

**عدد الصفوف:** `382,535`

### أمثلة حقيقية — `arabic_concordance`

- **أول 5 كلمات في التكوين (Gen.1.1):**
  - `pos=1` `strong_id=H7225` `source_word=בְּרֵאשִׁ֖ית` `source_clean=בראשית` `transliteration=be.;re.Shit` `arabic_word=فِي الْبَدْءِ` `arabic_clean=في البدء` `gloss_en=in;{beginning}` `morphology=HR/Ncfsa` `all_strongs=H9003;H7225`
  - `pos=3` `strong_id=H1254` `source_word=בָּרָ֣א` `source_clean=ברא` `transliteration=ba.Ra'` `arabic_word=خَلَقَ` `arabic_clean=خلق` `gloss_en={to create}` `morphology=HVqp3ms` `all_strongs=H1254`
  - `pos=4` `strong_id=H430` `source_word=אֱלֹהִ֑ים` `source_clean=אלהים` `transliteration='E.lo.Him` `arabic_word=اللهُ` `arabic_clean=الله` `gloss_en={God}` `morphology=HNcmpa` `all_strongs=H430`
  - `pos=5` `strong_id=H8064` `source_word=הַשָּׁמַ֖יִם` `source_clean=השמים` `transliteration=ha.;sha.Ma.yim` `arabic_word=السَّمَاوَاتِ` `arabic_clean=السماوات` `gloss_en=the;{heaven}` `morphology=HTd/Ncmpa` `all_strongs=H9009;H8064`
  - `pos=6` `strong_id=H776` `source_word=הָאָֽרֶץ` `source_clean=הארץ` `transliteration=ha.;'A.retz` `arabic_word=وَالأَرْضَ.` `arabic_clean=والارض.` `gloss_en=the;{country,_planet}` `morphology=HTd/Ncfsa` `all_strongs=H9009;H776`
- **مثال NULL:** لا يوجد gloss_en NULL (كلها معبأة أو بعضها)
- **بدون morphology:** `0` صف
---
## 🗃️ جدول `commentaries`

```sql
CREATE TABLE "commentaries" ("id" integer primary key autoincrement not null, "author_name" varchar not null, "era" varchar, "verse_start" varchar not null, "verse_end" varchar, "target_phrase" varchar, "strong_id" varchar, "level" varchar check ("level" in ('passage', 'verse', 'word')) not null, "title" varchar, "body_html" text, "source_book" varchar, "created_at" datetime, "updated_at" datetime)
```

| العمود | النوع | NOT NULL | افتراضي | PK | الوصف |
|---|---|---|---|---|---|
| `id` | `INTEGER` | ✓ | `` | ✓ | PK |
| `author_name` | `varchar` | ✓ | `` |  | المؤلف مثل القديس يوحنا ذهبي الفم |
| `era` | `varchar` |  | `` |  | الحقبة مثل آبائي — القرن الرابع |
| `verse_start` | `varchar` | ✓ | `` |  | بداية النطاق OSIS مفهرس |
| `verse_end` | `varchar` |  | `` |  | نهاية النطاق (NULL للمفرد) |
| `target_phrase` | `varchar` |  | `` |  | العبارة المستهدفة مثل فِي الْبَدْءِ |
| `strong_id` | `varchar` |  | `` |  | ربط معجمي H7225 مفهرس |
| `level` | `varchar` | ✓ | `` |  | passage | verse | word |
| `title` | `varchar` |  | `` |  | عنوان التفسير |
| `body_html` | `TEXT` |  | `` |  | نص HTML غني بالعربية |
| `source_book` | `varchar` |  | `` |  | المصدر Patrologia Graeca |
| `created_at` | `datetime` |  | `` |  | timestamps |
| `updated_at` | `datetime` |  | `` |  | timestamps |

**فهارس `commentaries`:**
- `commentaries_verse_start_verse_end_index`: `CREATE INDEX "commentaries_verse_start_verse_end_index" on "commentaries" ("verse_start", "verse_end")`
- `commentaries_strong_id_level_index`: `CREATE INDEX "commentaries_strong_id_level_index" on "commentaries" ("strong_id", "level")`
- `commentaries_verse_start_index`: `CREATE INDEX "commentaries_verse_start_index" on "commentaries" ("verse_start")`
- `commentaries_strong_id_index`: `CREATE INDEX "commentaries_strong_id_index" on "commentaries" ("strong_id")`
- `commentaries_level_index`: `CREATE INDEX "commentaries_level_index" on "commentaries" ("level")`

**عدد الصفوف:** `4`

### أمثلة حقيقية — `commentaries`

- **#1** `author=النص الموضح` `era=معاصر` `verse_start=Gen.1.1` `verse_end=Gen.1.1` `strong_id=H7225` `level=word` `title=في البدء - رِئشيت`
  - `target_phrase`: `فِي الْبَدْءِ`
  - `body_html` (مقتطف 80): `<p>كلمة <strong>רֵאשִׁית (H7225)</strong> تعني البداية، الأول، رأس. في السياق ال...`
- **#2** `author=القديس يوحنا ذهبي الفم` `era=آبائي — القرن الرابع` `verse_start=Jhn.1.1` `verse_end=None` `strong_id=None` `level=verse` `title=في البدء كان الكلمة — أزلية الابن`
  - `target_phrase`: `None`
  - `body_html` (مقتطف 80): `<p>يقول الذهبي الفم: <em>«في البدء كان الكلمة»</em> — لم يقل <em>«صار»</em> بل <...`
  - `strong_id` = `NULL` (تعليق على مقطع، ليس كلمة)
  - `verse_end` = `NULL` (تعليق على آية مفردة)
- **#3** `author=الأب متى المسكين` `era=معاصر — القرن العشرون` `verse_start=Jhn.1.1` `verse_end=Jhn.1.5` `strong_id=None` `level=passage` `title=مقدمة يوحنا (1:1-5) — النور والحياة`
  - `target_phrase`: `None`
  - `body_html` (مقتطف 80): `<p>يرى الأب متى أن مقدمة يوحنا هي <strong>نشيد لاهوتي</strong> يلخّص التدبير كله...`
  - `strong_id` = `NULL` (تعليق على مقطع، ليس كلمة)
---
## 🔍 أمثلة استعلامات حقيقية

```sql
-- أول آية مع نصها الكامل
SELECT v.osis_id, v.text_ar, v.text_clean, b.name_ar
FROM verses v JOIN books b ON b.id=v.book_id
WHERE v.osis_id='Gen.1.1';
-- Result: Gen.1.1 | التكوين | فِي الْبَدْءِ خَلَقَ اللهُ السَّمَاوَاتِ وَالأَرْضَ.

-- أول 5 كلمات في تكوين مع بياناتها
SELECT verse_osis, word_pos, arabic_word, source_word, strong_id, transliteration
FROM arabic_concordance WHERE verse_osis='Gen.1.1' ORDER BY word_pos LIMIT 5;

-- بحث عبارة كاملة مع تظليل (يستخدم text_clean)
SELECT osis_id, text_ar FROM verses WHERE text_clean LIKE '%في البدء خلق%';
-- Result: Gen.1.1 | فِي الْبَدْءِ خَلَقَ اللهُ السَّمَاوَاتِ وَالأَرْضَ.

-- معجم H7225 مع الترجمة العربية
SELECT strong_id, lemma, transliteration, short_gloss_ar, strongs_definition_ar FROM lexicons WHERE strong_id='H7225';
-- Result: H7225 | بداية، أول، باكورة، أفضل
```

---
*تم توليد هذا الملف تلقائياً من `database/biblical_core.db` — كل الأمثلة حقيقية 100%، وحتى قيم `NULL` موضحة كما هي.*