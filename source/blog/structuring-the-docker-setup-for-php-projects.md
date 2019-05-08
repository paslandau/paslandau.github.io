---
extends: _layouts.post
section: content
title: "How to build a Docker development setup for PHP Projects [Tutorial Part 3]"
subheading: "... folder structure, Dockerfile templates and general fundamentals"
h1: "Structuring the Docker setup for PHP Projects"
description: "Dockerfiles, folder structures, etc. - In this article I'll got through the fundamentals for a PHP development environment on Docker."
author: "Pascal Landau"
published_at: "2019-05-08 09:00:00"
vgwort: "380e34fac15043f5b80fecf412d4d831"
category: "development"
slug: "structuring-the-docker-setup-for-php-projects"
---

In the third part of this tutorial series on developing PHP on Docker we'll lay the fundamentals to
build a complete development infrastructure and explain how to "structure" the Docker setup as part
of a PHP project. Structure as in 
- folder structure ("what to put where")
- Dockerfile templates
- solving common problems (file permissions, runtime configuration, ...)

We will also create a minimal container setup consisting of php-fpm, nginx and a workspace container that we 
refactor from the previous parts of this tutorial.

<iframe width="560" height="315" src="https://www.youtube.com/embed/YYI5mTjFDuA" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

## Previous parts of the Docker PHP Tutorial
- [Setting up PHP, PHP-FPM and NGINX for local development on Docker](/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/)
- [Setting up PhpStorm with Xdebug for local development on Docker](/blog/setup-phpstorm-with-xdebug-on-docker/) 

All code sample are publicly available in my 
[Docker PHP Tutorial repository on github](https://github.com/paslandau/docker-php-tutorial). 
The branch for this tutorial is 
[part_3_structuring-the-docker-setup-for-php-projects](https://github.com/paslandau/docker-php-tutorial/tree/part_3_structuring-the-docker-setup-for-php-projects).

If you want to follow along, please subscribe to the [RSS feed](/feed.xml) or [via email](#newsletter) 
to get automatic notifications when the next part comes out :)

## Table of contents
- <a href="#introduction">Introduction</a>
- <a href="#structuring-the-repository">Structuring the repository</a>
  - <a href="#the-docker-folder">The .docker folder</a>
  - <a href="#shared-folder">The `.shared` folder</a>
  - <a href="#">`docker-test.sh`</a>
  - <a href="#env-example-and-docker-compose-yml">`.env.example` and `docker-compose.yml`</a>
  - <a href="#the-makefile">The Makefile</a>
- <a href="#defining-services-php-fpm-nginx-and-workspace">Defining services: php-fpm, nginx and workspace</a>
  - <a href="#php-fpm">php-fpm</a>
     - <a href="#modifying-the-pool-configuration">Modifying the pool configuration</a>
     - <a href="#custom-entrypoint">Custom ENTRYPOINT</a>
  - <a href="#nginx">nginx</a>
  - <a href="#workspace-formerly-php-cli">workspace (formerly php-cli)</a>
- <a href="#setting-up-docker-compose">Setting up docker-compose</a> 
  - <a href="#docker-compose-yml">docker-compose.yml</a>
  - <a href="#env-example">.env.example</a>
  - <a href="#building-and-running-the-containers">Building and running the containers</a>
  - <a href="#testing-if-everything-works">Testing if everything works</a>
- <a href="#makefile-and-bashrc">Makefile and `.bashrc`</a>
  - <a href="#using-make-as-central-entry-point">Using `make` as central entry point</a>
  - <a href="#install-make-on-windows-mingw">Install make on Windows (MinGW)</a>
  - <a href="#easy-container-access-via-din-bashrc-helper">Easy container access via `din` .bashrc helper</a>
- <a href="#fundamentals-on-building-the-containers">Fundamentals on building the containers</a>
  - <a href="#understanding-build-context">Understanding build context</a>
  - <a href="#dockerfile-template">Dockerfile template</a>
  - <a href="#setting-the-timezone">Setting the timezone</a>
  - <a href="#synchronizing-file-and-folder-ownership-on-shared-volumes">Synchronizing file and folder ownership on shared volumes</a>
  - <a href="#modifying-configuration-files">Modifying configuration files</a>
     - <a href="#providing-additional-config-files">Providing additional config files</a>
     - <a href="#changing-non-static-values">Changing non-static values</a>
  - <a href="#installing-php-extensions">Installing php extensions</a>
  - <a href="#installing-common-software">Installing common software</a>
  - <a href="#cleaning-up">Cleaning up</a>
  - <a href="#using-entrypoint-for-pre-run-configuration">Using `ENTRYPOINT` for pre-run configuration</a>
     - <a href="#providing-host-docker-internal-for-linux-host-systems">Providing `host.docker.internal` for linux host systems</a>
- <a href="#wrapping-up">Wrapping up</a>
    
## <a id="introduction"></a>Introduction
When I started my current role as Head of Marketing Technology at ABOUT YOU back in 2016, we heavily
relied on [Vagrant (namely: Homestead) as our development infrastructure](/blog/phpstorm-with-vagrant-using-laravel-homestead-on-windows-10/). 
Though that was much better than 
working on our local machines, we've run into a couple of problems along the way (e.g. diverging software,
bloated images, slow starting times, complicated readme for onboarding, upgrading php, ...).

Today, everything that we need for the infrastructure is under source control and committed in the same repository
that we use for our main application. In effect we get **the same infrastructure for every developer** including automatic
updates "for free". It is extremely easy to tinker around with updates / new tools due to the ephemeral nature of docker
as tear down and rebuild only take one command and a couple of minutes.

To get a feeling for how the process _feels_ like, simply execute the following commands.

````
git clone https://github.com/paslandau/docker-php-tutorial.git
cd docker-php-tutorial
git checkout part_3_structuring-the-docker-setup-for-php-projects
make docker-clean
make docker-init
make docker-build-from-scratch
make docker-test
````

You should now have a running docker environment to develop PHP on docker (unless 
[something is blocking your port 80/443](#building-and-running-the-containers)
or
[you don't have make installed](#install-make-on-windows-mingw)
;))

## <a id="structuring-the-repository"></a>Structuring the repository
While playing around with docker I've tried different ways to "structure" files and folders and ended up with the following
concepts:
- everything related to docker is **placed in a `.docker` directory on on the same level as the main application**
- in this directory
  - each service gets its own subdirectory for configuration
  - is a **`.shared` folder containing scripts and configuration** required by multiple services
  - is an **`.env.example`** file containing variables for the **`docker-compose.yml`**
  - is a **`docker-test.sh`** file containing high level tests to validate the docker containers
- a **Makefile** with common instructions to control Docker is placed in the repository root

The result looks roughly like this:
````
<project>/
├── .docker/
|   ├── .shared/
|   |   ├── config/
|   |   └── scripts/
|   ├── php-fpm/
|   |   └── Dockerfile
|   ├── ... <additional services>/
|   ├── .env.example
|   ├── docker-compose.yml
|   └── docker-test.sh
├── Makefile
├── index.php
└──  ... <additional app files>/
````

### <a id="the-docker-folder"></a>The .docker folder
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
|   ├── config/
|   └── scripts/
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

### <a id="shared-folder"></a>The `.shared` folder
When dealing with multiple services, chances are high that some of those services will be configured similarly, e.g. for
- installing common software 
- setting up unix users (with the same ids)
- configuration (think php-cli for workers and php-fpm for web requests)

To avoid duplication, I place scripts (simple bash files) and config files in the `.shared` folder and make it available in
the build context for each service. I'll explain the process in more detail under 
[providing the correct build context](#understanding-build-context).

### <a id=""></a>`docker-test.sh`
Is really just a simple bash script that includes some high level tests to make sure that the containers are
built correctly. See section [Testing if everything works](#testing-if-everything-works).

### <a id="env-example-and-docker-compose-yml"></a>`.env.example` and `docker-compose.yml`
`docker-compose` uses a [`.env` file](https://docs.docker.com/compose/environment-variables/#the-env-file)
for a convenient way to define and 
[`substitute environment variables`](https://docs.docker.com/compose/compose-file/#variable-substitution). 
Since this `.env` file is environment specific, it is **NOT**
part of the repository (i.e. ignored via `.gitignore`). Instead, we provide a `.env.example` file that contains 
the required environment variables including reasonable default values. A new dev would usually run 
`cp .env.example .env` after checking out the repository for the first time. 
See section [.env.example](#env-example).

### <a id="the-makefile"></a>The Makefile
`make` and `Makefile`s are among those things that I've heard about occasionally but never really cared to 
understand (mostly because I associated them with C). Boy, did I miss out. I was comparing different strategies
to provide code quality tooling (style checkers, static analyzers, tests, ...) and went from custom bash scripts
over [composer scripts](https://getcomposer.org/doc/articles/scripts.md) to finally end up at `Makefile`s.

The `Makefile` serves as a central entry point and simplifies the management of the docker containers, e.g. for
(re-)building, starting, stopping, logging in, etc. See section [Makefile and .bashrc](#makefile-and-bashrc).

## <a id="defining-services-php-fpm-nginx-and-workspace"></a>Defining services: php-fpm, nginx and workspace
Let's have a look at a real example and "refactor" the 
[php-cli](#workspace-formerly-php-cli),
[php-fpm](#php-fpm)
and
[nginx](#nginx)
containers from the [first part of this tutorial series](/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/). 

This is the folder structure:

````
<project>/
├── .docker/
|   ├── .shared/
|   |   ├── config/
|   |   |   └── php/ 
|   |   |       └── conf.d/
|   |   |           └── zz-app.ini
|   |   └── scripts/
|   |       └── docker-entrypoint/
|   |           └── resolve-docker-host-ip.sh
|   ├── nginx/
|   |   ├── sites-available/
|   |   |   └── default.conf
|   |   ├── Dockerfile
|   |   └── nginx.conf
|   ├── php-fpm/
|   |   ├── php-fpm.d/
|   |   |   └── pool.conf
|   |   └── Dockerfile
|   ├── workspace/ (formerly php-cli)
|   |   ├── .ssh/
|   |   |   └── insecure_id_rsa
|   |   |   └── insecure_id_rsa.pub
|   |   └── Dockerfile
|   ├── .env.example
|   ├── docker-compose.yml
|   └── docker-test.sh
├── Makefile
└── index.php
````

### <a id="php-fpm"></a>php-fpm
Click here to [see the full php-fpm Dockerfile](https://github.com/paslandau/docker-php-tutorial/blob/part_3_structuring-the-docker-setup-for-php-projects/.docker/php-fpm/Dockerfile).

Since we will be having two PHP containers, we need to place the common .ini settings in the `.shared` directory.

````
|   ├── .shared/
|   |   ├── config/
|   |   |   └── php/ 
|   |   |       └── conf.d/
|   |   |           └── zz-app.ini
````

For now, `zz-app.ini` will only contain our 
[opcache setup](https://www.scalingphpbook.com/blog/2014/02/14/best-zend-opcache-settings.html):

````
; enable opcache
opcache.enable_cli = 1
opcache.enable = 1
opcache.fast_shutdown = 1
; revalidate everytime (effectively disabled for development)
opcache.validate_timestamps = 0
````

The pool configuration is only relevant for php-fpm, so it goes in the directory of the service. Btw. I highly 
recommend [this video on PHP-FPM Configuration](https://serversforhackers.com/c/lemp-php-fpm-config) if your 
php-fpm foo isn't already over 9000.

````
|   ├── php-fpm/
|   |   ├── php-fpm.d/
|   |   |   └── pool.conf
````

#### <a id="modifying-the-pool-configuration"></a>Modifying the pool configuration
We're using the [`modify_config.sh` script](#changing-non-static-values) to set the user and group that owns the php-fpm processes.

````
# php-fpm pool config
COPY ${SERVICE_DIR}/php-fpm.d/* /usr/local/etc/php-fpm.d
RUN /tmp/scripts/modify_config.sh /usr/local/etc/php-fpm.d/zz-default.conf \
    "__APP_USER" \
    "${APP_USER}" \
 && /tmp/scripts/modify_config.sh /usr/local/etc/php-fpm.d/zz-default.conf \
    "__APP_GROUP" \
    "${APP_GROUP}" \
;
````

#### <a id="custom-entrypoint"></a>Custom ENTRYPOINT
Since php-fpm needs to be debuggable, we need to ensure that the `host.docker.internal` DNS entry exists,
so we'll use the [corresponding ENTRYPOINT](#providing-host-docker-internal-for-linux-host-systems) to do that.

````
# entrypoint
RUN mkdir -p /bin/docker-entrypoint/ \
 && cp /tmp/scripts/docker-entrypoint/* /bin/docker-entrypoint/ \
 && chmod +x -R /bin/docker-entrypoint/ \
;

ENTRYPOINT ["/bin/docker-entrypoint/resolve-docker-host-ip.sh","php-fpm"]
````

### <a id="nginx"></a>nginx
Click here to [see the full nginx Dockerfile](https://github.com/paslandau/docker-php-tutorial/blob/part_3_structuring-the-docker-setup-for-php-projects/.docker/nginx/Dockerfile).

The nginx setup is even simpler. There is no shared config, so that everything we need resides in
````
|   ├── nginx/
|   |   ├── sites-available/
|   |   |   └── default.conf
|   |   ├── Dockerfile
|   |   └── nginx.conf
````

Please note, that nginx only has the `nginx.conf` file for configuration (i.e. there is no `conf.d` directory or so),
so we need to define the **full** config in there. 

````
user __APP_USER __APP_GROUP;
worker_processes 4;
pid /run/nginx.pid;
daemon off;

http {
  # ...
  
  include /etc/nginx/sites-available/*.conf;
  
  # ...
}
````

There are two things to note:

- user and group are modified dynamically
- we specify `/etc/nginx/sites-available/` as the directory that holds the config files for the individual files via
  `include /etc/nginx/sites-available/*.conf;`
  
We need to keep the last point in mind, because we must use the same directory in the Dockerfile:

````
# nginx app config
COPY ${SERVICE_DIR}/sites-available/* /etc/nginx/sites-available/
````

The site's config file 
[`default.conf`](https://github.com/paslandau/docker-php-tutorial/blob/part_3_structuring-the-docker-setup-for-php-projects/.docker/nginx/sites-available/default.conf) 
has a variable (`__NGINX_ROOT`) for the `root` directive and we "connect" it with the fpm-container via 
`fastcgi_pass php-fpm:9000;`

````
server {
    # ...
    root __NGINX_ROOT;
    # ...

    location ~ \.php$ {
        # ...
        fastcgi_pass php-fpm:9000;
    }
}
````

`php-fpm` will resolve to the `php-fpm` container, because we use php-fpm as the service name in the docker-compose
file, so it will be [automatically used as the hostname](https://docs.docker.com/compose/compose-file/#aliases):

>  Other containers on the same network can use either the service name or [an] alias to connect to one of the service’s containers.

In the Dockerfile, we use

````
ARG APP_CODE_PATH
RUN /tmp/scripts/modify_config.sh /etc/nginx/sites-available/default.conf \
    "__NGINX_ROOT" \
    "${APP_CODE_PATH}" \
;
````

`APP_CODE_PATH` will be passed via docker-compose when we build the container and mounted as a shared directory 
from the host system. 

### <a id="workspace-formerly-php-cli"></a>workspace (formerly php-cli)
Click here to [see the full workspace Dockerfile](https://github.com/paslandau/docker-php-tutorial/blob/part_3_structuring-the-docker-setup-for-php-projects/.docker/workspace/Dockerfile).

We will use the former `php-cli` container and make it our `workspace` as introduced in part 2 of this tutorial under
[Preparing the "workspace" container](https://www.pascallandau.com/blog/setup-phpstorm-with-xdebug-on-docker/#preparing-the-workspace-container).

This will be the container we use to point our IDE to, e.g. to execute tests. Its Dockerfile looks almost identical
to the one of the `php-fpm` service, apart from the SSH setup:

````
# set up ssh
RUN apt-get update -yqq && apt-get install -yqq openssh-server \
 && mkdir /var/run/sshd \
;

# add default public key to authorized_keys
USER ${APP_USER}
COPY ${SERVICE_DIR}/.ssh/insecure_id_rsa.pub /tmp/insecure_id_rsa.pub
RUN mkdir -p ~/.ssh \
 && cat /tmp/insecure_id_rsa.pub >> ~/.ssh/authorized_keys \
 && chown -R ${APP_USER}: ~/.ssh \
 && chmod 700 ~/.ssh \
 && chmod 600 ~/.ssh/authorized_keys \
;
USER root
````

## <a id="setting-up-docker-compose"></a>Setting up docker-compose 
In order to orchestrate the build process, we'll use docker-compose.

### <a id="docker-compose-yml"></a>docker-compose.yml
See the full 
[docker-compose.yml file in the repository](https://github.com/paslandau/docker-php-tutorial/blob/part_3_structuring-the-docker-setup-for-php-projects/.docker/docker-compose.yml)

Things to note:
- each service uses `context: .` so it has access to the `.shared` folder.
  The context is always [relative to the location of the first docker-compose.yml file](https://docs.docker.com/compose/extends/#understanding-multiple-compose-files)
- all arguments that we used in the Dockerfiles are defined in the `args:`
  section via
  ````
  args:
    - APP_CODE_PATH=${APP_CODE_PATH_CONTAINER}
    - APP_GROUP=${APP_GROUP}
    - APP_GROUP_ID=${APP_GROUP_ID}
    - APP_USER=${APP_USER}
    - APP_USER_ID=${APP_USER_ID}
    - TZ=${TIMEZONE}
  ````
- the codebase is synced from the host in all containers via
  ````
  volumes:
    - ${APP_CODE_PATH_HOST}:${APP_CODE_PATH_CONTAINER}
  ````
- the `nginx` service exposes ports on the host machine so that we can 
  access the containers from "outside" via
  ````
  ports:
    - "${NGINX_HOST_HTTP_PORT}:80"
    - "${NGINX_HOST_HTTPS_PORT}:443"
  ````
- all services are part of the `backend` network so they can talk to each
  other. The `nginx` service has an additional alias that allows us to
  define an arbitrary host name via
  ````
  networks:
    backend:
      aliases:
        - ${APP_HOST}
  ````
  I prefer to have a dedicated hostname per project (e.g. `docker-php-tutorial.local`)
  instead of using `127.0.0.1` or `localhost` directly

### <a id="env-example"></a>.env.example
To fill in all the required variables / arguments, we're using a `.env.example` file with the following content:

````
# Default settings for docker-compose
COMPOSE_PROJECT_NAME=docker-php-tutorial
COMPOSE_FILE=docker-compose.yml
COMPOSE_CONVERT_WINDOWS_PATHS=1

# build
PHP_VERSION=7.3
TIMEZONE=UTC
NETWORKS_DRIVER=bridge

# application
APP_USER=www-data
APP_GROUP=www-data
APP_USER_ID=1000
APP_GROUP_ID=1000
APP_CODE_PATH_HOST=../
APP_CODE_PATH_CONTAINER=/var/www/current

# required so we can reach the nginx server from other containers via that hostname
APP_HOST=docker-php-tutorial.local

# nginx
NGINX_HOST_HTTP_PORT=80
NGINX_HOST_HTTPS_PORT=443

# workspace
WORKSPACE_HOST_SSH_PORT=2222
````

The `COMPOSE_` variables in the beginning set some reasonable 
[defaults for docker-compose](https://docs.docker.com/compose/reference/envvars/#compose_file).

###<a id="building-and-running-the-containers"></a>Building and running the containers
By now, we should have everything we need set up to get our dockerized PHP development up and running. If
you haven't done it already, now would be a great time to clone 
[the repository](https://github.com/paslandau/docker-php-tutorial)
and checkout the 
[`part_3_structuring-the-docker-setup-for-php-projects` branch](https://github.com/paslandau/docker-php-tutorial/blob/part_3_structuring-the-docker-setup-for-php-projects):

````
git clone https://github.com/paslandau/docker-php-tutorial.git
cd docker-php-tutorial
git checkout part_3_structuring-the-docker-setup-for-php-projects
````

Now copy the `.env.exmaple` to `.env`. All the default values  should work out of the box - unless you already have something
running on port `80` or `443`. In that case you have to change `NGINX_HOST_HTTP_PORT / NGINX_HOST_HTTP_PORT` to a free port.
````
cp .env.example .env
````

We can examine the "final" docker-compose.yml **after** the variable substitution via
````
docker-compose -f .docker/docker-compose.yml --project-directory .docker config
````

````
networks:
  backend:
    driver: bridge
services:
  nginx:
    build:
      args:
        APP_CODE_PATH: /var/www/current
        APP_GROUP: www-data
        APP_GROUP_ID: '1000'
        APP_USER: www-data
        APP_USER_ID: '1000'
        TZ: UTC
      context: D:\codebase\docker-php-tutorial\.docker
      dockerfile: ./nginx/Dockerfile
    image: php-docker-tutorial/nginx
    networks:
      backend:
        aliases:
        - docker-php-tutorial.local
    ports:
    - published: 80
      target: 80
    - published: 443
      target: 443
    volumes:
    - /d/codebase/docker-php-tutorial:/var/www/current:rw
  php-fpm:
// ...
````
Note, that this command is run from `./docker-php-tutorial`. If we would run this from `./docker-php-tutorial/.docker`,
we could simply use `docker-compose config` - but since we'll define that in a Makefile later anyway, the additional
"verbosity" won't matter ;)

This command is also a great way to check the various paths that are resolved to their absolute form, e.g. 
````
context: D:\codebase\docker-php-tutorial\.docker
````
and
````
volumes:
- /d/codebase/docker-php-tutorial:/var/www/current:rw
````

The actual build is triggered via
````
docker-compose -f .docker/docker-compose.yml --project-directory .docker build --parallel
````
Since we have more than one container, it makes sense to build with 
[`--parallel`](https://docs.docker.com/compose/reference/build/).

To start the containers, we use
````
docker-compose -f .docker/docker-compose.yml --project-directory .docker up -d
````
and should see  
````
$ docker-compose -f .docker/docker-compose.yml --project-directory .docker up -d
Starting docker-php-tutorial_nginx_1     ... done
Starting docker-php-tutorial_workspace_1 ... done
Starting docker-php-tutorial_php-fpm_1   ... done
````

### <a id="testing-if-everything-works"></a>Testing if everything works
After rewriting our own docker setup a couple of times, I've come to appreciate a structured way to test if 
"everything" works. Everything as in:
- are all containers running?
- does "host.docker.internal" exist?
- do we see the correct output when sending a request to nginx/php-fpm?
- are all required php extensions installed?

This might seem superfluous (after all, we just defined excatly that in the Dockerfiles), but there will come a time
when you (or someone else) need to make changes (new PHP version, new extensions, etc.) and having something that runs
automatically and informs you about obvious flaws is a real time saver. 

You can see 
[the full test file in the repository](https://github.com/paslandau/docker-php-tutorial/blob/part_3_structuring-the-docker-setup-for-php-projects/.docker/docker-test.sh). 
Since my bash isn't the best, I try to keep it as simple as possible. The tests can be run via

````
sh .docker/docker-test.sh
````

and should yield something like this:
````
Testing service 'workspace'
=======
Checking if 'workspace' has a running container
OK
Testing PHP version '7.3' on 'workspace' for 'php' and expect to see 'PHP 7.3'
OK
Testing PHP module 'xdebug' on 'workspace' for 'php'
OK
Testing PHP module 'Zend OPcache' on 'workspace' for 'php'
OK
Checking 'host.docker.internal' on 'workspace'
OK

Testing service 'php-fpm'
=======
...
````

## <a id="makefile-and-bashrc"></a>Makefile and `.bashrc`
In the previous sections I have introduced a couple of commands, e.g. for building and running containers. And to be honest,
I find it kinda challenging to keep them in mind without having to look up the exact options and arguments. I would
usually create a helper function or an alias in my local `.bashrc` file in a situation like that - but that wouldn't
be available to other members of the team then and it would be very specific to this one project. 
Instead we'll provide a `Makefile` as a central reference point.

### <a id="using-make-as-central-entry-point"></a>Using `make` as central entry point
Please refer 
[to the repository for the full `Makefile`](https://github.com/paslandau/docker-php-tutorial/blob/part_3_structuring-the-docker-setup-for-php-projects/Makefile).

Going into the details of `make` is a little out of scope for this article, 
so I kindly refer to some articles that helped me get started:
- [Makefile for lazy developers](https://localheinz.com/blog/2018/01/24/makefile-for-lazy-developers/)
- [Why you Need a Makefile on your Project](https://blog.theodo.fr/2018/05/why-you-need-a-makefile-on-your-project/)

Both are written with a PHP context in mind. 
Tip: If you are using PhpStorm, give the [Makefile support plugin](https://plugins.jetbrains.com/plugin/9333-makefile-support)
a try. And don't forget the number one rule: 
[A `Makefile` requires tabs](https://stackoverflow.com/q/14109724)!

Note: If you are using Windows, `make` is probably not available. See 
[Install make on Windows (MinGW)](#install-make-on-windows-mingw) for instructions to set it up.

The `Makefile` ist located in the root of the application. Since we use a `help` target that makes the 
[`Makefile` self-documenting](https://suva.sh/posts/well-documented-makefiles/), we can simply run `make` to
see all the available commands:

````
$ make

Usage:
  make <target>

[Docker] Build / Infrastructure
  docker-clean                 Remove the .env file for docker
  docker-init                  Make sure the .env file exists for docker
  docker-build-from-scratch    Build all docker images from scratch, without cache etc. Build a specific image by providing the service name via: make docker-build CONTAINER=<service>
  docker-build                 Build all docker images. Build a specific image by providing the service name via: make docker-build CONTAINER=<service>
  docker-up                    Start all docker containers. To only start one container, use CONTAINER=<service>
  docker-down                  Stop all docker containers. To only stop one container, use CONTAINER=<service>
  docker-test                  Run the infrastructure tests for the docker setup
````

As a new developer, your "onboarding" to get a running infrastructure should now look like this:

````
make docker-clean
make docker-init
make docker-build-from-scratch
make docker-test
````

### <a id="install-make-on-windows-mingw"></a>Install make on Windows (MinGW)
`make` doesn't exist on Windows and is also not part of the standard installation of MinGW 
(click here [to learn how to setup MinGW](/blog/phpstorm-with-vagrant-using-laravel-homestead-on-windows-10/#git-and-git-bash))
Setting is up is straight forward but as with "everything UI" it's easier if you can 
actually "see what I'm doing" - so here's a video:

<iframe width="560" height="315" src="https://www.youtube.com/embed/taCJhnBXG_w" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

The steps are as follows:
- Set up `mingw-get`
  - Instructions: http://www.mingw.org/wiki/getting_started#toc5
  - Download: https://sourceforge.net/projects/mingw/files/Installer/mingw-get-setup.exe/download
  - Install and [add the `bin/` directory to `PATH` (shortcut `systempropertiesadvanced`)](/blog/php7-with-xdebug-2-4-for-phpstorm-on-windows-10/#the-path-variable).
    Notes: 
     - Do not use an installation path that contains spaces!
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

### <a id="easy-container-access-via-din-bashrc-helper"></a>Easy container access via `din` .bashrc helper
I've got one last goodie for working with Docker that I use all the time: 

Logging into a running container via [`docker exec`](https://docs.docker.com/engine/reference/commandline/exec/#run-docker-exec-on-a-running-container)
and the `din / dshell` helper.

[![Log into any running docker container via din helper](/img/structuring-the-docker-setup-for-php-projects/easy-docker-login-din.gif)](/img/structuring-the-docker-setup-for-php-projects/easy-docker-login-din.gif.png)

To make this work, put the following code in your `.bashrc` file
````
function din() {
  filter=$1
  
  user=""
  if [[ -n "$2" ]];
  then
  	user="--user $2"
  fi
  
  shell="bash"
  if [[ -n "$3" ]];
  then
  	shell=$3
  fi
  
  prefix=""
  if [[ "$(expr substr $(uname -s) 1 5)" == "MINGW" ]]; then
  	prefix="winpty"
  fi
  ${prefix} docker exec -it ${user} $(docker ps --filter name=${filter} -q | head -1) ${shell}
}
````

The ` $(docker ps --filter name=${filter} -q | head -1)` part will find partial matches on running containers 
for the first argument and pass the result to the `docker exec` command. In effect, we can log into any container 
by only providing a minimal matching string on the container name. E.g. to log in the `workspace` container
I can now simply type `din works` from _anywhere_ on my system.

## <a id="fundamentals-on-building-the-containers"></a>Fundamentals on building the containers
Since we have now "seen" the end result, let's take a closer look behind the scenes. 
I assume that you are already somewhat familiar with `Dockerfile`s and have used `docker-compose` to orchestrate multiple 
services (if not, check out 
[Persisting image changes with a Dockerfile](blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#dockerfile) and
[Putting it all together: Meet docker-compose](blog/php-php-fpm-and-nginx-on-docker-in-windows-10/#docker-compose)). But
there are some points I would like to cover in a little more detail.

### <a id="understanding-build-context"></a>Understanding build context
There are two essential parts when building a container:

- the Dockerfile
- the build context

You can read about the official description in the [Dockerfile reference](http://docs.docker.com/engine/reference/builder/#usage).
You'll usually see something like this:

````
docker build .
````
which assumes that you use the current directory as build context and that there is a Dockerfile in the same directory.

But you can also start the build via

````
docker build .docker -f .docker/nginx/Dockerfile
                 |      |
                 |      └── use the Dockerfile at ".docker/nginx/Dockerfile"
                 |
                 └── use the .docker subdirectory as build context
````

For me, the gist is this: The build context defines the files and folders (recursively) on your machine that are send 
from the [Docker CLI](https://docs.docker.com/engine/reference/commandline/cli/) to 
the [Docker Daemon](https://docs.docker.com/engine/reference/commandline/dockerd/) that executes the build process
of a container so that you can reference those files in the Dockerfile (e.g. via `COPY`). Take the following structure for example:

````
<project>/
├── .docker/
    ├── .shared/
    |   └── scripts/
    |       └── ...
    └── nginx/
        ├── nginx.conf
        └── Dockerfile
````

Assume, that the current working directory is `<project>/`. If we started a build via
````
docker build .docker/nginx -f .docker/nginx/Dockerfile
````
the context would **not** include the `.shared` folder so we wouldn't be able to `COPY` the `scripts/` subfolder. 
If we ran

````
docker build .docker -f .docker/nginx/Dockerfile
````
however, that would make the `.shared` folder available. In the Dockerfile itself, I need to know what the build context
is, because I need to adjust the paths accordingly. Concrete example for the folder structure above and build 
triggered via `docker build .docker -f .docker/nginx/Dockerfile`:

````
FROM:nginx

# build context is .docker ...

# ... so the following COPY refers to .docker/.shared
COPY ./.shared /tmp

# ... so the following COPY refers to .docker/nginx/nginx.conf
COPY ./nginx/nginx.conf /tmp
````


The build context for all of our containers will be the `.docker` directory, 
so that all build processes have access to the `.shared` scripts and config. 
Yes, that also means that the `php-fpm` container has access to files that are only relevant to the `mysql` container (for
instance), but the performance penalty is absolutely neglectable. Plus, as long as we don't actively `COPY` those irrelevant
files, they won't bloat up our images.

A couple of notes:
- I used to think that the build context is *always* tied to the location of the Dockerfile but that's only the default,
  it can be any directory
- the build context is **actually send** to the build process - i.e. you should avoid unnecessary files / folders as this might
  affect performance, especially on big files (iaw: don't use `/` as context!)
- similar to `git`, Docker knows the concept of a [`.dockerignore` file](https://docs.docker.com/engine/reference/builder/#dockerignore-file)
  to exclude files from being included in the build context
  
### <a id="dockerfile-template"></a>Dockerfile template
The Dockerfiles for the containers roughly follow the structure outlined below:

````
FROM ...

# path to the directory where the Dockerfile lives relative to the build context
ARG SERVICE_DIR="./service"

# get the scripts from the build context and make sure they are executable
COPY .shared/scripts/ /tmp/scripts/
RUN chmod +x -R /tmp/scripts/

# set timezone
ARG TZ=UTC
RUN /tmp/scripts/set_timezone.sh ${TZ}

# add users
ARG APP_USER=www-data
ARG APP_USER_ID=1000
ARG APP_GROUP=$(APP_USER)
ARG APP_GROUP_ID=$(APP_USER_ID)

RUN /tmp/scripts/create_user.sh ${APP_USER} ${APP_GROUP} ${APP_USER_ID} ${APP_GROUP_ID}

# install common software
RUN /tmp/scripts/install_software.sh

# perform any other, container specific build steps
COPY ${SERVICE_DIR}/config/* /etc/service/config
RUN /tmp/scripts/modify_config.sh /etc/service/config/default.conf \
    "__APP_USER" \
    "${APP_USER}" \
;
# [...]

# set default work directory
WORKDIR "..."

# cleanup 
RUN /tmp/scripts/cleanup.sh

# define ENTRYPOINT
ENTRYPOINT [...]
CMD [...]
````

The comments should suffice to give you an overview - so let's talk about the individual parts in detail.

### <a id="setting-the-timezone"></a>Setting the timezone
**Script: `set_timezone.sh`**

Let's start with a simple and obvious one: Ensuring that all containers use the same system timezone
(see [here](https://www.itzgeek.com/how-tos/linux/debian/how-to-change-timezone-in-debian-9-8-ubuntu-16-04-14-04-linuxmint-18.html)
and [here](https://unix.stackexchange.com/q/452559))

````
#!/bin/sh

TZ=$1
ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
````

The script is then called from the `Dockerfile` via
````
ARG TZ=UTC
RUN /tmp/scripts/set_timezone.sh ${TZ}
````


### <a id="synchronizing-file-and-folder-ownership-on-shared-volumes"></a>Synchronizing file and folder ownership on shared volumes
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

[![File ownership with multiple containers using a shared volume](/img/structuring-the-docker-setup-for-php-projects/docker-file-ownership-volume.png)](/img/structuring-the-docker-setup-for-php-projects/docker-file-ownership-volume.png)

That's inconvenient but rather easy to solve in most cases, because we have full control over the containers and
can [assign ids as we like](https://www.cyberciti.biz/faq/linux-change-user-group-uid-gid-for-all-owned-files/) 
(using `usermod -u <id> <name>`) and thus making sure every container uses the same user names with the same user ids.

Things get complicated when the volume isn't just a Docker volume but a shared folder on the host. This is usually
what we want for development, so that changes on the host are immediately reflected in all the containers.

[![File ownership with multiple containers using a shared volume from the host](/img/structuring-the-docker-setup-for-php-projects/docker-file-ownership-host.png)](/img/structuring-the-docker-setup-for-php-projects/docker-file-ownership-host.png)

This issue **only affects users with a linux host system**! Docker Desktop (previously known as Docker for Mac / Docker for Win)
has a virtualization layer in between that will effectively erase all ownership settings and make everything shared
from the host available to every user in a container.

We use the following script to ensure a consistent user setup when building a container:

````
#!/bin/sh

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

Linux users should use the user id of the user on their host system - for Docker Desktop users the defaults are fine.

### <a id="modifying-configuration-files"></a>Modifying configuration files
For most services we probably need some custom configuration settings, like
- setting php.ini values
- changing the default user of a service 
- changing the location of logfiles

There are a couple of  
[common approaches to modify application configuration in docker](https://dantehranian.wordpress.com/2015/03/25/how-should-i-get-application-configuration-into-my-docker-containers/)
and we are currently trying to stick to two rules:
1. provide additional files that override defaults if possible
2. change non-static values with a simple search and replace via `sed` during the container build

#### <a id="providing-additional-config-files"></a>Providing additional config files
Most services allow the specification of additional configuration files that override the default values in 
a default config file. This is great because we only need to define the settings that we actually care about 
instead of copying a full file with lots of redundant values.

Take the [`php.ini` file](http://php.net/manual/en/configuration.file.php#configuration.file.scan) for example:
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

#### <a id="changing-non-static-values"></a>Changing non-static values
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
#!/bin/sh

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

where `PHP_FPM_MEMORY_LIMIT` has a default value of `1024M` but can be overriden when the actual build is initiated.


### <a id="installing-php-extensions"></a>Installing php extensions
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
#!/bin/sh

# add wget
apt-get update -yqq && apt-get -f install -yyq wget

# download helper script
wget -q -O /usr/local/bin/install-php-extensions https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions \
    || (echo "Failed while downloading php extension installer!"; exit 1)

# install all required extensions
chmod uga+x /usr/local/bin/install-php-extensions && sync && install-php-extensions \
    xdebug \
    opcache \
;
````

If you're not sure which extensions are required by your application, give the 
[ComposerRequireChecker](https://github.com/maglnet/ComposerRequireChecker) a try.

### <a id="installing-common-software"></a>Installing common software
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
    procps \
    strace \
    sudo \
    sysstat \
    unzip \
    vim \
    wget \
;
````

Notes:
- this list should match **your own set of go-to tools**. I'm fairly open to adding new stuff here if it speeds up the
  dev workflow. But if you don't require some of the tools, get rid of them.
- sorting the software alphabetically is a good practice to avoid unnecessary duplicates. Don't do this by hand, though!
  If you're using an IDE / established text editor, chances are high that this is either a build-in functionality or
  there's a plugin available. I'm using [Lines Sorter for PhpStorm](https://plugins.jetbrains.com/plugin/5919-lines-sorter)

### <a id="cleaning-up"></a>Cleaning up
**Script: `cleanup.sh`**

Nice and simple:

````
#!/bin/sh

apt-get clean
rm -rf /var/lib/apt/lists/* \
       /tmp/* \
       /var/tmp/* \
       /var/log/lastlog \
       /var/log/faillog
````

### <a id="using-entrypoint-for-pre-run-configuration"></a>Using `ENTRYPOINT` for pre-run configuration
Docker went back to the unix roots with the 
[do on thing and do it well philosophy](https://en.wikipedia.org/wiki/Unix_philosophy#Do_One_Thing_and_Do_It_Well) which is 
manifested in the [`CMD` and `ENTRYPOINT` instructions](https://medium.freecodecamp.org/docker-entrypoint-cmd-dockerfile-best-practices-abc591c30e21).

As I had a hard time understanding those instructions when I started with Docker, here's my take at a layman's terms description:
- since a container should do one thing, we need to specify that thing. That's what we do with `ENTRYPOINT`. Concrete examples:
  - a `mysql` container should probably run the `mysqld` daemon
  - a `php-fpm` container.. well, `php-fpm`
- the `CMD` is passed as the default argument to the `ENTRYPOINT`
- the `ENTRYPOINT` is executed every time we *run* a container. Some things can't be done during build but only at runtime
  (e.g. find the IP of the host from within a container - see section 
  [Providing `host.docker.internal` for linux host systems](providing-host-docker-internal-for-linux-host-systems)
  ) - `ENTRYPOINT` is a good solution for that problem
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
#!/bin/sh
echo 'hello'

exec "$@"
````
Full example (using [stdin to pass the Dockerfile](https://docs.docker.com/engine/reference/commandline/build/#build-with--) 
via [Heredoc string](https://stackoverflow.com/q/2953081/413531))
````
$ docker build -t my-fpm -<<'EOF'
FROM php:fpm

RUN  touch "/usr/bin/my-entrypoint.sh" \
  && echo "#!/bin/sh" >> "/usr/bin/my-entrypoint.sh" \
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

#### <a id="providing-host-docker-internal-for-linux-host-systems"></a>Providing `host.docker.internal` for linux host systems
**Script: `docker-entrypoint/resolve-docker-host-ip.sh`**

In the previous part of this tutorial series, I explained how to build the 
[Docker container in a way that it plays nice with PhpStorm and Xdebug](/blog/setup-phpstorm-with-xdebug-on-docker). 
The key parts were SSH access and the magical `host.docker.internal` DNS entry. This works great for Docker Desktop (Windows and Mac)
but not for Linux. The DNS entry [doesn't exist there](https://github.com/docker/for-linux/issues/264). 
Since we rely on that entry 
[to make debugging possible](/blog/setup-phpstorm-with-xdebug-on-docker/#fix-xdebug-on-phpstorm-when-run-from-a-docker-container),
we will set it "manually" [if the host doesn't exist](https://stackoverflow.com/a/24049165/413531) 
with the following script 
(inspired by the article [Access host from a docker container](https://dev.to/bufferings/access-host-from-a-docker-container-4099)):

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
COPY .shared/scripts/ /tmp/scripts/

RUN mkdir -p /bin/docker-entrypoint/ \
 && cp /tmp/scripts/docker-entrypoint/* /bin/docker-entrypoint/ \
 && chmod +x -R /bin/docker-entrypoint/ \
;

ENTRYPOINT ["/bin/docker-entrypoint/resolve-docker-host-ip.sh", ...]
````

Notes:
- since this script depends on runtime configuration, we need to run it as an `ENTRYPOINT`
- there is no need to explicitly check for the OS type - we simply make sure that the DNS entry exists
  and add it if it doesn't
- we're using `dig` (package `dnsutils`) and `ip` (package `iproute2`) which need to be installed 
  during the build time of the container. Tip: If you need to figure out the package for a specific command,
  give [https://command-not-found.com/](https://command-not-found.com/) a try. See the 
  [entry for `dig`](https://command-not-found.com/dig) for instance.
- this workaround is only required in containers we want to debug via xdebug

## <a id="wrapping-up"></a>Wrapping up
Congratulation, you made it! If some things are not completely clear by now, don't hesitate to leave a comment.
Apart from that, you should now have a running docker setup for your local PHP development as well as a nice "flow"
to get started each day.

In the next part of this tutorial, we will add some more containers (php workers, mysql, redis, blackfire) and use
a fresh installation of Laravel to make use of them.

Please subscribe to the [RSS feed](/feed.xml) or [via email](#newsletter) to get automatic notifications when this next part comes out :)

