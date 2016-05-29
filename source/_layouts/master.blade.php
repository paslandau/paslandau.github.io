<?php
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

    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link href="/css/clean-blog.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/main.css">

    <!-- Custom Fonts -->
    <link href="http://maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet"
          type="text/css">
    <link href='http://fonts.googleapis.com/css?family=Lora:400,700,400italic,700italic' rel='stylesheet'
          type='text/css'>
    <link href='http://fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800'
          rel='stylesheet' type='text/css'>

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
                </ul>
                <p class="copyright text-muted">&copy; {{$site["host"]}} <?= date("Y") ?></p>
            </div>
        </div>
    </div>
</footer>

<!-- jQuery -->
<script src="/js/jquery.min.js"></script>
<!-- Bootstrap Core JavaScript -->
<script src="/js/bootstrap.min.js"></script>
<!-- Custom Theme JavaScript -->
<script src="/js/clean-blog.min.js"></script>
<!-- Custom Theme JavaScript -->
<script src="/js/main.js"></script>

</body>

</html>