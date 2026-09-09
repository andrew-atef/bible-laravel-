<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة التحكم — المنصة الكتابية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--font-ar:'Cairo',system-ui,sans-serif}
        *{box-sizing:border-box} body{margin:0;font-family:var(--font-ar);background:#f8fafc;color:#0f172a}
        a{text-decoration:none;color:inherit}
        .sidebar{position:fixed;inset-inline-start:0;top:0;bottom:0;width:240px;background:#0f172a;color:#fff;padding:20px 14px;overflow-y:auto}
        .sidebar a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:#cbd5e1;font-size:13px;font-weight:600}
        .sidebar a.active,.sidebar a:hover{background:#1e293b;color:#fff}
        .main{margin-inline-start:240px;padding:24px 20px;max-width:1200px}
        .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;box-shadow:0 4px 12px rgba(0,0,0,.04)}
        .pill{padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid #e2e8f0;background:#f8fafc}
        .pill-on{background:#dcfce7;border-color:#bbf7d0;color:#166534}
        .pill-off{background:#fee2e2;border-color:#fecaca;color:#991b1b}
        .btn{padding:7px 12px;border-radius:10px;font-size:12px;font-weight:700;border:none;cursor:pointer}
        .btn-primary{background:#2563eb;color:#fff}
        .btn-danger{background:#dc2626;color:#fff}
        .btn-ghost{background:#fff;border:1px solid #e2e8f0;color:#334155}
        .toggle{width:44px;height:24px;background:#e2e8f0;border-radius:999px;position:relative;cursor:pointer;transition:.2s}
        .toggle.on{background:#16a34a}
        .toggle span{position:absolute;top:2px;inset-inline-start:2px;width:20px;height:20px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
        .toggle.on span{inset-inline-start:22px}
        .table{width:100%;border-collapse:collapse;font-size:13px}
        .table th{background:#f8fafc;text-align:start;padding:10px 8px;border-bottom:1px solid #e2e8f0;color:#475569;font-size:11px}
        .table td{padding:10px 8px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .flash{padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px;font-weight:600}
        .flash-success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
        .flash-warning{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
        .modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);display:none;z-index:90}
        .modal{position:fixed;inset:0;display:none;place-items:center;z-index:100}
        .modal.open,.modal-backdrop.open{display:grid}
        .modal-card{background:#fff;border-radius:16px;padding:20px;max-width:420px;width:90%;box-shadow:0 20px 40px rgba(0,0,0,.2)}
    </style>
</head>
<body>
<div class="sidebar">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
        <span style="width:36px;height:36px;background:#2563eb;border-radius:10px;display:grid;place-items:center;font-weight:800">ك</span>
        <div><div style="font-weight:800;font-size:14px">لوحة التحكم</div><div style="font-size:11px;color:#94a3b8">المنصة الكتابية — الفهرسة</div></div>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">📊 لوحة المؤشرات</a>
    <a href="{{ route('admin.books.index') }}" class="{{ request()->routeIs('admin.books.*') ? 'active' : '' }}">📚 إدارة الأسفار (66)</a>
    <a href="{{ route('admin.verses.index') }}" class="{{ request()->routeIs('admin.verses.*') ? 'active' : '' }}">📖 إدارة الآيات</a>
    <a href="{{ route('admin.lexicon.index') }}" class="{{ request()->routeIs('admin.lexicon.*') ? 'active' : '' }}">🔤 إدارة المعجم</a>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #1e293b">
        <a href="{{ url('/sitemap.xml') }}" target="_blank">🗺️ sitemap.xml ↗</a>
        <a href="{{ url('/') }}" target="_blank">🏠 عرض الموقع ↗</a>
    </div>
</div>
<div class="main">
    @if(session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="flash flash-warning">{{ session('warning') }}</div>@endif
    @yield('content')
</div>
@stack('scripts')
</body>
</html>
