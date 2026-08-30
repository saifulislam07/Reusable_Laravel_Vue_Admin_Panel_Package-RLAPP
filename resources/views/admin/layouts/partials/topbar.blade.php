@php
    $nkUser = auth()->guard(config('admin-kit.guard'))->user();
    $nkUserName = $nkUser?->name ?? __('Guest');
    $nkInitials = Str::of($nkUserName)
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
        ->implode('');
@endphp

<header class="nk-topbar" x-data>
    <button
        type="button"
        class="nk-iconbtn"
        @click="$store.nk.toggleMobileNav()"
        aria-controls="nk-sidebar"
        title="{{ __('Toggle navigation') }}"
    >
        <x-admin::icon name="menu" />
        <span class="nk-visually-hidden">{{ __('Toggle navigation') }}</span>
    </button>

    <button
        type="button"
        class="nk-iconbtn"
        @click="$store.nk.toggleSidebar()"
        title="{{ __('Collapse sidebar') }}"
    >
        <x-admin::icon name="panel-left" />
        <span class="nk-visually-hidden">{{ __('Collapse sidebar') }}</span>
    </button>

    {{-- Global search is wired up alongside the DataTable system (Phase 5). --}}
    <form class="nk-topbar__search" role="search" onsubmit="return false">
        <x-admin::icon name="search" class="nk-icon--sm" />
        <input type="search" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
    </form>

    <span class="nk-topbar__spacer"></span>

    <button
        type="button"
        class="nk-iconbtn"
        @click="$store.nk.cycleScheme()"
        :title="'{{ __('Colour scheme') }}: ' + $store.nk.scheme"
        title="{{ __('Colour scheme') }}"
    >
        <x-admin::icon name="sun" x-show="$store.nk.scheme === 'light'" x-cloak />
        <x-admin::icon name="moon" x-show="$store.nk.scheme === 'dark'" x-cloak />
        <x-admin::icon name="monitor" x-show="$store.nk.scheme === 'system'" x-cloak />
        <span class="nk-visually-hidden">{{ __('Change colour scheme') }}</span>
    </button>

    @if ($nkUser)
        <div class="nk-usermenu" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button
                type="button"
                class="nk-iconbtn"
                @click="open = ! open"
                :aria-expanded="open ? 'true' : 'false'"
                aria-haspopup="true"
                title="{{ __('Notifications') }}"
            >
                <x-admin::icon name="bell" />
                @if ($nkUnreadNotificationsCount > 0)
                    <span class="nk-iconbtn__dot"></span>
                @endif
                <span class="nk-visually-hidden">{{ __('Notifications') }}</span>
            </button>

            <div class="nk-dropdown nk-dropdown--notifications" x-show="open" x-cloak @click.outside="open = false">
                <div class="nk-dropdown__header nk-dropdown__header--flex">
                    <strong>{{ __('Notifications') }}</strong>

                    @if ($nkUnreadNotificationsCount > 0)
                        <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                            @csrf
                            <button type="submit" class="nk-link-btn">{{ __('Mark all as read') }}</button>
                        </form>
                    @endif
                </div>

                @forelse ($nkRecentNotifications as $nkNotification)
                    <form method="POST" action="{{ route('admin.notifications.read', $nkNotification->id) }}">
                        @csrf
                        <button type="submit" class="nk-notif-item @if (! $nkNotification->read_at) is-unread @endif">
                            <span class="nk-notif-item__title">{{ $nkNotification->data['title'] ?? __('Notification') }}</span>
                            <span class="nk-notif-item__body">{{ Str::limit($nkNotification->data['body'] ?? '', 70) }}</span>
                            <span class="nk-notif-item__time">{{ $nkNotification->created_at?->diffForHumans() }}</span>
                        </button>
                    </form>
                @empty
                    <p class="nk-dropdown__empty">{{ __('No notifications yet.') }}</p>
                @endforelse

                <a class="nk-dropdown__item nk-dropdown__item--footer" href="{{ route('admin.notifications.index') }}">
                    {{ __('View all notifications') }}
                </a>
            </div>
        </div>
    @endif

    @if ($nkUser)
        <div class="nk-usermenu" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button
                type="button"
                class="nk-usermenu__trigger"
                @click="open = ! open"
                :aria-expanded="open ? 'true' : 'false'"
                aria-haspopup="true"
            >
                @if ($nkUser?->avatarUrl())
                    <span class="nk-avatar"><img src="{{ $nkUser->avatarUrl() }}" alt=""></span>
                @else
                    <span class="nk-avatar">{{ $nkInitials }}</span>
                @endif
                <span class="nk-usermenu__name">{{ $nkUserName }}</span>
                <x-admin::icon name="chevron-down" class="nk-icon--sm" />
            </button>

            <div class="nk-dropdown" x-show="open" x-cloak @click.outside="open = false">
                <div class="nk-dropdown__header">
                    <strong>{{ $nkUserName }}</strong>
                    <span>{{ $nkUser->email }}</span>
                </div>

                {{-- Self-service profile pages land in Phase 9. --}}
                @if (Route::has('admin.profile.edit'))
                    <a class="nk-dropdown__item" href="{{ route('admin.profile.edit') }}">
                        <x-admin::icon name="user" class="nk-icon--sm" />
                        {{ __('My profile') }}
                    </a>
                @endif

                @php
                    $nkLogoutRoute = Route::has('admin.logout') ? 'admin.logout' : (Route::has('logout') ? 'logout' : null);
                @endphp

                @if ($nkLogoutRoute)
                    <form method="POST" action="{{ route($nkLogoutRoute) }}">
                        @csrf
                        <button type="submit" class="nk-dropdown__item nk-dropdown__item--danger">
                            <x-admin::icon name="log-out" class="nk-icon--sm" />
                            {{ __('Sign out') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</header>
