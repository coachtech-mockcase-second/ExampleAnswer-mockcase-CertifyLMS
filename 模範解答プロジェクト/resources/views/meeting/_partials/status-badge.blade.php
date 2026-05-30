{{--
    面談ステータスのバッジ表示 partial。props: status(状態 Enum、色とラベルは Enum から取得)。
--}}
@props(['status'])

<x-badge :variant="$status->color()" size="sm">{{ $status->label() }}</x-badge>
