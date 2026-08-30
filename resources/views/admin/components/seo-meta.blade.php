{{--
    <x-admin::seo-meta :model="$page" />

    Renders the full <head> block for any model using
    App\Models\Admin\Concerns\HasSeoMeta -- title, meta description/keywords,
    canonical, robots, OpenGraph, Twitter Card. For a consuming project's own
    public templates, not the admin panel itself (which has its own
    @section('title') mechanism and is noindex by nature).

    $model is expected to expose a `title` (or `name`) attribute for the
    fallback title, and its own seoMeta relation (eager-load it, or this
    triggers one query per render).
--}}
@props(['model'])

@php
    $seo = $model->seoMeta;
    $fallbackTitle = (string) ($model->title ?? $model->name ?? '');
    $suffix = trim((string) setting('meta_title_suffix', ''));

    $title = filled($seo?->meta_title)
        ? $seo->meta_title
        : trim($fallbackTitle.($suffix !== '' ? ' '.$suffix : ''));

    $description = $seo?->meta_description;
    $keywords = $seo?->meta_keywords;
    $canonical = $seo?->canonical_url ?: url()->current();
    $robots = $seo?->robots ?: 'index,follow';

    $ogImageUrl = $seo?->ogImage?->getUrl();

    if (! $ogImageUrl && $defaultOgImageId = setting('default_og_image_media_id')) {
        $ogImageUrl = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($defaultOgImageId)?->getUrl();
    }
@endphp
<title>{{ $title }}</title>
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
@if ($keywords)
    <meta name="keywords" content="{{ $keywords }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $robots }}">

<meta property="og:title" content="{{ $title }}">
@if ($description)
    <meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:type" content="website">
<meta property="og:url" content="{{ $canonical }}">
@if ($ogImageUrl)
    <meta property="og:image" content="{{ $ogImageUrl }}">
@endif

<meta name="twitter:card" content="{{ $ogImageUrl ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $title }}">
@if ($description)
    <meta name="twitter:description" content="{{ $description }}">
@endif
@if ($ogImageUrl)
    <meta name="twitter:image" content="{{ $ogImageUrl }}">
@endif
