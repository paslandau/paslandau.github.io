---
extends: _layouts.post
section: content
title: "Running a complete PHP Development Environment/Infrastructure on Docker [Tutorial Part 3]"
subheading: "... including php-cli workers, php-fpm, nginx, mysql, redis and blackfire."
h1: "Setting up a Docker-based development environment/infrastructure for PHP"
description: "Dockerfiles, folder structures, docker-compose, ... - In this article we're tying everything together to set up our fully-fledged PHP development environment on Docker."
author: "Pascal Landau"
published_at: "2019-01-19 12:00:00"
vgwort: ""
category: "development"
slug: "running-complete-php-development-environment-on-docker"
---

In the third part of this tutorial series on developing PHP on Docker we'll tie everything together,
polish some stuff up (SSH keys, user permissions, ...), build the complete development infrastructure
via docker-compose and provide a nice, clean workflow to start in a productive coding day.

If you're still completely new to Docker, you might want to start with the first part
[Setting up PHP, PHP-FPM and NGINX for local development on Docker](/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/)
and also take a look at the second one
[Setting up PhpStorm with Xdebug for local development on Docker](/blog/setup-phpstorm-with-xdebug-on-docker/) and then
come back here - just to avoid some unnecessary confusion :)

**Note**: The setup that I am going to use is for development purposes only! I do **not** recommend that you use it
in production. When moving to production, there are some more things to keep in mind:
- source code will not be synced from a host system but rather be pre-build
- all containers will contain much less software

There will be a another part of this series that will explain how we mitigate some of those issues. Although the next one 
will probably start with an explanation of how to set up a CI pipeline based on Jenkins... but we'll see ¯\_(ツ)_/¯

Please subscribe to the [RSS feed](/feed.xml) or [via email](#newsletter) to get automatic notifications when that part comes out :)

## Table of contents
- Defining the docker containers
  - workspace
  - php-fpm
  - nginx
  - php worker
  - redis
  - mysql
  - blackfire
- Setting up docker compose 
  - base setup in docker-compose.yml
  - override.yml
  - volumes
    - code, logging, cache, 
- Final touches
  - Syncing the developers SSH keys
  - Making host.docker.internal available on all operating systems
  - Retaining gobal host settings (e.g. .gitignore, .gitsettings)
- Workflow
  - Using makefiles
  - Bash
  
---  
- Intro
  - Reasons / Goal
    - easy setup in the team
    - updates/testing of infrastructure/ new PHP versions
  - folder structure
  - build context
  - shared scripts
    - Note: File mode on windows
  - shared config files
  - using entrypoint for post-build/pre-run config
- Defining the docker containers
  - workspace
    - ssh 
  - php-fpm
  - nginx
  - php worker
  - redis
  - mysql
  - blackfire
- Setting up docker compose 
  - base setup in docker-compose.yml
  - .env files
  - override.yml
  - volumes
    - code, logging, cache, 
- Final touches
  - Syncing the developers SSH keys
  - Making host.docker.internal available on all operating systems
  - Retaining gobal host settings (e.g. .gitignore, .gitsettings)
- Workflow
  - Using makefiles
    - Note: Installing make on windows (video/so question?)
    - self documenting
    - how to pass variables
    - testing for docker-context
      - Note: a word on conditionals
  - Bash
  - readme / setup in the team
    - make me-a-dev
    
---

## Introduction
When I started my current role as [LINK] Head of Marketing Technology at ABOUT YOU back in 2016, we heavily
relied on Vagrant (namely: Homestead) as our development infrastructure. Though that was much better than 
working on our local machines, we've run into a couple of problems along the way (e.g. diverging software,
bloated images, slow starting times, complicated readme for onboarding, upgrading php, ...).

Roughly two years later we  switched to Docker. The onboarding process is now reduced
to 
````
make me-a-dev
````
(well, excluding creating SSH keys and installing the required software like make and docker) 
and getting started each day is as easy as running
````
make docker-up
````

Everything that we need for the infrastructure is now under source control and committed in the same repository
that we use for our main application. In effect we get the same infrastructure for every developer including automatic
updates "for free". It is extremely easy to tinker around with updates / new tools due to the ephemeral nature of docker
as tear down and rebuild only take one command and a couple of minutes.

### Structuring the Docker containers
While tinkering with docker I've tried different ways to "structure" files and folders and ended up with the following
concepts:
- everything related to docker is placed in a `.docker` directory
- each service gets its own directory in the `.docker` directory
- there is a `.shared` folder containing scripts and configuration required by multiple services
- 

We're gonna setup the following folder structure:
````
+ app/
  + .shared/
    + scripts/
      + dev/
        +
    + config/
  + php-worker/
  + php-fpm/
  + nginx/
  + redis/
  + mysql/
  + workspace/
````

````
+ app/
  + .shared/
    + scripts/
      + dev/
        +
    + config/
  + php-worker/
  + php-fpm/
  + nginx/
  + redis/
  + mysql/
  + workspace/
  
  + nginx\
    + conf.d\
      - site.conf
    - Dockerfile
````

  - folder structure

  - build context
  - shared scripts
    - Note: File mode on windows
  - shared config files
  - using entrypoint for post-build/pre-run config
- Defining the docker containers
  - workspace
    - ssh 
  - php-fpm
  - nginx
  - php worker
  - redis
  - mysql
  - blackfire
- Setting up docker compose 
  - base setup in docker-compose.yml
  - .env files
  - override.yml
  - volumes
    - code, logging, cache, 
- Final touches
  - Syncing the developers SSH keys
  - Making host.docker.internal available on all operating systems
  - Retaining gobal host settings (e.g. .gitignore, .gitsettings)
- Workflow
  - Using makefiles
    - Note: Installing make on windows (video/so question?)
    - self documenting
    - how to pass variables
    - testing for docker-context
      - Note: a word on conditionals
  - Bash
  - readme / setup in the team
    - make me-a-dev
    
## Install make on Windows (MinGW)
`make` doesn't exist on Windows and is also not part of the standard installation of MinGW 
(click here to learn how to setup [MinGW](/blog/phpstorm-with-vagrant-using-laravel-homestead-on-windows-10/#git-and-git-bash))
Setting is up is kinda straight forward but as with "everything UI" it's easier if you can 
actually "see what I'm doing" - so here's a video:

<iframe width="560" height="315" src="https://www.youtube.com/embed/rLraYQK4Fzs" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

The steps are as follows:
- Set up `mingw-get` (basically `apt-get`)
  - Instructions: http://www.mingw.org/wiki/getting_started#toc5
  - Download: https://sourceforge.net/projects/mingw/files/Installer/mingw-get-setup.exe/download
  - Install and [add the `bin/` directory to `PATH` (shortcut `systempropertiesadvanced`)](/blog/php7-with-xdebug-2-4-for-phpstorm-on-windows-10/#the-path-variable).
    Notes: 
    - Do not use an installation path that contains spaces
    - The installation path can be different from your MinGW location
- Install `mingw32-make` via
  ````
  mingw-get install mingw32-make
  ````
  - create the file `bin/make` with the content
    ````
    mingw32-make.exe $*
    ````
  - Note: Sometimes Windows won't recognize non-.exe files - so instead of `bin/make` you might need to name the file
    `bin/make.exe` (with the same content)
- Open a new shell and type `make`. The output should look something like this
  ````
  $ make
  mingw32-make: *** Keine Targets angegeben und keine ¦make¦-Steuerdatei gefunden.  Schluss.
  ````

