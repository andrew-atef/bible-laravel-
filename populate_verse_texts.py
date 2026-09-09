import sqlite3
import pathlib

# تصحيح المسار ليكون database/biblical_core.db
db_path = pathlib.Path(__file__).parent / "database" / "biblical_core.db"
print(f"DB: {db_path}")

conn = sqlite3.connect(str(db_path))
cur = conn.cursor()

print("1. إضافة أعمدة النص الكامل لجدول verses...")
try:
    cur.execute("ALTER TABLE verses ADD COLUMN text_ar TEXT;")
    print(" - added text_ar")
except sqlite3.OperationalError as e:
    print(f" - text_ar already exists: {e}")
try:
    cur.execute("ALTER TABLE verses ADD COLUMN text_clean TEXT;")
    print(" - added text_clean")
except sqlite3.OperationalError as e:
    print(f" - text_clean already exists: {e}")

print("2. تجميع نصوص الآيات من الكلمات...")
cur.execute("""
    SELECT verse_osis, 
           GROUP_CONCAT(arabic_word, ' ') as full_ar,
           GROUP_CONCAT(arabic_clean, ' ') as full_clean
    FROM (
        SELECT verse_osis, arabic_word, arabic_clean 
        FROM arabic_concordance 
        ORDER BY verse_osis, word_pos
    )
    GROUP BY verse_osis;
""")
rows = cur.fetchall()

print(f"3. تحديث {len(rows):,} آية...")
# Use executemany with correct order: text_ar, text_clean, osis_id
cur.executemany("""
    UPDATE verses 
    SET text_ar = ?, text_clean = ? 
    WHERE osis_id = ?;
""", [(r[1], r[2], r[0]) for r in rows])

print(f" - updated {cur.rowcount} rows")

# إنشاء فهرس سريع للبحث النصي
print("4. إنشاء فهرس...")
cur.execute("CREATE INDEX IF NOT EXISTS idx_verse_text_clean ON verses(text_clean);")
cur.execute("CREATE INDEX IF NOT EXISTS idx_verse_text_ar ON verses(text_ar);")

conn.commit()

# تحقق
cur.execute("SELECT COUNT(*) FROM verses WHERE text_clean IS NOT NULL")
cnt = cur.fetchone()[0]
print(f" - verses with text_clean: {cnt}")

cur.execute("SELECT osis_id, text_ar, text_clean FROM verses WHERE osis_id='Gen.1.1'")
row = cur.fetchone()
if row:
    print(f"Sample Gen.1.1:\n  text_ar: {row[1]}\n  text_clean: {row[2]}")

conn.close()
print("✅ تم بنجاح! أصبحت كل آية تحتوي على نصها الكامل الآن.")
