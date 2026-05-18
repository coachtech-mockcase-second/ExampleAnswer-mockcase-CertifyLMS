@props(['status'])

<x-badge :variant="$status->color()" size="sm">{{ $status->label() }}</x-badge>
