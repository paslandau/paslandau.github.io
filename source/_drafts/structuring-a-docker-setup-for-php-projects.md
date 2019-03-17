---
extends: _layouts.post
section: content
title: "How to structure a Docker setup for a PHP Project [Tutorial Part 3]"
subheading: "... folder structure, Dockerfile templates and general fundamentals"
h1: "Structuring a Docker setup for PHP Projects"
description: "Dockerfiles, folder structures, etc. - In this article I'll got through some fundamentals for a PHP development environment on Docker."
author: "Pascal Landau"
published_at: "2019-03-17 12:00:00"
vgwort: ""
category: "development"
slug: "structuring-a-docker-setup-for-php-projects"
---

In the third part of this tutorial series on developing PHP on Docker we'll lay the fundamentals to
build a complete development infrastructure and explain how to "structure" the Docker setup as part
of a PHP project. Structure as in 
- folder structure ("what to put where")
- Dockerfile templates
- solving common problems

If you're still completely new to Docker, you might want to start with the first part
[Setting up PHP, PHP-FPM and NGINX for local development on Docker](/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/)
and also take a look at the second one
[Setting up PhpStorm with Xdebug for local development on Docker](/blog/setup-phpstorm-with-xdebug-on-docker/) and then
come back here.

In the next part we'll develop the  *actual* Dockerfiles to set up the development infrastructure.

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

Everything that we need for the infrastructure is now under source control and committed in the same repository
that we use for our main application. In effect we get **the same infrastructure for every developer** including automatic
updates "for free". It is extremely easy to tinker around with updates / new tools due to the ephemeral nature of docker
as tear down and rebuild only take one command and a couple of minutes.

## Structuring the repository
While playing around with docker I've tried different ways to "structure" files and folders and ended up with the following
concepts:
- everything related to docker is **placed in a `.docker` directory placed on on the same level as the main application**
- in this directory
  - each service gets its own subdirectory for configuration
  - is a **`.shared` folder containing scripts and configuration** required by multiple services
  - is an **`.env.example`** file containing variables for the **`docker-compose.yml`**
- a **Makefile** with common instructions to control Docker is placed in the repository root

The result looks roughly like this:
````
<project>/
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
As I mentioned, for me it makes a lot of sense to keep the **infrastructure definition close to the codebase**, because
it is immediately available to every developer. For bigger projects with multiple components there will be a
code-infrastructure-coupling anyways 
(e.g. in my experience it is usually not possible to simply switch MySQL for PostgreSQL without any other changes) 
and for a library it is a very convenient (although opinionated) way to get started. 

I personally find it rather 
frustrating when I want to contribute to an open source project but find myself spending a significant amount of time
setting the environment up correctly instead of being able to just work on the code.

Ymmv, though (e.g. because you don't want everybody with write access to your app repo also to be able to change your 
infrastructure code). We actually went a different route previously and had a second repository ("<app>-inf") 
that would contain the contents of the `.docker` folder: 

````
<project-inf>/
├── .shared/
|   ├── config /
|   └── scripts /
├── php-fpm/
|   └── Dockerfile
├── ... <additional services>/
├── .env.example
└──  docker-compose.yml

<project>/
├── index.php
└──  ... <additional app files>/
````

Worked as well, but we often ran into situations where 
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
(re-)building, starting, stopping, logging in, etc. 

There will be a dedicated part in this tutorial series that goes into the details of the Makefile

## Fundamentals on building the containers
I assume that you are already somewhat familiar with `Dockerfile`s and have used `docker-compose` to orchestrate multiple 
services (if not, check out 
[Persisting image changes with a Dockerfile](blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#dockerfile) and
[Putting it all together: Meet docker-compose](blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#docker-compose)). But
there are some points I would like to cover in a little more detail.

### Understanding build context
There are two essential parts when building a container:

- the Dockerfile
- the build context

You can read about the official description in the [Dockerfile reference](http://docs.docker.com/engine/reference/builder/#usage).

For me, the gist is this: The build context defines the files and folders (recursively) on your machine that are send 
from the [Docker CLI](https://docs.docker.com/engine/reference/commandline/cli/) to 
the [Docker Daemon](https://docs.docker.com/engine/reference/commandline/dockerd/) that executes the build process
of a container so that you can reference those files in the Dockerfile (e.g. via `COPY`). The build context for all of our containers 
will be the `.docker` directory, so that all build processes have access to the `.shared` scripts and config. 

Yes, that
also means that the `php-fpm` container has access to files that are only relevant to the `mysql` container (for
instance), but the performance penalty is absolutely neglectable. Plus, as long as we don't actively `COPY` those irrelevant
files, they won't bloat up our images.

A couple of notes:
- I used to think that the build context is *always* tied to the location of the Dockerfile but that's only the default,
  it can be any directory
- the build context is **actually send** to the build process - i.e. you should avoid unnecessary files / folders as this might
  affect performance, especially on big files (iaw: don't use `/` as context!)
- similar to `git`, Docker knows the concept of a [`.dockerignore` file](https://docs.docker.com/engine/reference/builder/#dockerignore-file)
  to exclude files from being included in the build context
  
### Dockerfile template
The Dockerfiles for the containers will follow the structure outlined below:

````
FROM ...

# path to the directory where the Dockerfile lives
ARG SERVICE_DIR="./"

# get the scripts from the build context and make sure they are executable
COPY ${SERVICE_DIR}/../.shared/scripts/ /tmp/scripts/
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
from the host available to every user in a container.

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
        find / -group $old_group_id -exec chgrp -h ${APP_GROUP} {} \; || true
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
        find / -user $old_user_id -exec chown -h ${APP_USER} {} \; || true
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
[build args](https://docs.docker.com/engine/reference/commandline/build/#set-build-time-variables---build-arg). 
We well do that via the `.env` file for the `docker-compose.yml`.

Linux users should use the user id of the user on their host system - for Docker Desktop users the defaults are fine.

### Modifying configuration files
For most services we probably need some custom configuration settings, like
- setting php.ini values
- changing the default user of a service 
- changing the location of logfiles

There are a couple of  
[common approaches to modify application configuration in docker](https://dantehranian.wordpress.com/2015/03/25/how-should-i-get-application-configuration-into-my-docker-containers/)
an we are currently trying to stick to two rules:
1. provide additional files that override defaults
2. change non-static values with a simple search and replace via `sed` during the container build

#### Providing additional config files
Most services allow the specification of additional configuration files that override the default values in 
a default config file. This is great because we only need to define the settings that we actually care about 
instead of copying a full file with lots of redundant values.

Take the [`php.ini` file for example](http://php.net/manual/en/configuration.file.php#configuration.file.scan) for example:
It allows to places additional `.ini` files in a specific directory that override the default values. An easy way
to find this directory is `php -i | grep "additional .ini"`:

````
$ php -i | grep "additional .ini"
Scan this dir for additional .ini files => /usr/local/etc/php/conf.d
````

So instead of providing a "full" `php.ini` file, we will use a `zz-app.ini` file instead, that **only** contains the
.ini settings we actually want to change and place it under `/usr/local/etc/php/conf.d`. 

Why `zz-`? Because

> [...] Within each directory, PHP will scan all files ending in .ini in alphabetical order.

so if we want to ensure that our .ini files comes last (overriding all previous settings), we'll give it a
corresponding prefix :)

The full process would look like this:
- place the file in the `.docker` folder, e.g. at `.docker/.shared/config/php/conf.d/zz-app.ini`
- pass the folder as build context
- in the Dockerfile, use `COPY .shared/config/php/conf.d/zz-app.ini /usr/local/etc/php/conf.d/zz-app.ini`

Notes:
- this will only work for some services but not for all (e.g. it does not for nginx)

#### Changing non-static values
**Script: `modify_config.sh`**

Some configuration values are subject to local settings and thus should not be hard coded in configuration files. 
Take the `memory_limit` configuration for `php-fpm` as an example: Maybe someone in the team can only dedicate
a limited amount of memory to docker, so the `memory_limit` has to be kept lower than usual.

We'll account for that fact by using a variable prefixed by `__` instead of the real value and replace it with
a dynamic argument in the Dockerfile. Example for the aforementioned `zz-app.ini`:

````
memory_limit = __MEMORY_LIMIT
````

We use the following script `modify_config.sh` to replace the value:

````
#!/usr/bin/env bash
CONFIG_FILE=$1
VAR_NAME=$2
VAR_VALUE=$3

sed -i -e "s#${VAR_NAME}#${VAR_VALUE}#" "${CONFIG_FILE}"
````

The script is then called from the `Dockerfile` via
````
ARG PHP_FPM_MEMORY_LIMIT=1024M

RUN /tmp/scripts/modify_config.sh \
    "/usr/local/etc/php/conf.d/zz-app.ini" \
    "__MEMORY_LIMIT" \
    "${PHP_FPM_MEMORY_LIMIT}" \
;
````

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

If you're not sure which extensions are required by your application, give the 
[ComposerRequireChecker](https://github.com/maglnet/ComposerRequireChecker) a try.

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
- sorting the software alphabetically is a good practice to avoid unnecessary duplicates. Don't do this by hand, though!
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

#### Providing `host.docker.internal` for linux host systems
**Script: `docker-entrypoint/resolve-docker-host-ip.sh`**

In the previous part of this tutorial series, I explained how to build the 
[Docker container in a way that it plays nice with PhpStorm and Xdebug](/blog/setup-phpstorm-with-xdebug-on-docker). 
The key parts were SSH access and the magical `host.docker.internal` DNS entry. This works great for Docker Desktop (Windows and Mac)
but not for Linux. The DNS entry [doesn't exist there](https://github.com/docker/for-linux/issues/264). 
Since we rely on that entry 
[to make debugging possible](/blog/setup-phpstorm-with-xdebug-on-docker/#fix-xdebug-on-phpstorm-when-run-from-a-docker-container),
we will set it "manually" [if the host doesn't exist](https://stackoverflow.com/a/24049165/413531) 
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
COPY ${SERVICE_DIR}/../.shared/scripts/ /tmp/scripts/

RUN mkdir -p /bin/docker-entrypoint \
 && cp /tmp/scripts/docker-entrypoint/* /bin/docker-entrypoint \
;

ENTRYPOINT ["/bin/docker-entrypoint/resolve-docker-host-ip.sh", ...]
````

Notes:
- since this script depends on runtime configuration, we need to run it as an `ENTRYPOINT`
- there is no need to explicitly check for the OS type - we simply make sure that the DNS entry exists
  and add it if it doesn't
- we're using `dig` (package `dnsutils`) and `ip` (package `iproute2`) which need to be installed 
   during the build time of the container. They are already part of [TODO LINK]
- this workaround is only required in containers we want to debug via xdebug

## Defining services: php-fpm and nginx container
Now that we have the theoretical background, let's have a look at a real example and "refactor" the 
[php-fpm](/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#setup-php-fpm)
and 
[nginx](/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#setup-nginx)
containers from the first part of this tutorial series. This is the folder structure:

````
<project>/
├── .docker/
|   ├── .shared/
|   |   ├── config/
|   |   |   └── php/ 
|   |   |       └── zz-app.ini
|   |   └── scripts/
|   |       └── docker-entrypoint/
|   |           └── resolve-docker-host-ip.sh
|   ├── php-fpm/
|   |   ├── php-fpm.d/
|   |   |   └── pool.conf
|   |   └── Dockerfile
|   ├── nginx/
|   |   ├── sites-available/
|   |   |   └── app.conf
|   |   ├── Dockerfile
|   |   └── nginx.conf
|   ├── .env.example
|   └── docker-compose.yml
├── Makefile
└── index.php
````

### php-fpm

### nginx
  
## Setting up docker-compose 
In order to orchestrate the build process, we'll use docker-compose.

### docker-compose.yml

### .env.example

  - base setup in docker-compose.yml
  - .env files
  - override.yml
  - volumes
    - code, logging, cache, 
