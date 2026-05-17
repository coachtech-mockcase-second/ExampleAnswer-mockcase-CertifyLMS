<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>修了証 — {{ $certificate->serial_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 18mm;
        }
        body {
            font-family: 'IPAGothic', 'Noto Sans JP', sans-serif;
            color: #0F2E2A;
            margin: 0;
            padding: 0;
        }
        .frame {
            border: 2pt solid #0D9488;
            border-radius: 2pt;
            padding: 28pt 28pt 24pt 28pt;
            position: relative;
            min-height: 220mm;
        }
        .frame::after {
            content: '';
            position: absolute;
            top: 6pt;
            left: 6pt;
            right: 6pt;
            bottom: 6pt;
            border: 0.5pt solid #5EEAD4;
            border-radius: 1pt;
            pointer-events: none;
        }
        .title {
            text-align: center;
            margin-top: 28pt;
            font-size: 36pt;
            font-weight: bold;
            color: #0F2E2A;
            letter-spacing: -0.01em;
        }
        .recipient-name {
            text-align: center;
            margin-top: 36pt;
            font-size: 26pt;
            font-weight: bold;
            color: #0F2E2A;
            padding-bottom: 6pt;
            border-bottom: 1.5pt solid #5EEAD4;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .statement {
            text-align: center;
            margin-top: 22pt;
            font-size: 12pt;
            line-height: 1.9;
            color: #1F3D38;
            padding: 0 14pt;
        }
        .cert-meta {
            margin-top: 32pt;
            display: table;
            width: 100%;
        }
        .cert-meta-cell {
            display: table-cell;
            font-size: 10pt;
            color: #5E4D72;
            vertical-align: top;
            padding-bottom: 12pt;
        }
        .cert-meta-cell .ml {
            display: block;
            font-size: 8pt;
            color: #6B8783;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 2pt;
        }
        .cert-meta-cell .val {
            font-size: 11pt;
            color: #0F2E2A;
            font-weight: 600;
        }
        .cert-meta-cell.right {
            text-align: right;
        }
        .issuer {
            margin-top: 32pt;
            text-align: center;
            padding-top: 12pt;
            border-top: 1pt solid #DEEEEB;
            font-size: 12pt;
            color: #0F766E;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="frame">
        <div class="title">修了証</div>

        <div class="recipient-name">{{ $certificate->user->name }}</div>

        <div class="statement">上記の者は、本資格の所定の課程を修了したことを証する</div>

        <div class="cert-meta">
            <div class="cert-meta-cell">
                <span class="ml">資格名</span>
                <span class="val">{{ $certificate->certification->name }}</span>
            </div>
            <div class="cert-meta-cell right">
                <span class="ml">発行日</span>
                <span class="val">{{ $certificate->issued_at?->format('Y 年 n 月 j 日') }}</span>
            </div>
        </div>

        <div class="cert-meta">
            <div class="cert-meta-cell">
                <span class="ml">証書番号</span>
                <span class="val" style="font-family: monospace; letter-spacing: 0.04em;">{{ $certificate->serial_no }}</span>
            </div>
        </div>

        <div class="issuer">Certify LMS</div>
    </div>
</body>
</html>
