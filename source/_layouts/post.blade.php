<?php
/**
 * @var TightenCo\Jigsaw\Jigsaw $jigsaw
 * @var array $currentMeta
 */
$url = $site["url"];
$path = preg_replace("#/+#","/","/".$currentMeta["url-path"]);
$canonical = rtrim($url,"/").$path;
?>
@extends('_layouts.master')

@section('body')
<article>
    <div class="container">
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1">
                @yield('content')
                <hr />
                <h3>Wanna stay in touch?</h3>
                <p>Since you ended up on this blog, chances are pretty high that you're into Software Development
                (probably PHP, Laravel, Docker or Google Big Query) and I'm a big fan of feedback and networking.
                </p><p>
                So - if you'd like to stay in touch, feel free to shoot me an email with a couple of words about yourself and/or
                connect with me on
                <a href="https://de.linkedin.com/in/pascallandau">LinkedIn</a>,
                <a href="https://twitter.com/PascalLandau">Twitter</a> or
                <a href="https://www.facebook.com/pascal.landau">Facebook</a>
                - or simply subscribe to my
                <a href="/feed.xml">RSS feed</a>
                and leave a comment ;)
                </p>
                <div style="text-align:center; margin-top:1em;">
                    <img src="/img/waving-bear.gif" alt="Waving bear" style="max-width:416px"/>
                </div>
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