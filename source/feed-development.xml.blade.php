<?php
/**
 * @var TightenCo\Jigsaw\Jigsaw $jigsaw
 */

$lessons = collect($jigsaw->getMeta())
        ->filter(function ($item, $path) {
            $sep = preg_quote(DIRECTORY_SEPARATOR, "#");
            return preg_match("#^blog{$sep}#", $path) && isset($item["category"]) && $item["category"] == "development";
        })
        ->sortByDesc("published_at")
        ->take(10);
xdebug_break();
$url = $site["url"];
?>
<?xml version = "1.0" encoding = "UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $site['title'] }} [dev only]</title>
        <description>{{ $site['description'] }} - Development related posts only</description>
        <link>{{$url}}</link>
        <atom:link href="{{$url}}/feed-development.xml" rel="self" type="application/rss+xml"/>
        <pubDate>{{ (new DateTime)->format(DATE_RSS) }}</pubDate>
        <lastBuildDate>{{ (new DateTime)->format(DATE_RSS) }}</lastBuildDate>
        <language>en</language>
        @foreach($lessons as $lesson)
            <item>
                <title>{{ $lesson['title'] }}</title>
                <description><![CDATA[{!! $lesson['content'] !!}]]></description>
                <?php
                $date = DateTime::createFromFormat("Y-m-d H:i:s", $lesson['published_at']) ?: DateTime::createFromFormat("Y-m-d", $lesson['published_at']) ?: null;
                ?>
                @if($date)
                    <pubDate>{{ $date->format(DATE_RSS) }}</pubDate>
                @endif
                <link>{{$url}}/{{ $lesson['url-path'] }}</link>
                <guid isPermaLink="true">{{$url}}/{{ $lesson['url-path'] }}</guid>
            </item>
        @endforeach
    </channel>
</rss>