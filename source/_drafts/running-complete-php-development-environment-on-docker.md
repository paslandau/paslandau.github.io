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

### The `.shared` folder
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
  
### Dockerfile blueprint
The Dockerfiles for the containers will follow the structure outlined below:

````
FROM ...

# path to the directory where the Dockerfile lives
ARG DOCKERFILE_DIR="./"

# get the scripts from the build context and make sure they are executable
COPY ${DOCKERFILE_DIR}/../.shared/scripts/ /tmp/scripts/
RUN chmod +x -R /tmp/scripts/

# add users
ARG APP_USER=www-data
ARG APP_USER_ID=1000
ARG APP_GROUP=$(APP_USER)
ARG APP_GROUP_ID=$(APP_USER_ID)

RUN /tmp/scripts/create_user.sh ${APP_USER} ${APP_GROUP} ${APP_USER_ID} ${APP_GROUP_ID}

# install php extensions
RUN /tmp/scripts/install_php_extensions.sh

# install other (common) software
RUN /tmp/scripts/install_software.sh

# perform any other, container specific build steps
# [...]

# cleanup 
RUN /tmp/scripts/cleanup.sh

# set default work directory
WORKDIR "..."

# define ENTRYPOINT
ENTRYPOINT [...]
CMD [...]
````

The comments should suffice to give you an overview - so let's talk about the individual parts in detail.

### Synchronizing file and folder ownership on shared volumes
**Script: `create_user.sh`**

Docker makes it really easy to share files between containers by using [volumes](https://docs.docker.com/storage/volumes/). 
For simplicities sake, you can picture a volume simply as an additional disk that multiple containers have access to.
And since it's PHP we're talking about here, sharing the same application files is a common requirement 
(e.g. for `php-fpm`, `nginx`, `php-workers`).

As long as you are only dealing with one container, life is easy: You can simply `chown` files to the correct user.
But since the containers might have a different user setup, permissions/ownership becomes a problem. Checkout 
[this video on Docker & File Permissions](https://serversforhackers.com/c/dckr-file-permissions) for a practical 
example in a Laravel application.

The first thing for me was understanding that file ownership does not depend on the user **name** but rather on the user **id**. 
And you might have guessed it: Two containers might have a user with the same name but with a different id. 
The same is true for groups, btw. You can check the id by running `id <name>`, e.g.
````
id www-data
uid=33(www-data) gid=33(www-data) groups=33(www-data)
````

[![File ownership with multiple containers using a shared volume](/img/running-complete-php-development-environment-on-docker/docker-file-ownership-volume.png)](/img/running-complete-php-development-environment-on-docker/docker-file-ownership-volume.png)

That's inconvenient but rather easy to solve in most cases, because we have full control over the containers and
can [assign ids as we like](https://www.cyberciti.biz/faq/linux-change-user-group-uid-gid-for-all-owned-files/) 
(using `usermod -u <id> <name>`) and thus making sure every container uses the same user names with the same user ids.

Things get complicated when the volume isn't just a Docker volume but a shared folder on the host. This is usually
what we want for development, so that changes on the host are immediately reflected in all the containers.

[![File ownership with multiple containers using a shared volume from the host](/img/running-complete-php-development-environment-on-docker/docker-file-ownership-host.png)](/img/running-complete-php-development-environment-on-docker/docker-file-ownership-host.png)

This issue **only affects users with a linux host system**! Docker Desktop (previously known as Docker for Mac / Docker for Win)
has a virtualization layer in between that will effectively erase all ownership settings and make everything shared
from the host available to every user in a container. But even for those cases it makes sense to apply the same solution.

We use the following script to ensure a consistent user setup when building a container:

````
#!/usr/bin/env bash
APP_USER=$1
APP_GROUP=$2
APP_USER_ID=$3
APP_GROUP_ID=$4

new_user_id_exists=$(id ${APP_USER_ID} > /dev/null 2>&1; echo $?) 
if [ "$new_user_id_exists" = "0" ]; then
    (>&2 echo "ERROR: APP_USER_ID $APP_USER_ID already exists - Aborting!");
    exit 1;
fi

new_group_id_exists=$(getent group ${APP_GROUP_ID} > /dev/null 2>&1; echo $?) 
if [ "$new_group_id_exists" = "0" ]; then
    (>&2 echo "ERROR: APP_GROUP_ID $APP_GROUP_ID already exists - Aborting!");
    exit 1;
fi

old_user_id=$(id -u ${APP_USER})
old_user_exists=$(id -u ${APP_USER} > /dev/null 2>&1; echo $?) 
old_group_id=$(getent group ${APP_GROUP} | cut -d: -f3)
old_group_exists=$(getent group ${APP_GROUP} > /dev/null 2>&1; echo $?)

if [ "$old_group_id" != "${APP_GROUP_ID}" ]; then
    # create the group
    groupadd -f ${APP_GROUP}
    # and the correct id
    groupmod -g ${APP_GROUP_ID} ${APP_GROUP}
    if [ "$old_group_exists" = "0" ]; then
        # set the permissions of all "old" files and folder to the new group
        find / -group $old_group_id -exec chgrp -h ${APP_GROUP} {} \;
    fi
fi

if [ "$old_user_id" != "${APP_USER_ID}" ]; then
    # create the user if it does not exist
    if [ "$old_user_exists" != "0" ]; then
        useradd ${APP_USER} -g ${APP_GROUP}
    fi
    
    # make sure the home directory exists with the correct permissions
    mkdir -p /home/${APP_USER} && chmod 755 /home/${APP_USER} && chown ${APP_USER}:${APP_GROUP} /home/${APP_USER} 
    
    # change the user id, set the home directory and make sure the user has a login shell
    usermod -u ${APP_USER_ID} -m -d /home/${APP_USER} ${APP_USER} -s $(which bash)

    if [ "$old_user_exists" = "0" ]; then
        # set the permissions of all "old" files and folder to the new user 
        find / -user $old_user_id -exec chown -h ${APP_USER} {} \;
    fi
fi
````

The script is then called from the `Dockerfile` via
````
ARG APP_USER=www-data
ARG APP_USER_ID=1000
ARG APP_GROUP=$(APP_USER)
ARG APP_GROUP_ID=$(APP_USER_ID)

RUN /tmp/scripts/create_user.sh ${APP_USER} ${APP_GROUP} ${APP_USER_ID} ${APP_GROUP_ID}
````

The default values can be overriden by passing in the corresponding 
[build args](https://docs.docker.com/engine/reference/commandline/build/#set-build-time-variables---build-arg). Linux 
users should use the user id of the user on their host system.

### Installing php extensions
**Script: `install_php_extensions.sh`**

When php extensions are missing, googling will often point to answers for normal linux systems using `apt-get` or `yum`, 
e.g. `sudo apt-get install php-xdebug`. But for the official docker images, the recommended way is using the 
[docker-php-ext-configure, docker-php-ext-install, and docker-php-ext-enable helper scripts](https://github.com/docker-library/docs/blob/master/php/README.md#how-to-install-more-php-extensions).
Unfortunately, some extensions have rather complicated dependencies, so that the installation fails.
Fortunately, there is a great project on Github called 
[docker-php-extension-installer](https://github.com/mlocati/docker-php-extension-installer) that takes care of that for us
and is super easy to use:

````
FROM php:7.3-cli

ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/

RUN chmod uga+x /usr/local/bin/install-php-extensions && sync && install-php-extensions xdebug
````

The readme also contains an 
[overview of supported extension](https://github.com/mlocati/docker-php-extension-installer#supported-php-extensions) 
per PHP version. To ensure that all of our PHP containers have the same extensions, we provide the following script:

````
#!/usr/bin/env bash
# add wget
apt-get update -yqq && apt-get -f install -yyq wget

# download helper script
wget -q -O /usr/local/bin/install-php-extensions https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions \
    | (echo "Failed while downloading php extension installer!"; exit 1)

# install all required extensions
chmod uga+x /usr/local/bin/install-php-extensions && sync && install-php-extensions \
    redis \
    pdo_mysql \
    mysqli \
    pcntl \
    zip \
    opcache \
;
````

### Installing common software
**Script: `install_software.sh`**

There is a certain set of software that I want to have readily available in every container. Since this a development 
setup, I'd prioritize ease of use / debug over performance / image size, so this might seem like a little "too much".
I think I'm also kinda spoiled by my Homestead past, because it's so damn convenient to have everything right at
your fingertips :)

Anyway, the script is straight forward:
````
#!/bin/sh

apt-get update -yqq && apt-get install -yqq \
    curl \
    dnsutils \
    gdb \
    git \
    htop \
    iputils-ping \
    iproute2 \
    ltrace \
    make \
    mysql-client \
    procps \
    redis-tools \
    strace \
    sudo \
    sysstat \
    unzip \
    vim \
    wget \
;
````

Notes:
- this list should match your own set of go-to tools. I'm fairly open to adding new stuff here if it speeds up the
  dev workflow
- sorting the software alphabetically is a good practice and avoid unnecessary duplicates. Don't do this by hand, though!
  If you're using an IDE / established text editor, chances are high that this is either a build-in functionality or
  there's a plugin available. I'm using [Lines Sorter for PhpStorm](https://plugins.jetbrains.com/plugin/5919-lines-sorter)

### Cleaning up
**Script: `cleanup.sh`**

Nice and simple:

````
#!/usr/bin/env bash

apt-get clean
rm -rf /var/lib/apt/lists/* \
       /tmp/* \
       /var/tmp/* \
       /var/log/lastlog \
       /var/log/faillog
````

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

#### Providing `host.docker.internal` for linux host systems
**Script: `docker-entrypoint/resolve-docker-host-ip.sh`**

In the last part of this tutorial series, I explained how to build the 
[Docker container in a way that it plays nice with PhpStorm and Xdebug](/blog/setup-phpstorm-with-xdebug-on-docker). 
The key parts were SSH access and the magical `host.docker.internal` DNS entry. This works great for Docker Desktop (Windows and Mac)
but not for linux. The DNS entry [doesn't exist there](https://github.com/docker/for-linux/issues/264). 
Since we rely on that entry 
[to make debugging possible](/blog/setup-phpstorm-with-xdebug-on-docker/#fix-xdebug-on-phpstorm-when-run-from-a-docker-container),
we will set it "manually" [if the host doesn't exist](ttps://stackoverflow.com/a/24049165/413531) 
with the following script 
(inspired by [Access host from a docker container](https://dev.to/bufferings/access-host-from-a-docker-container-4099)):

````
#!/bin/sh
set -e

HOST_DOMAIN="host.docker.internal"

# check if the host exists - this will fail on linux
if dig ${HOST_DOMAIN} | grep -q 'NXDOMAIN'
then
  # resolve the host IP
  HOST_IP=$(ip route | awk 'NR==1 {print $3}')
  # and write it to the hosts file
  echo "$HOST_IP\t$HOST_DOMAIN" >> /etc/hosts
fi

exec "$@"
````

The script is placed at `.shared/docker-entrypoint/resolve-docker-host-ip.sh` and added as `ENTRYPOINT` in the Dockerfile via 

````
COPY ${DOCKERFILE_DIR}/../.shared/scripts/ /tmp/scripts/

RUN mkdir -p /bin/docker-entrypoint \
 && cp /tmp/scripts/docker-entrypoint/* /bin/docker-entrypoint \
;

ENTRYPOINT ["/bin/docker-entrypoint/resolve-docker-host-ip.sh", ...]
````

Notes:
- since this script depends on runtime configuration, we need to run it as an `ENTRYPOINT`
- there is no need to explicitly check for the OS type - we simply make sure that the DNS entry exists
  and add it if it doesn't
- we're using `dig` and `ip` which need to be installed via 
  ````
  apt-get update -yqq && apt-get install -yqq \
      dnsutils \
      iproute2 \
  ;
  ````
  during the build time of the container
- this workaround is only required in containers we want to debug via xdebug

#### Adding SSH keys from the host system
**Script: `docker-entrypoint/copy-host-ssh.sh`**

Another challenge popped up when we needed access to our private bitbucket repository from within a Docker container
to run `composer install`. The access is controlled via SSH keys, i.e. each dev has added his public SSH key to
his bitbucket account. Works great from the host... not so much from a within a container, because we can't simply
"bake" the private key into the image at build time. 

So we do it at runtime. Should work by simply mounting our keys, right? Well.. might work on linux (when using 
the [correct user setup](#LINK-to-create-user-script)), but it definitely does not work on Windows as the mounted
files will have the wrong permissions. The [required permissions](https://superuser.com/a/215506) are as follows:

> .ssh directory: 700 (drwx------)
> public key (.pub file): 644 (-rw-r--r--)
> private key (id_rsa): 600 (-rw-------)
> home directory: 755 (drwxr-xr-x)).

**Caution:** Getting this wrong is really annoying, because the error messages will usually be like 
`ssh permission denied (publickey)` instead of `wrong permissions on .ssh folder` or so.

Luckily, `ENTRYPOINT` comes to the rescue again. We'll use the following script 
(inspired by [Docker Tip #56: Volume Mounting SSH Keys into a Docker Container](https://nickjanetakis.com/blog/docker-tip-56-volume-mounting-ssh-keys-into-a-docker-container)):

````
#!/bin/sh
set -e

# replace from outside!
user=__APP_USER
ssh_key_file=__SSH_KEY_FILE

ssh_dir="/home/$user/.ssh"

# caution: we rely on the host to mount its SSH keys to /tmp/.ssh/
cp /tmp/.ssh/${ssh_key_file} ${ssh_dir}/${ssh_key_file}
chown -R ${user}: ${ssh_dir}
chmod 700 ${ssh_dir}
chmod 600 ${ssh_dir}/${ssh_key_file}

exec "$@"
````

The script is placed at `.shared/docker-entrypoint/copy-host-ssh.sh` and added as `ENTRYPOINT` in the Dockerfile via 

````
COPY ${DOCKERFILE_DIR}/../.shared/scripts/ /tmp/scripts/

ARG APP_USER=www-data
ARG SSH_KEY_FILE=id_rsa

RUN mkdir -p /bin/docker-entrypoint \
 && cp /tmp/scripts/docker-entrypoint/* /bin/docker-entrypoint \
 && RUN sed -i -e "s#__APP_USER#${APP_USER}#" /bin/docker-entrypoint/copy-host-ssh.sh \
 && RUN sed -i -e "s#__SSH_KEY_FILE#${SSH_KEY_FILE}#" /bin/docker-entrypoint/copy-host-ssh.sh \
;

ENTRYPOINT ["/bin/docker-entrypoint/copy-host-ssh.sh", ...]
````

Notes:
- we don't pass the variables to the script but rather use `sed` to replace them directly in the file.
  I'm using this workaround because you 
  [can't use variables in the exec form of `ENTRYPOINT`](https://github.com/moby/moby/issues/4783)
- due to the `exec "$@"` we can chain the `ENTRYPOINT` scripts if required, e.g. 
  ````
  ENTRYPOINT ["/bin/docker-entrypoint/resolve-docker-host-ip.sh", "/bin/docker-entrypoint/copy-host-ssh.sh", ...]
  ````
- on startup we must remember to mount the SSH folder from the host to `/tmp/.ssh/` in the container.
  This is defined [in the `docker-compose` setup](#LINK-to-docker-compose) later on 

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

