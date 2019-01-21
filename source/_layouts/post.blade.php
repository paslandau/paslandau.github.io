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
                <a href="https://de.linkedin.com/in/pascallandau">LinkedIn</a> or
                <a href="https://twitter.com/PascalLandau">Twitter</a>
                or simply subscribe to my <a href="/feed.xml">RSS feed</a>
                or go the crazy route and subscribe via mail
                and don't forget to leave a comment :)
                </p>
                <!-- Begin Mailchimp Signup Form -->
                <link href="//cdn-images.mailchimp.com/embedcode/classic-10_7.css" rel="stylesheet" type="text/css">
                <style type="text/css">
                    #mc_embed_signup{background:#bae1ff; clear:left; font:14px Helvetica,Arial,sans-serif; border-radius: 20px}
                    #mc_embed_signup h4 {padding:1em 0 0 1em}
                    #mc-embedded-subscribe-form input[type=checkbox]{display: inline; width: auto;margin-right: 10px;}
                    #mergeRow-gdpr {margin-top: 20px;}
                    #mergeRow-gdpr fieldset label {font-weight: normal;}
                    #mc-embedded-subscribe-form .mc_fieldset{border:none;min-height: 0px;padding-bottom:0px;}
                </style>
                <div id="mc_embed_signup">
                    <h4 id="newsletter">Subscribe to posts via mail</h4>
                    <form action="https://pascallandau.us20.list-manage.com/subscribe/post?u=89e1c97fa614ded06a44fbcfd&amp;id=852c78303c" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>
                        <div id="mc_embed_signup_scroll">
                            <div class="mc-field-group">
                                <label for="mce-EMAIL">Email Address </label>
                                <input type="email" value="" name="EMAIL" class="required email" id="mce-EMAIL">
                            </div>
                            <div class="mc-field-group">
                                <label for="mce-FNAME">First Name </label>
                                <input type="text" value="" name="FNAME" class="required" id="mce-FNAME">
                            </div>
                            <div id="mce-responses" class="clear">
                                <div class="response" id="mce-error-response" style="display:none"></div>
                                <div class="response" id="mce-success-response" style="display:none"></div>
                            </div>    <!-- real people should not fill this in and expect good things - do not remove this or risk form bot signups-->
                            <div style="position: absolute; left: -5000px;" aria-hidden="true"><input type="text" name="b_89e1c97fa614ded06a44fbcfd_852c78303c" tabindex="-1" value=""></div>
                            <div class="clear"><input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button"></div>
                            <div id="mergeRow-gdpr" class="mergeRow gdpr-mergeRow content__gdprBlock mc-field-group">
                                <div class="content__gdprLegal">
                                    <small>
                                        We use Mailchimp as our newsletter provider. By clicking subscribe, you acknowledge that your
                                        information will be transferred to Mailchimp for processing.
                                        <a href="https://mailchimp.com/legal/" target="_blank" rel="nofollow">Learn more about Mailchimp's privacy practices here.</a>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <script type='text/javascript' src='//s3.amazonaws.com/downloads.mailchimp.com/js/mc-validate.js'></script><script type='text/javascript'>(function($) {window.fnames = new Array(); window.ftypes = new Array();fnames[0]='EMAIL';ftypes[0]='email';fnames[1]='FNAME';ftypes[1]='text';fnames[2]='LNAME';ftypes[2]='text';fnames[3]='ADDRESS';ftypes[3]='address';fnames[4]='PHONE';ftypes[4]='phone';fnames[5]='BIRTHDAY';ftypes[5]='birthday';}(jQuery));var $mcj = jQuery.noConflict(true);</script>
                <!--End mc_embed_signup-->
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