<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $page)
    <url>
        <loc>{{ $page['loc'] }}</loc>
@foreach (($page['alternates'] ?? []) as $alt)
        <xhtml:link rel="alternate" hreflang="{{ $alt['hreflang'] }}" href="{{ $alt['href'] }}" />
@endforeach
@if (!empty($page['lastmod']))
        <lastmod>{{ $page['lastmod'] }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
