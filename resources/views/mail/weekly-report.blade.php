@php
  [$band, $bandNote] = \App\Support\ChildSummary::band($summary['average']);
  $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  $first = $child->firstName();
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Weekly report</title></head>
<body style="margin:0;padding:24px;background:#f5f7f5;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0e1a15">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #dbe3dd">

  <tr>
    <td style="background:#007145;padding:26px 28px;color:#fff">
      <div style="font-size:13px;letter-spacing:.12em;text-transform:uppercase;opacity:.82">ClassMate 9ja</div>
      <div style="font-size:23px;font-weight:800;margin-top:6px">{{ $first }}'s week</div>
      <div style="font-size:14px;opacity:.85;margin-top:4px">
        Week of {{ now()->startOfWeek()->format('j F Y') }}
      </div>
    </td>
  </tr>

  <tr>
    <td style="padding:26px 28px">

      <p style="margin:0 0 20px;font-size:15px;line-height:1.6">
        {{ $summary['on_track']
            ? $first . ' is keeping up this week.'
            : $first . ' needs a push this week.' }}
        Studied on <b>{{ $summary['days_studied'] }} of 7 days</b>,
        {{ intdiv($summary['minutes'], 60) }}h {{ $summary['minutes'] % 60 }}m in total,
        on a {{ $summary['streak'] }}-day streak.
      </p>

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:22px">
        <tr>
          @foreach ([
            ['Study time', intdiv($summary['minutes'], 60) . 'h ' . $summary['minutes'] % 60 . 'm'],
            ['Days', $summary['days_studied'] . '/7'],
            ['Average', $summary['average'] . '%'],
            ['Grade', $band],
          ] as [$label, $value])
            <td style="background:#f5f7f5;border-radius:10px;padding:14px 10px;text-align:center;width:25%">
              <div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:#6c8076">{{ $label }}</div>
              <div style="font-size:19px;font-weight:800;margin-top:4px">{{ $value }}</div>
            </td>
            @if (! $loop->last)<td style="width:8px"></td>@endif
          @endforeach
        </tr>
      </table>

      <h3 style="font-size:15px;margin:0 0 12px">Day by day</h3>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
        @foreach ($summary['week'] as $i => $mins)
          <tr>
            <td style="padding:7px 0;font-size:14px;border-bottom:1px solid #eef2ef;width:80px">{{ $days[$i] }}</td>
            <td style="padding:7px 0;border-bottom:1px solid #eef2ef">
              <div style="background:#eef2ef;border-radius:99px;height:8px;width:100%">
                <div style="background:{{ $mins >= 30 ? '#007145' : '#f0a020' }};height:8px;border-radius:99px;width:{{ min(100, round($mins / 60 * 100)) }}%"></div>
              </div>
            </td>
            <td style="padding:7px 0;font-size:14px;text-align:right;border-bottom:1px solid #eef2ef;width:70px">
              {{ $mins ? $mins . ' min' : '—' }}
            </td>
          </tr>
        @endforeach
      </table>

      @if ($summary['weakest'])
        <div style="background:#fdf0ed;border-left:3px solid #a03620;border-radius:0 8px 8px 0;padding:16px 18px;margin-bottom:22px">
          <div style="font-weight:700;font-size:14px;margin-bottom:5px">What to do this week</div>
          <div style="font-size:14px;line-height:1.6">
            Ask {{ $first }} about <b>{{ ucfirst($summary['weakest']['slug']) }}</b>
            @if ($summary['weakest']['weak'])— specifically {{ $summary['weakest']['weak'][0] }}@endif.
            Do not ask "did you read?". Ask them to explain the topic to you out loud.
            If they cannot explain it simply, they have not understood it yet.
          </div>
        </div>
      @endif

      <a href="{{ route('parent.report') }}"
         style="display:inline-block;background:#007145;color:#fff;text-decoration:none;padding:13px 22px;border-radius:10px;font-weight:600;font-size:15px">
        See the full report
      </a>

      <p style="margin:24px 0 0;font-size:12px;color:#6c8076;line-height:1.6">
        Study time is measured on {{ $first }}'s device, not self-reported.
        You are receiving this because you are linked to {{ $child->name }} on ClassMate 9ja.
      </p>
    </td>
  </tr>
</table>

</body>
</html>
