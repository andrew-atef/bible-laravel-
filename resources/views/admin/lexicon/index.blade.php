@extends('admin.layout')
@section('content')
<h1 style="font-size:18px;font-weight:800;margin:0">إدارة المعجم — سترونغ</h1>
<p style="color:#64748b;font-size:12px;margin:6px 0 12px">بحث بالرقم/اللفظ/النطق، تفعيل فردي أو جماعي. Top 500 هي الأكثر وروداً في النص.</p>

<form method="GET" action="{{ route('admin.lexicon.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">
    <div>
        <label style="font-size:11px;font-weight:700;color:#475569">بحث</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="H7225, רֵאשִׁית, Logos" style="display:block;padding:7px 10px;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;width:200px">
    </div>
    <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600"><input type="checkbox" name="top500" value="1" {{ request('top500') ? 'checked' : '' }}> أهم 500 كلمة لاهوتية (Top 500 Seeds)</label>
    <button class="btn btn-primary" style="height:36px">فلترة</button>
    <a href="{{ route('admin.lexicon.index') }}" class="btn btn-ghost" style="height:36px;display:inline-flex;align-items:center">إعادة</a>
    @if(request('top500'))
        <span style="font-size:11px;color:#2563eb">معروض: Top 500 حسب الورود</span>
    @endif
</form>

<form id="bulkForm" method="POST" action="{{ route('admin.lexicon.bulkEnable') }}" style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    @csrf
    <button type="button" onclick="selectAll(true)" class="btn btn-ghost">تحديد الكل</button>
    <button type="button" onclick="selectAll(false)" class="btn btn-ghost">إلغاء</button>
    <button type="submit" class="btn btn-primary" onclick="return confirmBulk('تفعيل الفهرسة للكلمات المحددة؟')">✅ تفعيل المحدد</button>
    <button type="button" onclick="submitBulk('{{ route('admin.lexicon.bulkDisable') }}')" class="btn btn-danger">🚫 إلغاء المحدد</button>
    <button type="button" onclick="if(confirm('تفعيل أهم 500 كلمة لاهوتية دفعة واحدة؟')){ document.getElementById('top500bulk').submit(); }" class="btn" style="background:#7c3aed;color:#fff">⚡ تفعيل Top 500 بضغطة واحدة</button>
    <span id="selCount" style="font-size:12px;color:#64748b"></span>
</form>
<form id="top500bulk" method="POST" action="{{ route('admin.lexicon.bulkEnable') }}" style="display:none">@csrf<input type="hidden" name="top500_bulk" value="1"></form>

<div class="card" style="padding:0;overflow:hidden">
<table class="table">
<thead>
<tr>
    <th><input type="checkbox" id="checkAll" onchange="selectAll(this.checked)"></th>
    <th>Strong ID</th>
    <th>اللفظ (عبري/يوناني)</th>
    <th>النطق</th>
    <th>التعريف المختصر</th>
    <th>مرات الورود</th>
    <th>مفعل في جوجل؟</th>
    <th>إجراء</th>
</tr>
</thead>
<tbody>
@foreach($lexicons as $lex)
<tr>
    <td><input type="checkbox" name="ids[]" value="{{ $lex->strong_id }}" form="bulkForm" class="rowCheck" onchange="updateCount()"></td>
    <td><a href="{{ url('/lexicon/'.$lex->strong_id) }}" target="_blank" style="font-family:monospace;font-weight:700;color:#2563eb">{{ $lex->strong_id }}</a></td>
    <td style="font-family:'SBL Hebrew',serif">{{ $lex->lemma }}</td>
    <td style="font-family:'Gentium',serif;color:#475569">{{ $lex->transliteration }}</td>
    <td style="font-size:11px;color:#64748b">{{ \Illuminate\Support\Str::limit($lex->strongs_definition, 60) }}</td>
    <td><span class="pill">{{ $occMap[$lex->strong_id] ?? 0 }}×</span></td>
    <td>
        <form method="POST" action="{{ route('admin.lexicon.toggle', $lex->strong_id) }}" style="display:inline">
            @csrf @method('PATCH')
            <button type="submit" class="toggle {{ $lex->is_indexed ? 'on' : '' }}"><span></span></button>
        </form>
        <span class="pill {{ $lex->is_indexed ? 'pill-on' : 'pill-off' }}">{{ $lex->is_indexed ? 'مفعل' : 'noindex' }}</span>
    </td>
    <td>
        <form method="POST" action="{{ route('admin.lexicon.toggle', $lex->strong_id) }}" style="display:inline">
            @csrf @method('PATCH')
            <button class="btn btn-ghost" style="padding:4px 8px">{{ $lex->is_indexed ? 'إلغاء' : 'تفعيل' }}</button>
        </form>
    </td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:10px">{{ $lexicons->links() }}</div>

@push('scripts')
<script>
function selectAll(v){document.querySelectorAll('.rowCheck').forEach(c=>c.checked=v);const a=document.getElementById('checkAll');if(a)a.checked=v;updateCount()}
function updateCount(){const n=document.querySelectorAll('.rowCheck:checked').length;document.getElementById('selCount').textContent=n?`محدد: ${n}`:''}
function confirmBulk(m){ if(document.querySelectorAll('.rowCheck:checked').length===0){alert('اختر كلمات');return false} return confirm(m) }
function submitBulk(url){ if(document.querySelectorAll('.rowCheck:checked').length===0){alert('اختر كلمات');return} if(!confirm('إلغاء فهرسة المحدد؟')) return; const f=document.getElementById('bulkForm'); f.action=url; f.submit(); }
</script>
@endpush
@endsection
