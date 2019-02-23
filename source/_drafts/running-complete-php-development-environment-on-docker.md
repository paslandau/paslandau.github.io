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
- we'll use a registry for our "built" container images

There will be a another part of this series that will explain how we mitigate some of those issues. Although the next one 
will probably start with an explanation of how to set up a CI pipeline based on Jenkins... but we'll see ¯\_(ツ)_/¯

Please subscribe to the [RSS feed](/feed.xml) or [via email](#newsletter) to get automatic notifications when the next part comes out :)

## Table of contents
- Intro
  - Reasons / Goal
    - easy setup in the team
    - updates/testing of infrastructure/ new PHP versions
- repo structure
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
When I started my current role as Head of Marketing Technology at ABOUT YOU back in 2016, we heavily
relied on Vagrant (namely: Homestead) as our development infrastructure. Though that was much better than 
working on our local machines, we've run into a couple of problems along the way (e.g. diverging software,
bloated images, slow starting times, complicated readme for onboarding, upgrading php, ...).

Roughly two years later we switched to Docker. The onboarding process is now reduced to 

````
make docker-setup
````

(well, excluding creating SSH keys and installing the required software like `make` and `docker`) 
and getting started each day is as easy as running

````
make docker-up
````

Everything that we need for the infrastructure is now under source control and committed in the same repository
that we use for our main application. In effect we get **the same infrastructure for every developer** including automatic
updates "for free". It is extremely easy to tinker around with updates / new tools due to the ephemeral nature of docker
as tear down and rebuild only take one command and a couple of minutes.

To get an idea how that feels, I'd recommend to check out the accompanying `php-docker-tutorial` repository,
switch to the branch of this tutorial and simply run `make`:

````
cd /c/codebase/
git clone https://github.com/paslandau/docker-php-tutorial.git
cd docker-php-tutorial
git checkout part_3
# print make targets
make
# create docker containers
make docker-setup
````

**Caution** 
- If you're running Windows and don't have `make` installed, see section 
  [Install make on Windows (MinGW)](#install-make-on-windows-mingw)
- If anything is occupying port 80 on your machine, you need to change the mapped `HTTP_PORT`, see section
  [Modyfing the .env file](#LINK)

## Structuring the repository
While playing around with docker I've tried different ways to "structure" files and folders and ended up with the following
concepts:
- everything related to docker is placed in a `.docker` directory placed on on the same level as the file of the main application
- in this directory
  - each service gets its own subdirectory for configuration
  - is a `.shared` folder containing scripts and configuration required by multiple services
  - is an `.env.example` file containing variables for the `docker-compose.yml`
- a Makefile with common instructions to control Docker is placed in the repository root

The result looks roughly like this:
````
├── .docker/
|   ├── .shared/
|   |   ├── config /
|   |   └── scripts /
|   ├── php-fpm/
|   |   └── Dockerfile
|   ├── ... <additional services>/
|   ├── .env.example
|   └──  docker-compose.yml
├── Makefile
├── index.php
└──  ... <additional app files>/
````

### The .docker folder
As I mentioned, for me it makes a lot of sense to keep the infrastructure definition close to the codebase, because
it is immediately available to every developer. For bigger projects with multiple components there will be a
code-infrastructure coupling anyways 
(e.g. in my experience it is usually not possible to simply switch mysql for postgresql without any other changes) 
and for a library it is a very convenient (although opinionated) way to get started. I personally find it rather 
frustrating when I want to contribute to an open source project but find myself spending a significant amount of time
setting the environment up correctly instead of being able to just work on the code.

Ymmv, though (e.g. because you don't want everybody with write access to your app repo also to be able to change your 
infrastructure code). We actually went a different route previously and had a second repository ("<app>-inf") 
that would contain the contents of the `.docker` folder. Worked as well, but we often ran into situations where 
the contents of the repo would be stale for some devs, plus it was simply additional overhead with not other benefits 
to us at that point. Maybe [git submodules](https://medium.com/@porteneuve/mastering-git-submodules-34c65e940407) will
enable us to get the best of both worlds - I'll blog about it once we try ;)

### The .shared folder
When dealing with multiple services, chances are high that some of those services will be configured similarly, e.g. for
- installing common software 
- setting up unix users (with the same ids)
- configuration (think php-cli for workers and php-fpm for web requests)

To avoid duplication, I place scripts (simple bash files) and config files in the `.shared` folder and make it available in
the build context for each service. I'll explain the process in more detail under 
[providing the correct build context](#LINK).

### `.env.example` and `docker-compose.yml`
`docker-compose` uses a [`.env` file](https://docs.docker.com/compose/environment-variables/#the-env-file)
for a convenient way to define and 
[`substitute environment variables`](https://docs.docker.com/compose/compose-file/#variable-substitution). 
Since this `.env` file is environment specific, it is **NOT**
part of the repository (i.e. ignored via `.gitignore`). Instead, we provide a `.env.example` file that contains 
the required environment variables including reasonable default values. A new dev would usually run 
`cp .env.example .env` after checking out the repository for the first time.

### The Makefile
`make` and `Makefile`s are among those things that I've heard about occasionally but never really cared to 
understand (mostly because I associated them with C). Boy, did I miss out. I was comparing different strategies
to provide code quality tooling (style checkers, static analyzers, tests, ...) and went from custom bash scripts
over [composer scripts](https://getcomposer.org/doc/articles/scripts.md) to finally end up at `Makefile`s. Some articles 
I would recommend are [Makefile for lazy developers](https://localheinz.com/blog/2018/01/24/makefile-for-lazy-developers/)
and [Why you Need a Makefile on your Project](https://blog.theodo.fr/2018/05/why-you-need-a-makefile-on-your-project/). 
Both are written with a PHP context in mind.

The `Makefile` serves as a central entry point and simplifies the management of the docker containers, e.g. for
(re-)building, starting, stopping, logging in, etc. Section [Self-documenting Makefile and targets for docker](#LINK)
will go deeper into that topic. 

## Fundamentals on building the containers
I assume that you are already somewhat familiar with `Dockerfile`s and have used `docker-compose` to orchestrate multiple 
services (if not, check out 
[Persisting image changes with a Dockerfile](blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#dockerfile) and
[Putting it all together: Meet docker-compose](blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#docker-compose)). But
there are some points I would like to cover in a little more detail.

### Understanding build context
There are two essential parts when building a container:

- the base image
- the build context

You can read about the official description in the [Dockerfile reference](http://docs.docker.com/engine/reference/builder/#usage).

For me, the gist is this: The build context defines the files and folders (recursively) on your machine that are send 
from the [Docker CLI](https://docs.docker.com/engine/reference/commandline/cli/) to 
the [Docker Daemon](https://docs.docker.com/engine/reference/commandline/dockerd/) that executes the build process
of a container so that you can reference those files in the Dockerfile (e.g. via `COPY`). The build context for all of our containers 
will be the `.docker` directory, so that all build processes have access to the `.shared` scripts and config. Yes, that
also means that the `php-fpm` container has access to files that are only relevant to the `mysql` container (for
instance), but the performance penalty is absolutely neglectable. Plus, as long as we don't actively `COPY` those irrelevant
files, they won't bloat up our images.

A couple of notes:
- I used to think that the build context is *always* tied to the location of the Dockerfile but that's only the default,
  it can be any directory
- the build context is **actually send** to the build process - i.e. you should avoid unnecessary files / folders as this might
  affect performance, especially on big files (don't use `/`!)
- similar to `git`, Docker knows the concept of a [`.dockerignore` file](https://docs.docker.com/engine/reference/builder/#dockerignore-file)
  to exclude files from being included in the build context

### Using `ENTRYPOINT` for pre-run configuration
Docker went back to the unix roots with the 
[do on thing and do it well philosophy](https://en.wikipedia.org/wiki/Unix_philosophy#Do_One_Thing_and_Do_It_Well) which is 
manifested in the [`CMD` and `ENTRYPOINT` instructions](https://medium.freecodecamp.org/docker-entrypoint-cmd-dockerfile-best-practices-abc591c30e21).

As I had a hard time understanding those instructions when I started with Docker, here's my take at a layman's terms description:
- since a container should do one thing, we need to specify that thing. That's what we do with `ENTRYPOINT`. Concrete examples:
  - a `mysql` container should probably run the `mysqld` daemon
  - a `php-fpm` container.. well, `php-fpm`
- the `CMD` is passed as the default argument to the `ENTRYPOINT`
- the `ENTRYPOINT` is executed every time we *run* a container. Some things can't be done during build but only at runtime
  (e.g. find the IP of the host from within a container - see section [TODO]) - `ENTRYPOINT` is a good solution for that problem
- technically, we can only override an already existing `ENTRYPOINT` from the base image. But: We can structure the new 
  `ENTRYPOINT` like a [decorator](https://en.wikipedia.org/wiki/Decorator_pattern) by adding `exec "$@"` at the end to 
  simulate inheritance from the parent image

To expand on the last point, consider the default 
[`ENTRYPOINT` of the current [2019-02-23; PHP 7.3] `php-fpm` image](https://github.com/docker-library/php/blob/640a30e8ff27b1ad7523a212522472fda84d56ff/7.3/stretch/fpm/docker-php-entrypoint)
````
#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

exec "$@"
````
In the [corresponding Dockerfile](https://github.com/docker-library/php/blob/640a30e8ff27b1ad7523a212522472fda84d56ff/7.3/stretch/fpm/Dockerfile#L223)
we find the following instructions:
````
# [...]
ENTRYPOINT ["docker-php-entrypoint"]
# [...]
CMD ["php-fpm"]
````
That means: When we run the container it will pass the string "php-fpm" to the `ENTRYPOINT` script `docker-php-entrypoint` 
as argument which will then execute it (due to the `exec "$@"` instruction at the end):
````
$ docker run --name test --rm php:fpm
[23-Feb-2019 14:49:20] NOTICE: fpm is running, pid 1
[23-Feb-2019 14:49:20] NOTICE: ready to handle connections
# php-fpm is running
# Hit ctrl + c to close the connection
$ docker stop test
````

We could now override the default `CMD` "php-fpm" with something else, e.g. a simple `echo "hello"`. The `ENTRYPOINT`
will happily execute it:
````
$ docker run --name test --rm php:fpm echo "hello"
hello
````
But now the `php-fpm` process isn't started any more. How can we echo "hello" but still keep the fpm process running?
By adding our own `ENTRYPOINT` script:
````
#!/bin/bash
echo 'hello'

exec "$@"
````
Full example (using [stdin to pass the Dockerfile](https://docs.docker.com/engine/reference/commandline/build/#build-with--) 
via [Heredoc string](https://stackoverflow.com/q/2953081/413531))
````
$ docker build -t my-fpm -<<'EOF'
FROM php:fpm

RUN  touch "/usr/bin/my-entrypoint.sh" \
  && echo "#!/bin/bash" >> "/usr/bin/my-entrypoint.sh" \
  && echo "echo 'hello'" >> "/usr/bin/my-entrypoint.sh" \
  && echo "exec \"\$@\"" >> "/usr/bin/my-entrypoint.sh" \
  && chmod +x "/usr/bin/my-entrypoint.sh" \
  && cat "/usr/bin/my-entrypoint.sh" \
;

ENTRYPOINT ["/usr/bin/my-entrypoint.sh", "docker-php-entrypoint"]
CMD ["php-fpm"]
EOF
````
Note that we added the `ENTRYPOINT` of the parent image `docker-php-entrypoint` as argument to our own `ENTRYPOINT` script
`/usr/bin/my-entrypoint.sh` so that we don't loose its functionality. And we need to define the `CMD` instruction explicitly,
because the one from the parent image is [automatically removed once we define our own `ENTRYPOINT`](https://stackoverflow.com/a/49031590/413531).

But: It works: 
````
$ docker run --name test --rm my-fpm
hello
[23-Feb-2019 15:43:25] NOTICE: fpm is running, pid 1
[23-Feb-2019 15:43:25] NOTICE: ready to handle connections
# Hit ctrl + c to close the connection
$ docker stop test
````

We will use that technique during this tutorial when [Resolving the `host.docker.internal` hostname (for Linux)](#LINK) and
[Sync the hosts SSH keys (for Windows)](#LINK)

### Synchronizing file and folder ownership on shared volumes
Docker makes it really easy to share files between containers by using [volumes](https://docs.docker.com/storage/volumes/). 
For simplicities sake, you can picture a volume simply as an additional disk that multiple containers have access to.
As long as you are only dealing with one container, life is easy: You can simply `chown` files to the correct user.
But since the containers might have a different user setup, permissions/ownership becomes a problem.

The first thing for me was understanding that file ownership does not depend on the user **name** but rather on the user **id**. 
And you might have guessed it: Two containers might have a user with the same name but with a different id. 
The same is true for groups, btw. You can check the id by running `su - <user name> -c id -s /bin/sh`, e.g.
````
su - www-data -c id -s /bin/sh
uid=33(www-data) gid=33(www-data) groups=33(www-data)
````

That's inconvenient but rather easy to solve in most cases, because we have full control over the containers and
can assign ids as we like, using `usermod -u <id> <name>`.


 - and it gets even
worse when the volume in question is mounted from the host system (at least for linux users ;)).


https://medium.com/@mccode/understanding-how-uid-and-gid-work-in-docker-containers-c37a01d01cf
### Installing php extensions
When php extensions are missing, googling will often point to answers for normal linux systems using `apt-get` or `yum`, 
e.g. `sudo apt-get install php-xdebug`. But for the official docker images, the recommended way is using the 
[docker-php-ext-configure, docker-php-ext-install, and docker-php-ext-enable helper scripts](https://github.com/docker-library/docs/blob/master/php/README.md#how-to-install-more-php-extensions).
Unfortunately, some extensions have rather complicated dependencies, so that the installation fails.
Fortunately, there is a great project on Github called 
[docker-php-extension-installer](https://github.com/mlocati/docker-php-extension-installer) that takes care of that for us
and is super easy to use:

````
FROM php:7.2-cli

ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/

RUN chmod uga+x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions xdebug
````

The readme also contains an 
[overview of supported extension](https://github.com/mlocati/docker-php-extension-installer#supported-php-extensions) 
per PHP version. 

### A real example: The workspace container
To give you a better idea how everything plays together in practice, we'll start by defining the "workspace" container.
We will use this container as our main development tool, i.e. we will point our IDE to this container
(to run our unit tests, for instance). If you are used to something like 
[Homestead on Vagrant](https://github.com/laravel/homestead), then consider this "workspace" of kinda the same thing.

Since it is also the "heaviest" container in the whole setup, it will conveniently force us to solve a lot of problems 
that are also relevant for other services.

#### phusion base image
First of all we will "break" with the docker mantra to make containers as small as possible, containing
only the minimum required software and use the [phusion base image](https://github.com/phusion/baseimage-docker) - a 
"A minimal Ubuntu base image modified for Docker-friendliness". This decision is inspired by the 
[laradoc workspace image](https://github.com/laradock/workspace) and has proven to be very convenient in a dev 
context for someone that is used to Vagrant virtual machines (especially homestead) or cloud instances on AWS EC2, 
Digital Ocean, etc.

**Dockerfile**
````
FROM phusion/baseimage:latest
````

#### software

#### users

#### ssh

#### php extensions and configuration

#### mounting host configuration (ssh keys, .gitconfig, ...)

#### Providing `host.docker.internal` for linux

### Refactoring shared scripts and config
- Note: File mode on windows

## Defining the services
  - php-fpm
  - nginx
  - php worker
  - redis
  - mysql
  - blackfire

## Setting up docker compose 
  - base setup in docker-compose.yml
  - .env files
  - override.yml
  - volumes
    - code, logging, cache, 

## Workflow
  - Using makefiles
    - Note: Installing make on windows (video/so question?)
    - self documenting
    - how to pass variables
    - testing for docker-context
      - Note: a word on conditionals
  - Bash
  - readme / setup in the team
    - make me-a-dev
   
   

### Appendix
## Fixing file modes in git 

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

