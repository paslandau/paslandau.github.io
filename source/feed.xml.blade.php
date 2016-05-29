<?php
/**
 * @var TightenCo\Jigsaw\Jigsaw $jigsaw
 */

$title = "Blog";
$h1 = "Blog";
$subheading = "Coding, PHP, Laravel";
$description = "The most recent articles in this blog";

$lessons = collect($jigsaw->getMeta())
        ->filter(function ($item, $path) {
            $sep = preg_quote(DIRECTORY_SEPARATOR, "#");
            return preg_match("#^blog{$sep}#", $path);
        })
        ->sortByDesc("last_modified")
        ->take(10);

$url = $site["scheme"].$site["host"];
?>
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $site['title'] }}</title>
        <description>{{ $site['description'] }}</description>
        <link>{{$url}}</link>
        <atom:link href="{{$url}}/feed.xml" rel="self" type="application/rss+xml"/>
        <pubDate>{{ (new DateTime)->format(DATE_ATOM) }}</pubDate>
        <lastBuildDate>{{ (new DateTime)->format(DATE_ATOM) }}</lastBuildDate>
        @foreach($lessons as $lesson)
            <item>
                <title>{{ $lesson['title'] }}</title>
                <description>{{ $lesson['description'] }}</description>
                <?php
                        $date = DateTime::createFromFormat("Y-m-d H:i:s", $lesson['published_at']);
                ?>
                @if($date)
                    <pubDate>{{ $date->format(DATE_ATOM) }}</pubDate>
                @endif
                <link>{{$url}}/{{ $lesson['target-path'] }}</link>
                <guid isPermaLink="true">{{$url}}/{{ $lesson['target-path'] }}</guid>
            </item>
        @endforeach
    </channel>
</rss>