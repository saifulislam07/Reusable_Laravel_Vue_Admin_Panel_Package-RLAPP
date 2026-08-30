{{--
    <x-admin::page-header title="Users" subtitle="…" :breadcrumbs="[['label' => 'Home', 'url' => '…'], ['label' => 'Users']]">
        <x-slot:actions>…</x-slot:actions>
    </x-admin::page-header>
--}}
@props(['title', 'subtitle' => null, 'breadcrumbs' => []])

<div {{ $attributes->class('nk-pagehead') }}>
    <div>
        @if (filled($breadcrumbs))
            <ol class="nk-breadcrumb">
                @foreach ($breadcrumbs as $crumb)
                    <li>
                        @if (! empty($crumb['url']) && ! $loop->last)
                            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                        @else
                            {{ $crumb['label'] }}
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        <h1 class="nk-pagehead__title">{{ $title }}</h1>

        @if ($subtitle)
            <p class="nk-pagehead__subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="nk-pagehead__actions">{{ $actions }}</div>
    @endisset
</div>
