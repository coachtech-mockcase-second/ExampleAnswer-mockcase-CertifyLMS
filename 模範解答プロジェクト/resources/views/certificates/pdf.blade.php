<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>修了証 — {{ $certificate->serial_no }}</title>
    <style>
        body {
            color: #0F2E2A;
            font-size: 11pt;
        }
        .frame-outer {
            border: 1.5pt solid #0D9488;
            padding: 10pt;
        }
        .frame-inner {
            border: 0.5pt solid #5EEAD4;
            padding: 18pt 22pt 16pt 22pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .brand {
            font-size: 12pt;
            font-weight: bold;
            color: #0F2E2A;
        }
        .brand-lms {
            color: #0F766E;
            font-weight: normal;
        }
        .serial {
            text-align: right;
            font-size: 9pt;
            color: #6B8783;
        }
        .title {
            text-align: center;
            margin-top: 24pt;
            padding: 10pt 0;
            font-size: 36pt;
            font-weight: bold;
            color: #0F2E2A;
            border-top: 1pt solid #0D9488;
            border-bottom: 1pt solid #0D9488;
        }
        .intro {
            text-align: center;
            margin-top: 22pt;
            font-size: 10pt;
            color: #466662;
        }
        .recipient {
            text-align: center;
            margin-top: 6pt;
            font-size: 26pt;
            font-weight: bold;
            color: #0F2E2A;
        }
        .recipient-line {
            margin: 6pt auto 0 auto;
            height: 1.5pt;
            background-color: #2DD4BF;
            width: 50%;
        }
        .statement {
            text-align: center;
            margin-top: 20pt;
            font-size: 12pt;
            line-height: 1.9;
            color: #2C4A45;
        }
        .cert-name {
            text-align: center;
            margin-top: 14pt;
            font-size: 14pt;
            font-weight: bold;
            color: #0F2E2A;
        }
        .meta {
            margin-top: 28pt;
        }
        .meta td {
            padding: 0 6pt;
        }
        .meta-label {
            font-size: 8pt;
            color: #6B8783;
            padding-bottom: 3pt;
        }
        .meta-value {
            font-size: 12pt;
            font-weight: bold;
            color: #0F2E2A;
        }
        .meta-right {
            text-align: right;
        }
        .seal-row {
            margin-top: 28pt;
        }
        .seal-cell {
            text-align: center;
        }
        .seal {
            background-color: #0D9488;
            color: #FFFFFF;
            padding: 8pt 24pt;
            font-size: 12pt;
            font-weight: bold;
        }
        .issuer-note {
            margin-top: 6pt;
            text-align: center;
            font-size: 8pt;
            color: #6B8783;
        }
    </style>
</head>
<body>
<div class="frame-outer">
    <div class="frame-inner">
        <table>
            <tr>
                <td class="brand">Certify <span class="brand-lms">LMS</span></td>
                <td class="serial">SERIAL: {{ $certificate->serial_no }}</td>
            </tr>
        </table>

        <div class="title">修了証</div>

        <div class="intro">この証は、下記のとおり修了したことを証する</div>
        <div class="recipient">{{ $certificate->user->name }}</div>
        <div class="recipient-line"></div>

        <div class="statement">上記の者は、本資格の所定の課程を修了したことを証する</div>

        <div class="cert-name">{{ $certificate->certification->name }}</div>

        <table class="meta">
            <tr>
                <td>
                    <div class="meta-label">発行日</div>
                    <div class="meta-value">{{ $certificate->issued_at?->format('Y 年 n 月 j 日') }}</div>
                </td>
                <td class="meta-right">
                    <div class="meta-label">証書番号</div>
                    <div class="meta-value">{{ $certificate->serial_no }}</div>
                </td>
            </tr>
        </table>

        <table class="seal-row">
            <tr>
                <td class="seal-cell">
                    <span class="seal">Certify LMS</span>
                </td>
            </tr>
        </table>

        <div class="issuer-note">Issued by Certify LMS</div>
    </div>
</div>
</body>
</html>
