@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2 text-center">
    <h1 class="font-display text-lg tracking-wide text-cromo">{{ $title }}</h1>
    <p class="text-center text-sm text-niebla">{{ $description }}</p>
</div>
