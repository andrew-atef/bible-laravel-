@extends('admin.layout')
@section('content')
<h1 style="font-size:18px;font-weight:800;margin:0">إدارة الأسفار — 66 سفر</h1>
<p style="color:#64748b;font-size:12px;margin:6px 0 14px">تحكم مرحلي — تفعيل سفر كامل يفهرس جميع آياته. إلغاء الفهرسة يخفيه من جوجل فوراً مع تفريغ كاش sitemap.</p>

<form id="bulkForm" method="POST" action="{{ route('admin.books.bulkEnable') }}" style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    @csrf
    <button type="button" onclick="selectAll(true)" class="btn btn-ghost">تحديد الكل</button>
    <button type="button" onclick="selectAll(false)" class="btn btn-ghost">إلغاء التحديد</button>
    <button type="submit" class="btn btn-primary" onclick="return confirmBulk('تفعيل فهرسة الأسفار المحددة مع جميع آياتها؟')">✅ تفعيل المحدد</button>
    <button type="button" onclick="submitBulk('{{ route('admin.books.bulkDisable') }}')" class="btn btn-danger">🚫 إلغاء المحدد</button>
    <span id="selCount" style="font-size:12px;color:#64748b"></span>
</form>

<div class="card" style="padding:0;overflow:hidden">
<table class="table">
<thead>
<tr>
    <th><input type="checkbox" id="checkAll" onchange="selectAll(this.checked)"></th>
    <th>#</th>
    <th>اسم السفر</th>
    <th>Code</th>
    <th>العهد</th>
    <th>مفعل في جوجل؟</th>
    <th>آيات</th>
    <th>إجراءات</th>
</tr>
</thead>
<tbody>
@foreach($books as $book)
<tr>
    <td><input type="checkbox" name="ids[]" value="{{ $book->id }}" form="bulkForm" class="rowCheck" onchange="updateCount()"></td>
    <td>{{ $book->book_number }}</td>
    <td><div style="font-weight:700">{{ $book->name_ar }}</div><div style="font-size:11px;color:#64748b">{{ $book->name_en }}</div></td>
    <td><span class="pill" style="font-family:monospace">{{ $book->code }}</span></td>
    <td><span class="pill" style="background:{{ $book->testament==='OT' ? '#fef3c7' : '#dbeafe' }}">{{ $book->testament }}</span></td>
    <td>
        <form method="POST" action="{{ route('admin.books.toggle', $book) }}" style="display:inline">
            @csrf @method('PATCH')
            <button type="submit" class="toggle {{ $book->is_indexed ? 'on' : '' }}" title="{{ $book->is_indexed ? 'مفعل في جوجل' : 'noindex' }}"><span></span></button>
        </form>
        <span class="pill {{ $book->is_indexed ? 'pill-on' : 'pill-off' }}" style="margin-inline-start:6px">{{ $book->is_indexed ? 'مفعل في جوجل' : 'noindex' }}</span>
    </td>
    <td>{{ $book->verses()->count() }}</td>
    <td style="display:flex;gap:6px;flex-wrap:wrap">
        <form method="POST" action="{{ route('admin.books.enableBook', $book) }}" onsubmit="return confirm('تفعيل فهرسة السفر كاملاً: {{ $book->name_ar }} مع جميع آياته ({{ $book->verses()->count() }} آية)؟')">
            @csrf @method('PATCH')
            <button class="btn btn-primary" style="padding:5px 8px">تفعيل كاملاً</button>
        </form>
        <form method="POST" action="{{ route('admin.books.disableBook', $book) }}" onsubmit="return confirm('إلغاء فهرسة {{ $book->name_ar }} وإخفاء جميع آياته من جوجل؟')">
            @csrf @method('PATCH')
            <button class="btn btn-danger" style="padding:5px 8px">إلغاء الفهرسة</button>
        </form>
    </td>
</tr>
@endforeach
</tbody>
</table>
</div>

<div class="card" style="margin-top:12px;background:#fffbeb;border-color:#fde68a">
    <div style="font-weight:700;font-size:12px;color:#92400e">⚠️ تحذير قبل تفعيل عهد كامل</div>
    <p style="font-size:12px;color:#92400e;margin:6px 0 0">تفعيل العهد القديم (39 سفر) أو الجديد (27 سفر) دفعة واحدة قد يسبب عقوبة Scaled Content. استخدم التحديد المتعدد بحذر.</p>
    <div style="margin-top:8px;display:flex;gap:8px">
        <button class="btn btn-ghost" onclick="if(confirm('تفعيل العهد القديم كاملاً (39 سفر + ~23k آية)؟ قد يعرّض الموقع للخطر. متابعة؟')){ selectTestament('OT'); document.getElementById('bulkForm').submit(); }">تفعيل OT كامل</button>
        <button class="btn btn-ghost" onclick="if(confirm('تفعيل العهد الجديد كاملاً (27 سفر)؟')){ selectTestament('NT'); document.getElementById('bulkForm').submit(); }">تفعيل NT كامل</button>
    </div>
</div>

@push('scripts')
<script>
function selectAll(v){document.querySelectorAll('.rowCheck').forEach(c=>c.checked=v);document.getElementById('checkAll').checked=v;updateCount()}
function selectTestament(t){document.querySelectorAll('.rowCheck').forEach(c=>{const row=c.closest('tr'); const testament=row.querySelector('td:nth-child(5) span').textContent.trim(); c.checked=(testament===t)});updateCount()}
function updateCount(){const n=document.querySelectorAll('.rowCheck:checked').length;document.getElementById('selCount').textContent=n?`محدد: ${n}`:''}
function confirmBulk(msg){ if(document.querySelectorAll('.rowCheck:checked').length===0){alert('اختر سفراً أولاً');return false} return confirm(msg) }
function submitBulk(url){ const f=document.getElementById('bulkForm'); if(document.querySelectorAll('.rowCheck:checked').length===0){alert('اختر سفراً');return} if(!confirm('إلغاء فهرسة المحدد؟')) return; f.action=url; f.submit(); }
</script>
@endpush
@endsection
