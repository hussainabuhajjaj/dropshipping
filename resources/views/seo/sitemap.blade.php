<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if (! empty($url['lastmod']))
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
@if (! empty($url['image']))
@foreach ($url['image'] as $img)
        <image:image>
            <image:loc>{{ $img['loc'] }}</image:loc>
@if (! empty($img['title']))
            <image:title>{{ $img['title'] }}</image:title>
@endif
        </image:image>
@endforeach
@endif
    </url>
@endforeach
</urlset>
