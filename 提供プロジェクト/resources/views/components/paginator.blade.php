{{--
    ページネーション。Laravel のページネータを受け取りページ送り UI を表示する。
    props: paginator(ページネータ。1 ページに収まるときは何も表示しない)。現在のクエリ文字列を引き継ぐ。
--}}
@props(['paginator'])

@if ($paginator->hasPages())
    {{ $paginator->withQueryString()->links('pagination::tailwind') }}
@endif
