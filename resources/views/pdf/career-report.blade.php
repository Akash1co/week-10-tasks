<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Career Intelligence Report</title>
    <style>
        @page { margin: 34px 40px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.55; }
        .brand { color: #155eef; font-size: 11px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 25px; margin: 8px 0 3px; }
        .muted { color: #667085; }
        .score { background: #eff8ff; border: 1px solid #b2ddff; border-radius: 8px; color: #175cd3; display: inline-block; font-size: 19px; font-weight: bold; margin: 16px 0; padding: 10px 16px; }
        h2 { border-bottom: 1px solid #d0d5dd; font-size: 15px; margin: 24px 0 10px; padding-bottom: 6px; }
        .archetype { margin: 9px 0; }
        .archetype-label { display: inline-block; width: 105px; }
        .bar-track { background: #eaecf0; display: inline-block; height: 9px; vertical-align: middle; width: 330px; }
        .bar { background: #528bff; height: 9px; }
        .archetype-score { color: #344054; display: inline-block; margin-left: 8px; width: 40px; }
        .summary { background: #f9fafb; border-left: 3px solid #528bff; padding: 12px 14px; }
        li { margin: 7px 0; padding-left: 3px; }
        .footer { border-top: 1px solid #eaecf0; color: #667085; font-size: 9px; margin-top: 28px; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="brand">Career Intelligence</div>
    <h1>Career Intelligence Report</h1>
    <div class="muted">{{ $candidateName }} · Generated {{ $generatedAt->format('F j, Y') }}</div>

    <div class="score">{{ number_format($report['overall_employability_score'], 1) }} / 100 Employability</div>

    <h2>Career archetype profile</h2>
    @foreach ($report['archetype_scores'] as $archetype => $score)
        <div class="archetype">
            <span class="archetype-label">{{ $archetype }}</span>
            <span class="bar-track"><span class="bar" style="display: block; width: {{ $score }}%;"></span></span>
            <span class="archetype-score">{{ number_format($score, 1) }}</span>
        </div>
    @endforeach

    <h2>AI career analysis</h2>
    <div class="summary">{{ $report['narrative_summary'] }}</div>

    <h2>Recommended next steps</h2>
    <ol>
        @foreach ($report['recommendations'] as $recommendation)
            <li>{{ $recommendation }}</li>
        @endforeach
    </ol>

    <div class="footer">This report is intended as developmental guidance and does not guarantee employment outcomes.</div>
</body>
</html>
