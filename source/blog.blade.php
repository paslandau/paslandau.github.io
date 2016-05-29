<?php
/**
 * @var TightenCo\Jigsaw\Jigsaw $jigsaw
 */

$title = "Blog";
$h1 = "Blog";
$subheading = "Coding, PHP, Laravel";
$description = "The most recent articles in this blog";

$posts = collect($jigsaw->getMeta())
        ->filter(function ($item, $path) {
            $sep = preg_quote(DIRECTORY_SEPARATOR, "#");
            return preg_match("#^blog{$sep}#", $path);
        })
        ->sortByDesc("last_modified")
        ->take(10);
?>
@extends('_layouts.post')

@section('content')
    @foreach($posts as $post)
        <h2><a href="/{{$post['target-path']}}">{{$post['title']}}</a></h2>
        <small>Published at {{$post['published_at']}} by {{$post['author']}} </small>
        <p>{{$post['description']}}</p>
    @endforeach
@endsection