@extends('admin.layout')
@section('content')
<h1 style="font-size:22px;font-weight:800;margin:0">لوحة مؤشرات الفهرسة المرحلية</h1>
<p style="color:#64748b;font-size:13px;margin:6px 0 16px">تحكم مرحلي لتجنب عقوبة Scaled Content Abuse — الشهر الأول ~40 صفحة فقط مفهرسة</p>

<div class="card" style="border:2px solid #2563eb">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
            <div style="font-size:11px;font-weight:700;letter-spacing:.06em;color:#2563eb">إجمالي الصفحات المفهرسة في جوجل</div>
            <div style="font-size:36px;font-weight:800;color:#0f172a">{{ $totalIndexed }}</div>
            <div style="font-size:11px;color:#64748b">صفحة مفهرسة (تشمل الرئيسية + كتب + آيات + معجم)</div>
        </div>
        <a href="{{ url('/sitemap.xml') }}" target="_blank" class="btn btn-primary" style="text-decoration:none">رابط فحص خريطة الموقع الحية: sitemap.xml ↗</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:16px">
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:center">
            <div style="font-size:11px;color:#64748b">كتب مفهرسة</div>
            <div style="font-size:20px;font-weight:800">{{ $booksIndexed }} / 66</div>
            <div style="margin-top:6px;height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div style="height:100%;background:#2563eb;width:{{ $booksIndexed/66*100 }}%"></div></div>
        </div>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:center">
            <div style="font-size:11px;color:#64748b">آيات مفهرسة</div>
            <div style="font-size:20px;font-weight:800">{{ number_format($versesIndexed) }} / 31,102</div>
            <div style="margin-top:6px;height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div style="height:100%;background:#16a34a;width:{{ $versesIndexed/31102*100 }}%"></div></div>
        </div>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:center">
            <div style="font-size:11px;color:#64748b">كلمات معجمية مفهرسة</div>
            <div style="font-size:20px;font-weight:800">{{ number_format($lexIndexed) }} / 14,298</div>
            <div style="margin-top:6px;height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div style="height:100%;background:#dc2626;width:{{ $lexIndexed/14298*100 }}%"></div></div>
        </div>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
        <a href="{{ route('admin.books.index') }}" class="pill">إدارة الأسفار</a>
        <a href="{{ route('admin.verses.index') }}" class="pill">إدارة الآيات</a>
        <a href="{{ route('admin.lexicon.index') }}" class="pill">إدارة المعجم</a>
        <a href="{{ url('/sitemap-core.xml') }}" target="_blank" class="pill">sitemap-core.xml</a>
        <a href="{{ url('/sitemap-verses.xml') }}" target="_blank" class="pill">sitemap-verses.xml</a>
        <a href="{{ url('/sitemap-lexicon.xml') }}" target="_blank" class="pill">sitemap-lexicon.xml</a>
    </div>
</div>

<div class="card" style="margin-top:14px;background:#fffbeb;border-color:#fde68a">
    <div style="font-weight:700;font-size:13px;color:#92400e">⚠️ تنبيه مرحلي</div>
    <p style="font-size:12px;color:#92400e;margin:6px 0 0;line-height:1.7">الفهرسة المرحلية تحمي الموقع من عقوبة المحتوى المقيس. الشهر الأول ~40 صفحة فقط (يوحنا + تكوين 1 + 20 كلمة لاهوتية). تفعيل عهد كامل (OT/NT) بدون مراجعة يعرّض الموقع للخطر.</p>
</div>
@endsection
