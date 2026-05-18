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
            padding: 8pt;
        }
        .frame-inner {
            border: 0.5pt solid #5EEAD4;
            padding: 14pt 26pt 10pt 26pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .topline td.brand {
            font-size: 13pt;
            font-weight: bold;
            color: #0F2E2A;
        }
        .topline .brand-lms {
            color: #0F766E;
            font-weight: normal;
        }
        .topline td.serial {
            text-align: right;
            font-size: 9pt;
            color: #6B8783;
        }
        .label-cert {
            text-align: center;
            margin-top: 14pt;
            font-size: 9pt;
            font-weight: bold;
            color: #0F766E;
            letter-spacing: 0.3em;
        }
        .title {
            text-align: center;
            margin-top: 4pt;
            font-size: 36pt;
            font-weight: bold;
            color: #0F2E2A;
        }
        .intro {
            text-align: center;
            margin-top: 22pt;
            font-size: 10pt;
            color: #466662;
        }
        .recipient {
            text-align: center;
            margin-top: 4pt;
            font-size: 28pt;
            font-weight: bold;
            color: #0F2E2A;
        }
        .recipient-underline {
            margin: 4pt auto 0 auto;
            height: 1.5pt;
            background-color: #2DD4BF;
            width: 40%;
        }
        .statement {
            text-align: center;
            margin-top: 14pt;
            font-size: 11pt;
            line-height: 1.85;
            color: #2C4A45;
        }
        .cert-name {
            text-align: center;
            margin-top: 8pt;
            font-size: 14pt;
            font-weight: bold;
            color: #0F2E2A;
        }
        .footer {
            margin-top: 22pt;
        }
        .footer td {
            vertical-align: middle;
            padding: 0 6pt;
        }
        .footer td.left {
            width: 35%;
        }
        .footer td.center {
            width: 30%;
            text-align: center;
        }
        .footer td.right {
            width: 35%;
            text-align: right;
        }
        .footer .meta-label {
            font-size: 8pt;
            color: #6B8783;
            padding-bottom: 3pt;
        }
        .footer .meta-value {
            font-size: 11pt;
            font-weight: bold;
            color: #0F2E2A;
        }
        .seal {
            display: inline-block;
            width: 70pt;
            height: 70pt;
            border-radius: 35pt;
            background-color: #0D9488;
            color: #FFFFFF;
            text-align: center;
            padding: 22pt 4pt 0 4pt;
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 0.1em;
        }
    </style>
</head>
<body>
<div class="frame-outer">
    <div class="frame-inner">
        <table class="topline">
            <tr>
                <td class="brand">Certify <span class="brand-lms">LMS</span></td>
                <td class="serial">SERIAL: {{ $certificate->serial_no }}</td>
            </tr>
        </table>

        <div class="title">修了証</div>

        <div class="recipient">{{ $certificate->user->name }}</div>
        <div class="recipient-underline"></div>

        <div class="statement">上記の者は、本資格の所定の課程を修了したことを証する</div>

        <div class="cert-name">{{ $certificate->certification->name }}</div>

        <table class="footer">
            <tr>
                <td class="left">
                    <div class="meta-label">発行日</div>
                    <div class="meta-value">{{ $certificate->issued_at?->format('Y 年 n 月 j 日') }}</div>
                </td>
                <td class="center">
                    <span class="seal">Certify<br>LMS</span>
                </td>
                <td class="right">
                    <div class="meta-label">証書番号</div>
                    <div class="meta-value">{{ $certificate->serial_no }}</div>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
