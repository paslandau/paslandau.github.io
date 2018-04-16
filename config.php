<?php

$config = [
    'production' => false,
    'site' => [
        "title" => "pascallandau.com",
        "description" => "Personal website of Pascal Landau",
        'host' => "www.pascallandau.com",
        'scheme' => "https://",
    ],
    'social' => [
        "twitter" => "https://twitter.com/PascalLandau",
        "fb" => "https://www.facebook.com/pascal.landau",
        "github" => "https://github.com/paslandau/",
        "rss" => "/feed.xml",
    ]
];

$config["site"]["url"] = $config["site"]["scheme"].$config["site"]["host"];

return $config;