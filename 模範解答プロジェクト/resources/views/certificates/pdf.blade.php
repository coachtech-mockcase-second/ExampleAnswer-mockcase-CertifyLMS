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

        .topline {
            display: table;
            width: 100%;
            margin-bottom: 18pt;
        }
        .brand, .serial {
            display: table-cell;
            vertical-align: middle;
        }
        .brand {
            font-size: 13pt;
            font-weight: bold;
            color: #0F766E;
        }
        .brand .product {
            color: #0F2E2A;
        }
        .serial {
            text-align: right;
            font-size: 9pt;
            color: #6B8783;
            letter-spacing: 0.04em;
        }

        .label-cert {
            text-align: center;
            margin-top: 24pt;
            font-size: 11pt;
            font-weight: 600;
            color: #0F766E;
            letter-spacing: 0.4em;
        }
        .title-cert {
            text-align: center;
            margin-top: 6pt;
            font-size: 36pt;
            font-weight: bold;
            color: #0F2E2A;
            letter-spacing: -0.01em;
        }

        .recipient-intro {
            text-align: center;
            margin-top: 22pt;
            font-size: 10pt;
            color: #5E4D72;
        }
        .recipient-name {
            text-align: center;
            margin-top: 8pt;
            font-size: 26pt;
            font-weight: bold;
            color: #0F2E2A;
            padding-bottom: 6pt;
            border-bottom: 1.5pt solid #5EEAD4;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }

        .reason {
            text-align: center;
            margin-top: 18pt;
            font-size: 11pt;
            line-height: 1.9;
            color: #1F3D38;
            padding: 0 14pt;
        }
        .reason b {
            color: #0F2E2A;
            font-weight: bold;
        }

        .cert-meta {
            margin-top: 22pt;
            display: table;
            width: 100%;
        }
        .cert-meta-cell {
            display: table-cell;
            font-size: 9.5pt;
            color: #5E4D72;
            vertical-align: top;
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

        .footer {
            margin-top: 32pt;
            text-align: center;
            padding-top: 12pt;
            border-top: 1pt solid #DEEEEB;
            font-size: 11pt;
            color: #0F766E;
            font-weight: 600;
        }
        .footer .meta {
            margin-top: 4pt;
            font-size: 9pt;
            color: #6B8783;
            letter-spacing: 0.04em;
        }
    </style>
</head>
<body>
    <div class="frame">
        <div class="topline">
            <div class="brand">Certify <span class="product">LMS</span></div>
            <div class="serial">SERIAL: {{ $certificate->serial_no }}</div>
        </div>

        <div class="label-cert">Certificate of Completion</div>
        <div class="title-cert">修了証</div>

        <div class="recipient-intro">この証は、下記のとおり修了したことを証する</div>
        <div class="recipient-name">{{ $certificate->user->name }}</div>

        <div class="reason">
            上記の者は、<b>{{ $certificate->certification->name }}</b>（<span style="font-family: monospace;">{{ $certificate->certification->code }}</span>）の<br>
            本資格の所定の課程を修了したことを証する。
        </div>

        <div class="cert-meta">
            <div class="cert-meta-cell">
                <span class="ml">発行日</span>
                <span class="val">{{ $certificate->issued_at?->format('Y 年 n 月 j 日') }}</span>
            </div>
            <div class="cert-meta-cell right">
                <span class="ml">証書番号</span>
                <span class="val" style="font-family: monospace; letter-spacing: 0.04em;">{{ $certificate->serial_no }}</span>
            </div>
        </div>

        <div class="footer">
            Certify LMS
            <div class="meta">マルチ資格対応 学習プラットフォーム</div>
        </div>
    </div>
</body>
</html>
