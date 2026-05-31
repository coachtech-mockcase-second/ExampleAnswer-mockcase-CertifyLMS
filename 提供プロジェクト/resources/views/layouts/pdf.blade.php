<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Certify LMS')</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        body {
            font-family: 'IPAGothic', 'Noto Sans JP', sans-serif;
            color: #0F2E2A;
            font-size: 12pt;
            line-height: 1.55;
            margin: 0;
        }
        .pdf-header {
            text-align: center;
            margin-bottom: 24pt;
            padding-bottom: 12pt;
            border-bottom: 1pt solid #DEEEEB;
        }
        .pdf-title {
            font-size: 24pt;
            font-weight: bold;
            color: #0F766E;
            margin: 0;
        }
        .pdf-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            color: #6B8783;
        }
        .pdf-section { margin-bottom: 18pt; }
        .pdf-label {
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6B8783;
            margin-bottom: 4pt;
        }
        .pdf-value { font-size: 14pt; font-weight: 600; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
