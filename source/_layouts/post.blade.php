<?php
/**
 * @var TightenCo\Jigsaw\Jigsaw $jigsaw
 * @var array $currentMeta
 */
$url = $site["url"];
$canonical = $url."/".$currentMeta["url-path"];
?>
@extends('_layouts.master')

@section('body')
<article>
    <div class="container">
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1">
                @yield('content')
                <h2>Comments</h2>
                <div id="disqus_thread"></div>
                <script>
                     var disqus_config = function () {
                        this.page.url = "{{$canonical}}";
                         @if(isset($slug) && $slug)
                            this.page.identifier = "{{$slug}}";
                         @endif
                     };
                    (function() {  // DON'T EDIT BELOW THIS LINE
                        var d = document, s = d.createElement('script');

                        s.src = '//pascallandau.disqus.com/embed.js';

                        s.setAttribute('data-timestamp', +new Date());
                        (d.head || d.body).appendChild(s);
                    })();
                </script>
                <noscript>Please enable JavaScript to view the <a href="https://disqus.com/?ref_noscript" rel="nofollow">comments powered by Disqus.</a></noscript>
            </div>
        </div>
    </div>
</article>
@endsection