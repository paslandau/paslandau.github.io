<?php
/**
 * @var TightenCo\Jigsaw\Jigsaw $jigsaw
 * @var array $currentMeta
 */
$url = $site["url"];
$canonical = $url."/".$currentMeta["url-path"];
$vars = [
        "title",
        "description",
        "h1",
];
        /* OPTIONAL
         "image",
        "subheading",
        "author",
        "published_at",
         */
foreach ($vars as $var) {
    $$var = isset($$var) ? $$var : "['$var' NOT SET]";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="{{$description}}">
    @if(isset($author))
        <meta name="author" content="{{$author}}">
    @endif
    <title>{{$title}} | {{$site["title"]}}</title>
    <meta name="google-site-verification" content="fcW8afndMqg-HUmdh_fIAbz81qMkxVJA-Hogrg3UYEw" />

    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link href="/css/clean-blog.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/main.css">
    <!-- RSS Feed -->
    <link rel="canonical" href="{{$canonical}}" />
    <link rel="alternate" type="application/rss+xml" title="{{$site["title"]}}" href="{{$site["url"]}}{{$jigsaw->getMeta()["feed.xml.blade.php"]["target-path"]}}" />

    <!-- Custom Fonts -->
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet"
          type="text/css">
    {{--<link href='http://fonts.googleapis.com/css?family=Lora:400,700,400italic,700italic' rel='stylesheet'--}}
          {{--type='text/css'>--}}
    {{--<link href='http://fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800'--}}
          {{--rel='stylesheet' type='text/css'>--}}

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

</head>

<body>

<!-- Navigation -->
<nav class="navbar navbar-default navbar-custom navbar-fixed-top">
    <div class="container-fluid">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header page-scroll">
            <button type="button" class="navbar-toggle" data-toggle="collapse"
                    data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="/">{{$site["title"]}}</a>
        </div>

        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav navbar-right">
                <li>
                    <a href="/">Home</a>
                </li>
                <li>
                    <a href="/about/">About</a>
                </li>
                <li>
                    <a href="/blog/">Blog</a>
                </li>
                <li>
                    <a href="/bigquery-snippets/">BigQuery Snippets</a>
                </li>
            </ul>
        </div>
        <!-- /.navbar-collapse -->
    </div>
    <!-- /.container -->
</nav>

<!-- Page Header -->
<!-- Set your background image for this header on the line below. -->
<header class="intro-header" style="background: #000
        @if(isset($image))
            url('/img/{{$image}}');
        @endif
        ">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1">
                <div class="post-heading">
                    <h1>{{$h1}}</h1>
                    @if(isset($subheading))
                        <h2 class="subheading">{{$subheading}}</h2>
                    @endif
                    @if(isset($author, $published_at))
                    <span class="meta">Posted by <a href="#">{{$author}}</a> on {{$published_at}}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>

@yield('body')

<hr>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1">
                <ul class="list-inline text-center">
                    <li>
                        <a href="{{$social["twitter"]}}">
                                <span class="fa-stack fa-lg">
                                    <i class="fa fa-circle fa-stack-2x"></i>
                                    <i class="fa fa-twitter fa-stack-1x fa-inverse"></i>
                                </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{$social["fb"]}}">
                                <span class="fa-stack fa-lg">
                                    <i class="fa fa-circle fa-stack-2x"></i>
                                    <i class="fa fa-facebook fa-stack-1x fa-inverse"></i>
                                </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{$social["github"]}}">
                                <span class="fa-stack fa-lg">
                                    <i class="fa fa-circle fa-stack-2x"></i>
                                    <i class="fa fa-github fa-stack-1x fa-inverse"></i>
                                </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{$social["rss"]}}">
                                <span class="fa-stack fa-lg">
                                    <i class="fa fa-circle fa-stack-2x"></i>
                                    <i class="fa fa-rss fa-stack-1x fa-inverse"></i>
                                </span>
                        </a>
                    </li>
                </ul>
                <p class="copyright text-muted">&copy; <a href="{{$site["url"]}}">{{$site["host"]}}</a> <?= date("Y") ?>
                    built with (a slighty modified version of) <a href="https://github.com/paslandau/jigsaw/tree/develop">Jigsaw</a></p>
            </div>
        </div>
    </div>
</footer>
@if(isset($vgwort))
    <img src="https://ssl-vg03.met.vgwort.de/na/{{$vgwort}}" width="1" height="1" alt="" />
@endif
<script>
    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
    ga('create', 'UA-4233326-17', 'auto');
    ga('send', 'pageview');
    ga("set","anonymizeIp",!0);

</script>
<!-- jQuery -->
<script src="/js/jquery.min.js"></script>
<!-- Bootstrap Core JavaScript -->
<script src="/js/bootstrap.min.js"></script>
<!-- Custom Theme JavaScript -->
<script src="/js/clean-blog.min.js"></script>
<!-- Custom JavaScript -->
<script src="/js/main.js"></script>

</body>

</html>