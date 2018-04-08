<?php
/**
 * @var TightenCo\Jigsaw\Jigsaw $jigsaw
 */

$title = "BigQuery Snippets";
$h1 = "BigQuery Snippets";
$subheading = "A collection of BigQuery snippets for fun and non-profit";
$description = "A collection of BigQuery snippets including example code and a short explanation.";
$slug = "bigquery-snippets";

$posts = collect($jigsaw->getMeta())
        ->filter(function ($item, $path) {
            $sep = preg_quote(DIRECTORY_SEPARATOR, "#");
            return preg_match("#^bigquery-snippets{$sep}#", $path);
        })
        ->sortByDesc("published_at")
        ->take(100);
?>
@extends('_layouts.post')

@section('content')
    @foreach($posts as $post)
        <h2><a href="/{{$post['url-path']}}">{{$post['title']}}</a></h2>
        <small>Published at {{$post['published_at']}} by {{$post['author']}} </small>
        <p>{{$post['description']}}</p>
    @endforeach
@endsection