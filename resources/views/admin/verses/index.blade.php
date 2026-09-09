@extends('admin.layout')
@section('content')
<h1 style="font-size:18px;font-weight:800;margin:0">إدارة الآيات — الفهرسة المرحلية</h1>
<p style="color:#64748b;font-size:12px;margin:6px 0 12px">فلترة بالسفر/الإصحاح، تفعيل فردي أو جماعي. التفعيل يفهرس الآية في sitemap-verses.xml.</p>

<form method="GET" action="{{ route('admin.verses.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">
    <div>
        <label style="font-size:11px;font-weight:700;color:#475569">السفر</label>
        <select name="book_id" style="display:block;padding:7px 10px;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;min-width:160px">
            <option value="">كل الأسفار</option>
            @foreach($books as $b)
                <option value="{{ $b->id }}" {{ request('book_id')==$b->id?'selected':'' }}>{{ $b->name_ar }} ({{ $b->code }})</option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="font-size:11px;font-weight:700;color:#475569">الإصحاح</label>
        <input type="number" name="chapter" value="{{ request('chapter') }}" placeholder="1" style="display:block;padding:7px 10px;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;width:90px">
    </div>
    <div>
        <label style="font-size:11px;font-weight:700;color:#475569">بحث OSIS</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Gen.1.1" style="display:block;padding:7px 10px;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;width:140px">
    </div>
    <button class="btn btn-primary" style="height:36px">فلترة</button>
    <a href="{{ route('admin.verses.index') }}" class="btn btn-ghost" style="height:36px;display:inline-flex;align-items:center">إعادة</a>
</form>

<form id="bulkForm" method="POST" action="{{ route('admin.verses.bulkEnable') }}" style="margin-bottom:10px;display:flex;gap:8px;align-items:center">
    @csrf
    <button type="button" onclick="selectAll(true)" class="btn btn-ghost">تحديد الكل</button>
    <button type="button" onclick="selectAll(false)" class="btn btn-ghost">إلغاء</button>
    <button type="submit" class="btn btn-primary" onclick="return confirmBulk('تفعيل الفهرسة للآيات المحددة؟')">✅ تفعيل المحدد</button>
    <button type="button" onclick="submitBulk('{{ route('admin.verses.bulkDisable') }}')" class="btn btn-danger">🚫 إلغاء المحدد</button>
    <span id="selCount" style="font-size:12px;color:#64748b"></span>
</form>

<div class="card" style="padding:0;overflow:hidden">
<table class="table">
<thead>
<tr>
    <th><input type="checkbox" id="checkAll" onchange="selectAll(this.checked)"></th>
    <th>OSIS</th>
    <th>السفر</th>
    <th>النص العربي (أول كلمة)</th>
    <th>سترونغ أساسي</th>
    <th>مفعل في جوجل؟</th>
    <th>إجراء</th>
</tr>
</thead>
<tbody>
@foreach($verses as $verse)
@php $tok = $firstTokens[$verse->osis_id] ?? null; @endphp
<tr>
    <td><input type="checkbox" name="ids[]" value="{{ $verse->id }}" form="bulkForm" class="rowCheck" onchange="updateCount()"></td>
    <td><a href="{{ url('/verse/'.$verse->osis_id) }}" target="_blank" style="font-family:monospace;font-weight:700;color:#2563eb">{{ $verse->osis_id }}</a></td>
    <td>{{ $verse->book->name_ar ?? $verse->book_id }} <span style="color:#94a3b8">{{ $verse->book->code ?? '' }}</span> {{ $verse->chapter }}:{{ $verse->verse }}</td>
    <td style="font-family:'Amiri',serif">{{ $tok->arabic_word ?? '—' }} <span style="color:#94a3b8;font-size:11px">{{ $tok->strong_id ?? '' }}</span></td>
    <td><span class="pill" style="font-family:monospace">{{ $tok->strong_id ?? '—' }}</span></td>
    <td>
        <form method="POST" action="{{ route('admin.verses.toggle', $verse) }}" style="display:inline">
            @csrf @method('PATCH')
            <button type="submit" class="toggle {{ $verse->is_indexed ? 'on' : '' }}"><span></span></button>
        </form>
        <span class="pill {{ $verse->is_indexed ? 'pill-on' : 'pill-off' }}">{{ $verse->is_indexed ? 'مفعل في جوجل' : 'noindex' }}</span>
    </td>
    <td>
        <form method="POST" action="{{ route('admin.verses.toggle', $verse) }}" style="display:inline">
            @csrf @method('PATCH')
            <button class="btn btn-ghost" style="padding:4px 8px">{{ $verse->is_indexed ? 'إلغاء' : 'تفعيل' }}</button>
        </form>
    </td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:10px">{{ $verses->links() }}</div>

@push('scripts')
<script>
function selectAll(v){document.querySelectorAll('.rowCheck').forEach(c=>c.checked=v);const a=document.getElementById('checkAll');if(a)a.checked=v;updateCount()}
function updateCount(){const n=document.querySelectorAll('.rowCheck:checked').length;document.getElementById('selCount').textContent=n?`محدد: ${n}`:''}
function confirmBulk(m){ if(document.querySelectorAll('.rowCheck:checked').length===0){alert('اختر آيات');return false} return confirm(m) }
function submitBulk(url){ if(document.querySelectorAll('.rowCheck:checked').length===0){alert('اختر آيات');return} if(!confirm('إلغاء فهرسة المحدد؟')) return; const f=document.getElementById('bulkForm'); f.action=url; f.submit(); }
</script>
@endpush
@endsection
