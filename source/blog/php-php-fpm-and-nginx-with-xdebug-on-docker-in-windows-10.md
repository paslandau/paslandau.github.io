---
extends: _layouts.post
section: content
title: "How to setup PHP, PHP-FPM and NGINX with Xdebug on Docker in Windows 10 [Tutorial Part 1]"
subheading: "A primer on PHP on Docker in Windows."
h1: "Setting up PHP for local development on Docker"
description: "Step-by-Step tutorial for setting up PHP Docker containers (cli and fpm)  for local development."
author: "Pascal Landau"
published_at: "2018-05-30 16:00:00"
vgwort: ""
category: "development"
slug: "php-fpm-nginx-xdebug-docker-windows-10"
---

, the second
at 
## Table of contents
<ul>
<li><a href="#setting-up-laravel">Setting up Laravel</a><ul>
<li> <a href="#install-laravel">Install laravel/laravel</a></li>
<li> <a href="#install-homestead">Install laravel/homestead</a></li>
<li> <a href="#convenience-commands">Convenience commands</a></li>
</ul></li>
<li> <a href="#configure-php-storm">Configure PhpStorm</a><ul>
<li> <a href="#setup-phpunit">Setup PHPUnit</a></li>
<li> <a href="#laravel-specific-settings-in-phpstorm">Laravel-specific settings in PhpStorm</a></li>
</ul></li>
<li> <a href="#housekeeping">Housekeeping</a><ul>
<li> <a href="#update-the-gitignore-file">Update the .gitignore file</a></li>
<li> <a href="#update-the-readme-md-file">Update the readme.md file</a></li>
</ul></li>
</ul>

## How to set up Docker for local Development in PHPStorm on Windows 10
So you probably heard from the new kid around the block called Docker and that every developer is supposed to do it now, right? 
You are a PHP developer and would like to get into Docker, but you didn't have the time to look into it, yet? 
Then this tutorial is for you! By the end of it, you should know:
- how to setup Docker "natively" on a Windows 10 machine
- how to build and run containers from the command line
- how to log into containers and explore them for information
- what a Dockerfile is and how to use it
- how containers can talk to each other
- how docker-compose can be used to fit everything nicely together

### Preconditions
I'm assuming that you have installed [Git bash Windows](https://git-scm.com/download/win). If not, please do that before, 
see [Setting up the software: Git and Git Bash](http://www.pascallandau.com/blog/phpstorm-with-vagrant-using-laravel-homestead-on-windows-10/#git-and-git-bash).

### Why use Docker?
I won't go into too much detail what Docker is and why should use it, because others have already 
talked about this extensively (links…). [TODO]

As for me, my main reasons were
- Symlinks in vagrant didn't work the way they should
- VMs become bloated and hard to manage over time
- Setup in the team involved a lot of work
- I wanted to learn Docker for quite some time because you hear a lot about it

In general, Docker is kind of like a virtual machine, so it allows us to develop in an OS of our choice (e.g. Windows) 
but run the code in the same environment as it will in production (e.g. on a linux server). Thanks to its core principles, 
it makes the separation of services really easy (e.g. having a dedicated server for your database) which - again - 
is something that should happen on production anyway.

### Transition from Vagrant
On Windows, you can either use the [Docker Toolbox](https://docs.docker.com/toolbox/toolbox_install_windows/) 
(which is essentially a VM with Docker setup on it) or the Hyper-V based [Docker for Windows](https://www.docker.com/docker-windows). 
This tutorial will only look at the latter.

*A word of caution*: Unfortunately, we cannot have other Gods besides Docker (on Windows). 
The native Docker client requires Hyper-V to be activated which in turn will cause Virtualbox to not work any longer. 
Thus, we will not be able to use Vagrant and Docker alongside each other. 
This was actually the main reason it took me so long to start working with Docker.

## Setup Docker
First, [download Docker for Windows](https://store.docker.com/editions/community/docker-ce-desktop-windows)
(requires Microsoft Windows 10 Professional or Enterprise 64-bit). During the installation, 
leave the option "Use Windows Containers instead of Linux containers" unticked as we intend to develop on linux containers 
(you can change it later anyway).

[![Install docker](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/use-linux-containers.PNG)](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/use-linux-containers.PNG)

After the installation finishes, we need to log out of Windows and in again. If Hyper-V is not activated yet, 
Docker will automatically urge you to do so now.

[![Activate Hype-V](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/enable-hyper-v-and-containers.PNG)](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/enable-hyper-v-and-containers.PNG)

If you agree, Hyper-V and container features are activated and a reboot is initiated. 
Caution: VirtualBox will stop working afterwards! Starting one of my previous machines from the VirtualBox interface 
or via `vagrant up` fails with the error message

> VT-x is not available (VERR_VMX_NO_VMX)

[![Virtual box error](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/virtual-box-error.PNG)](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/virtual-box-error.PNG)
[![Vagrant error](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/vagrant-error.PNG)](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/vagrant-error.PNG)

After rebooting, Docker will start automatically and a welcome screen appears. We can ignore that (close the window). 
In addition, a new icon is added to your system tray. A right-click reveals the context menu. 
[![Docker settings in system tray](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/system-tray-icon.PNG)](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/system-tray-icon.PNG)

Open the tab "Shared Devices" and tick the hard drives on your host machine that you want to share with Docker containers. 

_Note: We will still need to define explicit path mappings for the actual containers later on, but the hard drive that the path belongs to must be made available here.
After clicking "Apply", you will be prompted for your credentials_

[![Docker settings: Shared devices](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/settings-shared-drives.PNG)](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/settings-shared-drives.PNG)
[![Docker settings: Credential prompt](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/settings-shared-drives-credentials.PNG)](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/settings-shared-drives-credentials.PNG)

Next, open tab "Advanced". You don't actually have to change any of the settings but if you (like me) 
don't have `C:/` set up as you biggest partition, you might want to change the "Disk image location". 
Docker "physically" stores the container images in that location.

[![Docker settings: Advanced](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/settings-advanced-disk-image-location.PNG)](/img/php-php-fpm-and-nginx-with-xdebug-on-docker-in-windows-10/install-docker/settings-advanced-disk-image-location.PNG)

Congratulations, Docker is now set up on your machine 😊

## Setting up the PHP cli container 
Now set we have the general stuff out of the way, let's set up our first container. 
I've created the directory `D:/codebase/docker-php/` and will the remaining examples run in there. 

Firstly, lets create a directory for our sourcecode:
```
mkdir -p "D:/codebase/docker-php/app"
```

For the sake of simplicity, we will stick to the [official PHP image](https://hub.docker.com/_/php/) and run:

```
docker run -d --name docker-php -v "D:/codebase/docker-php/app":/var/www php:7.0-cli
```

Which means:

```
docker run                               // run a container
-d                                       // in the background (detached)
--name docker-php                        // named docker-php
-v "D:/codebase/docker-php/app":/var/www // sync the directory D:/codebase/docker-php/app on the 
                                         // windows host with /var/www in the container
php:7.0-cli                              // use this image to build the container
```

The result looks something like this:
```
$ docker run -d --name docker-php -v "D:/codebase/docker-php/app":/var/www php:7.0-cli
Unable to find image 'php:7.0-cli' locally
7.0-cli: Pulling from library/php
f2aa67a397c4: Pulling fs layer
c533bdb78a46: Pulling fs layer
65a7293804ac: Pulling fs layer
35a9c1f94aea: Pulling fs layer
54cffc62e1c2: Pulling fs layer
153ff2f4c2af: Pulling fs layer
96d392f71f56: Pulling fs layer
e8c43e665458: Pulling fs layer
35a9c1f94aea: Waiting
54cffc62e1c2: Waiting
153ff2f4c2af: Waiting
96d392f71f56: Waiting
e8c43e665458: Waiting
c533bdb78a46: Verifying Checksum
c533bdb78a46: Download complete
35a9c1f94aea: Verifying Checksum
35a9c1f94aea: Download complete
f2aa67a397c4: Verifying Checksum
f2aa67a397c4: Download complete
153ff2f4c2af: Verifying Checksum
153ff2f4c2af: Download complete
54cffc62e1c2: Verifying Checksum
54cffc62e1c2: Download complete
e8c43e665458: Verifying Checksum
e8c43e665458: Download complete
96d392f71f56: Verifying Checksum
96d392f71f56: Download complete
f2aa67a397c4: Pull complete
65a7293804ac: Verifying Checksum
65a7293804ac: Download complete
c533bdb78a46: Pull complete
65a7293804ac: Pull complete
35a9c1f94aea: Pull complete
54cffc62e1c2: Pull complete
153ff2f4c2af: Pull complete
96d392f71f56: Pull complete
e8c43e665458: Pull complete
Digest: sha256:ff6c5e695a931f18a5b59c82b1045edea42203a299e89a554ebcd723df8b9014
Status: Downloaded newer image for php:7.0-cli
56af890e1a61f8ffa5528b040756dc62a94c0b929c29df82b9bf5dec6255321f
```

Since we don't have the image on our machine (see `Unable to find image 'php:7.0-cli' locally`), 
Docker attempts to pull it from the official registry at https://hub.docker.com/. 
We've specifically chosen the "7.0-cli" version of the PHP image (which means: PHP 7.0 CLI only). 
See https://hub.docker.com/_/php/ for a list of all available tags/images.

Now let's see if the container is actually running via `docker ps`

````
$ docker ps
CONTAINER ID    	IMAGE           	COMMAND         	CREATED         	STATUS          	PORTS           	NAMES
````

Weird. For some reason, we don't see our newly created container there. Let's check with the `-a` flag to list *all* containers, 
even the ones that are not running.

````
$ docker ps -a
CONTAINER ID    	IMAGE           	COMMAND              	CREATED         	STATUS                  	PORTS           	NAMES
56af890e1a61    	php:7.0-cli     	"docker-php-entrypoi…"   27 seconds ago  	Exited (0) 25 seconds ago                   	docker-php
````

Aha. So the container was created, but immediately stopped (see `Created 27 seconds ago; Exited (0) 25 seconds ago`). 
That's because a container only [lives as long as it's main process is running](https://stackoverflow.com/a/28214133/413531).
According to the docs, 
> A container's main running process is the ENTRYPOINT and/or CMD at the end of the Dockerfile." 

[This answer explains the difference between CMD and ENTRYPOINT](https://stackoverflow.com/a/21564990/413531) quite well. 
Since we don't have a Dockerfile defined, we would need to look at the 
[Dockerfile of the base image](https://github.com/docker-library/php/blob/27c65bbd606d1745765b89bf43f39b06efad1e43/7.0/stretch/cli/Dockerfile) 
we're using, but I actually don't wanna go down this rabbit hole for now. To keep the container alive, 
we need to add the `-i` flag to the `docker run` command:

````
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
docker run -di --name docker-php -v "D:/codebase/docker-php/app":/var/www php:7.0-cli
7b3024a542a2d25fd36cef96f4ea689ec7ebb758818758300097a7be3ad2c2f6

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker ps
CONTAINER ID    	IMAGE           	COMMAND              	CREATED         	STATUS          	PORTS           	NAMES
7b3024a542a2    	php:7.0-cli     	"docker-php-entrypoi…"   5 seconds ago   	Up 4 seconds                        	docker-php
````

Sweet, so now that the container is up and running, let's "log in" via `docker exec -it docker-php bash`

````
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker exec -it docker-php bash
the input device is not a TTY.  If you are using mintty, try prefixing the command with 'winpty'
````

You might get the error message above. If so, prefixing the command with `winpty` should help:

````
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ winpty docker exec -it docker-php bash
root@7b3024a542a2:/#
````

A quick `php -v` within the container verifies, that we can actually run php scripts in there:

````
root@7b3024a542a2:/# php -v
PHP 7.0.30 (cli) (built: May 23 2018 23:04:32) ( NTS )
Copyright (c) 1997-2017 The PHP Group
Zend Engine v3.0.0, Copyright (c) 1998-2017 Zend Technologies
````

Remember the path mapping, that we specified? Let's create a simple "hello world" script *on the windows 10 host machine* to make sure it works:

````
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ echo '<?php echo "Hello World (php)\n"; ?>' > app/hello-world.php
````

Should look like this on the host machine:

````
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ ls -alh app
total 1,0K
drwxr-xr-x 1 Pascal 197121  0 Mai 28 12:29 ./
drwxr-xr-x 1 Pascal 197121  0 Mai 28 11:46 ../
-rw-r--r-- 1 Pascal 197121 49 Mai 28 12:30 hello-world.php
````

And like this from within the container:

````
root@7b3024a542a2:/# ls -alh /var/www
total 4.5K
drwxr-xr-x 2 root root	0 May 28 10:29 .
drwxr-xr-x 1 root root 4.0K May 28 10:00 ..
-rwxr-xr-x 1 root root   31 May 28 10:31 hello-world.php
````

Let's run the script via `php /var/www/hello-world.php`

````
root@7b3024a542a2:/# php /var/www/hello-world.php
Hello World
````

Purrfect. We created the file on our host system and it's automatically available in the container. 

### Installing Xdebug in the PHP container
Since we intend to use Docker for our local development setup, the ability to debug is mandatory. So let's extend our image with the xdebug extension.
The readme of the official Docker PHP repository does a good job at explaining how to install extensions [ https://github.com/docker-library/docs/blob/master/php/README.md#how-to-install-more-php-extensions ]. For xdebug, we're using PECL. To install the extension, make sure logged into the container and run 
pecl install xdebug-2.6.0
You should see an ouput like this:
root@7b3024a542a2:/# pecl install xdebug-2.6.0
[…]
Build process completed successfully
Installing '/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so'
install ok: channel://pecl.php.net/xdebug-2.6.0
configuration option "php_ini" is not set to php.ini location
You should add "zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so" to php.ini

The xdebug extension has been build and saved in /usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so. To actually activate it, run 

docker-php-ext-enable xdebug

That helper command will place the file docker-php-ext-xdebug.ini in the directory for additional php ini files with the content
zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so
which enables the extension.

Btw you can locate additional php ini files folder by running 

php -i | grep "additional .ini"

Result:
root@7b3024a542a2:/# php -i | grep "additional .ini"
Scan this dir for additional .ini files => /usr/local/etc/php/conf.d

When we check the contents of that folder, we will indeed find the xdebug.ini file with the before mentioned content and php -m reveals, that xdebug is active

root@7b3024a542a2:/# ls -alh /usr/local/etc/php/conf.d
total 12K
drwxr-sr-x 1 root staff 4.0K May 28 13:30 .
drwxr-sr-x 1 root staff 4.0K Apr 30 20:34 ..
-rw-r--r-- 1 root staff   81 May 28 13:30 docker-php-ext-xdebug.ini
root@7b3024a542a2:/# cat /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so
root@7b3024a542a2:/# php -m | grep xdebug
xdebug

Now we'll log out of the container (type "exit" or hit CTRL+D) and stop the container via
$ docker stop docker-php

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker stop docker-php
docker-php

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker ps -a
CONTAINER ID    	IMAGE           	COMMAND              	CREATED         	STATUS                   	PORTS           	NAMES
7b3024a542a2    	php:7.0-cli     	"docker-php-entrypoi…"   2 hours ago     	Exited (137) 7 seconds ago                   	docker-php

Now we start the container again via (docker start docker-php), log back in and check if xdebug is still there

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker start docker-php
docker-php

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ winpty docker exec -it docker-php bash
root@7b3024a542a2:/# php -m | grep xdebug
xdebug

And… it is! So the changes we made "survived" a restart of the container. Alas they won't survive a "rebuild" of the container via docker rm -f docker-php:

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker rm -f docker-php
docker-php

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker run -di --name docker-php -v "D:/codebase/docker-php/":/codebase php:7.0-cli
1da17524418f5327760eb113904b7ceec30f22b41e4b4bd77f9fa2f7b92b4ead

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ winpty docker exec -it docker-php bash
root@1da17524418f:/# php -m | grep xdebug
root@1da17524418f:/#

Note the new container ID (before: 7b3024a542a2 ; now: 1da17524418f). How can we make that work? Well, Dockerfile to the rescue. https://docs.docker.com/engine/reference/builder/
Persisting image changes with a Dockerfile
Simply put, a Dockerfile describes the changes we make to a base image, so we (and everybody else) can easily recreate the same environment. In our case, we need to define the PHP base image that we used as well as instructions for installing and enabling xdebug.
To clearly separate infrastructure from code, we'll create a new directory at D:/codebase/docker-php/php-cli/. Create a file named Dockerfile in  this directory and give it the following content:
FROM php:7.0-cli
RUN pecl install xdebug-2.6.0 \
	&& docker-php-ext-enable xdebug

Now cd in to the php-cli directory and run 

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-cli
docker build -t docker-php-image -f Dockerfile .
 to create an image based on that Dockerfile (the -f Dockerfile is actually optional as this is the default anyway). "docker-php-image" is the name of our new image. Output:
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-cli
$ docker build -t docker-php-image -f Dockerfile .
Sending build context to Docker daemon   5.12kB
Step 1/2 : FROM php:7.0-cli
 ---> da771ba4e565
Step 2/2 : RUN pecl install xdebug-2.6.0 	&& docker-php-ext-enable xdebug
 ---> Running in ff16ef56e648
downloading xdebug-2.6.0.tgz ...
Starting to download xdebug-2.6.0.tgz (283,644 bytes)
[...]
You should add "zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so" to php.ini
Removing intermediate container ff16ef56e648
 ---> 12be27256b12
Successfully built 12be27256b12
Successfully tagged docker-php-image:latest
SECURITY WARNING: You are building a Docker image from Windows against a non-Windows Docker host. All files and directories added to build context will have '-rwxr-xr-x' permissions. It is recommended to double check and reset permissions for sensitive files and directories.
Note, that it takes longer than before, because Docker now needs to do the extra work of installing xdebug. Instead of using the base php:7.0-cli image, we'll now use our new, shiny docker-php-image image to start the container and check for xdebug.
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-cli
$ docker run -di --name docker-php -v "D:/codebase/docker-php/app":/var/www docker-php-image
C:\Program Files\Docker\Docker\Resources\bin\docker.exe: Error response from daemon: Conflict. The container name "/docker-php" is already in use by container "1da17524418f5327760eb113904b7ceec30f22b41e4b4bd77f9fa2f7b92b4ead". You have to remove (or rename) that container to be able to reuse that name.
See 'C:\Program Files\Docker\Docker\Resources\bin\docker.exe run --help'.

Aaaand we get an error, because we tried to use the same name (docker-php), that we used for the previous, still running container:

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-cli
$ docker ps
CONTAINER ID    	IMAGE           	COMMAND              	CREATED         	STATUS          	PORTS           	NAMES
1da17524418f    	php:7.0-cli     	"docker-php-entrypoi…"   40 minutes ago  	Up 40 minutes                       	docker-php

To fix that, we will stop and remove the container via

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-cli
$ docker rm -f docker-php
docker-php

The "-f" flag forces the container to stop. Otherwise we would need an additional "docker stop docker-php" before.
Now, let's try again:

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-cli
$ docker run -di --name docker-php -v "D:/codebase/docker-php/app":/var/www docker-php-image
f27cc1310c836b15b7062e1fd381f283250a85133fb379c4cf1f891dec63770b

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-cli
$ winpty docker exec -it docker-php bash
root@f27cc1310c83:/# php -m | grep xdebug
xdebug

Yep, all good. Btw. since we "only" want to check if xdebug was installed, we could also pass that as the "script to be executed" to docker:

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-cli
$ docker run docker-php-image php -m | grep xdebug
xdebug
Be aware that this will create a new container every time it's run (, note the first entry with the wonderful name "distracted_mclean"):
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-cli
$ docker ps -a
CONTAINER ID    	IMAGE           	COMMAND              	CREATED         	STATUS                   	PORTS           	NAMES
abc9fec8a88b    	docker-php-image	"docker-php-entrypoi…"   4 minutes ago   	Exited (0) 4 minutes ago                     	distracted_mclean
f27cc1310c83    	docker-php-image	"docker-php-entrypoi…"   10 minutes ago  	Exited (137) 6 minutes ago                   	docker-php

Before we move on, let's stop and remove all containers via.
docker rm -f $(docker ps -aq)

The "$(docker ps -aq)" part returns only the numeric ids of all containers and passes them to the " docker rm -f" command.
Pascal@Landau-Laptop MINGW64 /
$ docker rm -f $(docker ps -aq)
abc9fec8a88b
f27cc1310c83

Setting up a web stack with php-fpm and nginx
Since most people are probably not only working on CLI scripts but rather on web pages, the next step in this tutorial is about setting up an nginx web server and connect it to php-fpm.
Setting up nginx
We're gonna use the official nginx image https://hub.docker.com/_/nginx/ and since we don't know anything about that image yet, let's run and explore it a bit:
docker -di run nginx:latest

$ docker run -di nginx:latest
15c6b8d8a2bff873f353d24dc9c40d3008da9396029b3f1d9db7caeebedd3f50

Note that we only used the minimum number of arguments here. Since we did not specify a name, we will simply use the ID instead to log in 

$ winpty docker exec -it 15c6b8d8a2bff873f353d24dc9c40d3008da9396029b3f1d9db7caeebedd3f50 bash
root@15c6b8d8a2bf:/#

We would expect that there is an nginx process running, but upon checking with "ps aux" we get "bash: ps: command not found" as a response. This is common when using docker images, because they are usually kept as minimal as possible. Although this is a good practice in production, it is kind of cumbersome in development. So, let's install ps via "apt-get update && apt-get install -y procps" and try again:

root@15c6b8d8a2bf:/# apt-get update && apt-get install -y procps
Get:1 http://security.debian.org/debian-security stretch/updates InRelease [94.3 kB]
[...] 
associated file /usr/share/man/man1/w.procps.1.gz (of link group w) doesn't exist
Processing triggers for libc-bin (2.24-11+deb9u3) ...
root@15c6b8d8a2bf:/# ps aux
USER        PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
root          1  0.0  0.2  32608  5148 ?        Ss   06:46   0:00 nginx: master process nginx -g daemon off;
nginx         5  0.0  0.1  33084  2388 ?        S    06:46   0:00 nginx: worker process
root         14  0.0  0.1  18132  3272 pts/0    Ss   06:50   0:00 bash
root        259  0.0  0.1  36636  2844 pts/0    R+   06:53   0:00 ps aux
root@15c6b8d8a2bf:/#

Ah. Much better. Lets dig a little deeper and see how the process is configured

root@15c6b8d8a2bf:/# nginx -V
nginx version: nginx/1.13.12
built by gcc 6.3.0 20170516 (Debian 6.3.0-18+deb9u1)
built with OpenSSL 1.1.0f  25 May 2017
TLS SNI support enabled
configure arguments: --prefix=/etc/nginx --sbin-path=/usr/sbin/nginx --modules-path=/usr/lib/nginx/modules --conf-path=/etc/nginx/nginx.conf --error-log-path=/var/log/nginx/error.log --http-log-path=/var/log/ng
inx/access.log --pid-path=/var/run/nginx.pid --lock-path=/var/run/nginx.lock --http-client-body-temp-path=/var/cache/nginx/client_temp --http-proxy-temp-path=/var/cache/nginx/proxy_temp --http-fastcgi-temp-path
=/var/cache/nginx/fastcgi_temp --http-uwsgi-temp-path=/var/cache/nginx/uwsgi_temp --http-scgi-temp-path=/var/cache/nginx/scgi_temp --user=nginx --group=nginx --with-compat --with-file-aio --with-threads --with-
http_addition_module --with-http_auth_request_module --with-http_dav_module --with-http_flv_module --with-http_gunzip_module --with-http_gzip_static_module --with-http_mp4_module --with-http_random_index_module
 --with-http_realip_module --with-http_secure_link_module --with-http_slice_module --with-http_ssl_module --with-http_stub_status_module --with-http_sub_module --with-http_v2_module --with-mail --with-mail_ssl_
module --with-stream --with-stream_realip_module --with-stream_ssl_module --with-stream_ssl_preread_module --with-cc-opt='-g -O2 -fdebug-prefix-map=/data/builder/debuild/nginx-1.13.12/debian/debuild-base/nginx-
1.13.12=. -specs=/usr/share/dpkg/no-pie-compile.specs -fstack-protector-strong -Wformat -Werror=format-security -Wp,-D_FORTIFY_SOURCE=2 -fPIC' --with-ld-opt='-specs=/usr/share/dpkg/no-pie-link.specs -Wl,-z,relr
o -Wl,-z,now -Wl,--as-needed -pie'


Sweet, so the configuration file is placed in the default location at "/etc/nginx/nginx.conf" (see --conf-path=/etc/nginx/nginx.conf). Checking that file will show us, where we need to place additional config files (e.g. for the configuration of our web site): 

root@15c6b8d8a2bf:/# cat /etc/nginx/nginx.conf

user  nginx;
worker_processes  1;

error_log  /var/log/nginx/error.log warn;
pid        /var/run/nginx.pid;


events {
    worker_connections  1024;
}


http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;

    log_format  main  '$remote_addr - $remote_user [$time_local] "$request" '
                      '$status $body_bytes_sent "$http_referer" '
                      '"$http_user_agent" "$http_x_forwarded_for"';

    access_log  /var/log/nginx/access.log  main;

    sendfile        on;
    #tcp_nopush     on;

    keepalive_timeout  65;

    #gzip  on;

    include /etc/nginx/conf.d/*.conf;
}


Note the line " include /etc/nginx/conf.d/*.conf" at the end of the file. In this directory, we'll find the default nginx config:

root@15c6b8d8a2bf:/# ls -alh /etc/nginx/conf.d/
total 12K
drwxr-xr-x 2 root root 4.0K Apr 30 13:55 .
drwxr-xr-x 3 root root 4.0K Apr 30 13:55 ..
-rw-r--r-- 1 root root 1.1K Apr  9 16:01 default.conf
root@15c6b8d8a2bf:/# cat /etc/nginx/conf.d/default.conf
server {
    listen       80;
    server_name  localhost;

    #charset koi8-r;
    #access_log  /var/log/nginx/host.access.log  main;

    location / {
        root   /usr/share/nginx/html;
        index  index.html index.htm;
    }

    #error_page  404              /404.html;

    # redirect server error pages to the static page /50x.html
    #
    error_page   500 502 503 504  /50x.html;
    location = /50x.html {
        root   /usr/share/nginx/html;
    }

    # proxy the PHP scripts to Apache listening on 127.0.0.1:80
    #
    #location ~ \.php$ {
    #    proxy_pass   http://127.0.0.1;
    #}

    # pass the PHP scripts to FastCGI server listening on 127.0.0.1:9000
    #
    #location ~ \.php$ {
    #    root           html;
    #    fastcgi_pass   127.0.0.1:9000;
    #    fastcgi_index  index.php;
    #    fastcgi_param  SCRIPT_FILENAME  /scripts$fastcgi_script_name;
    #    include        fastcgi_params;
    #}

    # deny access to .htaccess files, if Apache's document root
    # concurs with nginx's one
    #
    #location ~ /\.ht {
    #    deny  all;
    #}
}

So the server is listening on port 80. Unfortunately, we cannot reach the web server from our windows host machine, as there is an open bug for accessing container IPs from a windows host https://github.com/docker/for-win/issues/221 (don't worry, we'll fix that with port mappings in a second ;)) . So, in order to verify that the server is actually  working, we'll install curl inside the nginx container and fetch 127.0.0.1:80

root@15c6b8d8a2bf:/# apt-get install curl -y
Reading package lists... Done
Building dependency tree
[...]
Running hooks in /etc/ca-certificates/update.d...
done.
root@15c6b8d8a2bf:/# curl localhost:80
<!DOCTYPE html>
<html>
<head>
<title>Welcome to nginx!</title>
<style>
    body {
        width: 35em;
        margin: 0 auto;
        font-family: Tahoma, Verdana, Arial, sans-serif;
    }
</style>
</head>
<body>
<h1>Welcome to nginx!</h1>
<p>If you see this page, the nginx web server is successfully installed and
working. Further configuration is required.</p>

<p>For online documentation and support please refer to
<a href="http://nginx.org/">nginx.org</a>.<br/>
Commercial support is available at
<a href="http://nginx.com/">nginx.com</a>.</p>

<p><em>Thank you for using nginx.</em></p>
</body>
</html>

Looks good! Now let's customize some stuff:
- clean up the config and point the root to /var/www
- place a "Hello world" index file in /var/www/index.html

root@15c6b8d8a2bf:/# rm /etc/nginx/conf.d/default.conf
root@15c6b8d8a2bf:/# apt-get install vim -y
root@15c6b8d8a2bf:/# vi /etc/nginx/conf.d/site.conf
server {
    	listen       80;
    	server_name  localhost;
	root   /var/www;
}
root@15c6b8d8a2bf:/# mkdir /var/www
root@15c6b8d8a2bf:/# echo "Hello world!" > /var/www/index.html

To make the changes become effective, we need to reload nginx:

root@15c6b8d8a2bf:/# nginx -s reload
2018/05/29 09:22:54 [notice] 351#351: signal process started

Check with curl, et voilá:

root@943d6fab864c:/# curl 127.0.0.1:80
Hello world!

With all that new information we can actually set up our nginx image with the following folder structure on the host machine:

D:\codebase\docker-php
+ nginx\
  + conf.d\site.conf
  + Dockerfile
+ app\
  + index.html
  + hello-world.php


# Dockerfile
FROM nginx:latest

# conf.d\site.conf
server {
    	listen       80;
    	server_name  localhost;
	root   /var/www;
}

# app\index.html
Hello World

Clean up the "exploration" nginx container via
docker rm -f 15c6b8d8a2bff873f353d24dc9c40d3008da9396029b3f1d9db7caeebedd3f50

Build our new image
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/nginx
$ docker build -t docker-nginx-image .
Sending build context to Docker daemon  3.584kB
Step 1/1 : FROM nginx:latest
 ---> ae513a47849c
Successfully built ae513a47849c
Successfully tagged docker-nginx-image:latest
SECURITY WARNING: You are building a Docker image from Windows against a non-Windows Docker host. All files and directories added to build context will have '-rwxr-xr-x' permissions. It is recommended to double check and reset permissions for sensitive files and directories.

And then run the "new" container via 
docker run -di --name docker-nginx -p 8080:80 -v "D:\codebase\docker-php\nginx\conf.d":/etc/nginx/conf.d/ -v "D:\codebase\docker-php\app":/var/www docker-nginx-image

where
-p 8080:80 # maps port 8080 on the windows host to port 80 in the container
-v "D:\codebase\docker-php\nginx\conf.d":/etc/nginx/conf.d/ # mounts the conf.d folder on the host to the correct directory in the container
-v "D:\codebase\docker-php\app":/var/www # mounts the "code" directory in the correct place

Thanks to the port mapping we can now simply open http://127.0.0.1:8080/ in a browser on the host machine and see the content of our app\index.html file.

Further reading on https://www.digitalocean.com/community/tutorials/how-to-run-nginx-in-a-docker-container-on-ubuntu-14-04


Setting up php-fpm
We are already familiar with the official docker PHP image but have only used the cli-only version so far. As with nginx, let's explore the php-fpm one:

$ docker run -di --name php-fpm-test php:7.0-fpm
c5d23b6945631cb4d33b48a64258620aaf64b30a3c4f5329614c5d2128bf7e64

The first thing to note is, that the image automatically exposes port 9000 as a docker ps -a reveals:

$ docker ps -a
CONTAINER ID        IMAGE               COMMAND                  CREATED             STATUS                      PORTS                  NAMES
c5d23b694563        php:7.0-fpm         "docker-php-entrypoi…"   4 hours ago         Up 4 hours                  9000/tcp               php-fpm-test

When we examine the Dockerfile that was used to build the image (click here https://hub.docker.com/r/library/php/ and search for the "7.0-fpm" tag the at the time of this wrinting links to https://github.com/docker-library/php/blob/27c65bbd606d1745765b89bf43f39b06efad1e43/7.0/stretch/fpm/Dockerfile), we can see that it contains an "EXPOSE 9000" at the bottom.

Let's see what else we can figure out. Login:

$ winpty docker exec -it php-fpm-test bash
root@c5d23b694563:/var/www/html#

First, will check where the configuration files are located:

root@c5d23b694563:/var/www/html# php-fpm -i | grep config
Configure Command =>  './configure'  '--build=x86_64-linux-gnu' '--with-config-file-path=/usr/local/etc/php' '--with-config-file-scan-dir=/usr/local/etc/php/conf.d' '--enable-option-checking=fatal' '--disable-c
gi' '--with-mhash' '--enable-ftp' '--enable-mbstring' '--enable-mysqlnd' '--with-curl' '--with-libedit' '--with-openssl' '--with-zlib' '--with-libdir=lib/x86_64-linux-gnu' '--enable-fpm' '--with-fpm-user=www-da
ta' '--with-fpm-group=www-data' 'build_alias=x86_64-linux-gnu'
fpm.config => no value => no value
[...]

"--with-config-file-path=/usr/local/etc/php" is our suspect. So it is very likely, that we will find the Global directives config file [https://myjeeva.com/php-fpm-configuration-101.html#global-directives] at /usr/local/etc/php-fpm.conf (unfortunately, we cannot resolve the location directly). Grep'ing this file for "include=" reveals the location for the Pool directives [https://myjeeva.com/php-fpm-configuration-101.html#pool-directives]:

root@c5d23b694563:/var/www/html# grep "include=" /usr/local/etc/php-fpm.conf
include=etc/php-fpm.d/*.conf

Hm - a relative path. That looks kinda odd? Let's get a little more context:

root@c5d23b694563:/var/www/html# grep -C 6 "include=" /usr/local/etc/php-fpm.conf
; Include one or more files. If glob(3) exists, it is used to include a bunch of
; files from a glob(3) pattern. This directive can be used everywhere in the
; file.
; Relative path can also be used. They will be prefixed by:
;  - the global prefix if it's been set (-p argument)
;  - /usr/local otherwise
include=etc/php-fpm.d/*.conf

Ah - that makes more sense. So we need to resolve "etc/php-fpm.d/*.conf" starting from "/usr/local". Resulting in "/usr/local/etc/php-fpm.d/*.conf" (usually you'll at least find a www.conf file in there). The pool config determines how php-fpm listens for connections (e.g. via Unix socket or via TCP IP:port).

root@c5d23b694563:/var/www/html# cat /usr/local/etc/php-fpm.d/www.conf
[...]
; The address on which to accept FastCGI requests.
; Valid syntaxes are:
;   'ip.add.re.ss:port'    - to listen on a TCP socket to a specific IPv4 address on
;                            a specific port;
;   '[ip:6:addr:ess]:port' - to listen on a TCP socket to a specific IPv6 address on
;                            a specific port;
;   'port'                 - to listen on a TCP socket to all addresses
;                            (IPv6 and IPv4-mapped) on a specific port;
;   '/path/to/unix/socket' - to listen on a unix socket.
; Note: This value is mandatory.
listen = 127.0.0.1:9000

[...]

So, php-fpm ist listening on port 9000 on 127.0.0.1 (localhost). So it makes total sense to expose port 9000.

Installing xdebug
Since we probably also want to debug php-fpm, xdebug needs to be setup as well. The process is pretty much the same as for the cli image:

root@b880e4fe30b8:/var/www/html# pecl install xdebug-2.6.0
root@b880e4fe30b8:/var/www/html# docker-php-ext-enable xdebug
root@b880e4fe30b8:/var/www/html# php-fpm -m | grep xdebug
xdebug

Of course we'll also put that in its own Dockerfile:

D:\codebase\docker-php
+ php-fpm\
  + Dockerfile

# php-fpm\Dockerfile
FROM php:7.0-fpm
RUN pecl install xdebug-2.6.0 \
    && docker-php-ext-enable xdebug

Clean up the test container

Pascal@Landau-Laptop MINGW64 /
$ docker rm -f php-fpm-test
php-fpm-test

and build the new image
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-fpm
$ docker build -t docker-php-fpm-image .

Connecting  nginx and php-fpm
Now that we have containers for nginx and php-fpm, we need to connect them. To do so, we have to make sure that both containers are in the same network and can talk to each other. Docker provides so called user defined bridge networks [ https://docs.docker.com/network/network-tutorial-standalone/#use-user-defined-bridge-networks ] allowing automatic service discovery . That basically means, that our nginx container can use the name of the php-fpm container to connect to it. Otherwise we would have to figure out the containers IP address in the default network every time we start the containers.

"docker network ls" reveals a list of the current networks

Pascal@Landau-Laptop MINGW64 /
$ docker network ls
NETWORK ID          NAME                DRIVER              SCOPE
7019b0b37ba7        bridge              bridge              local
3820ad97cc92        host                host                local
03fecefbe8c9        none                null                loca

Now let's add a new one for our web stack:

docker network create --driver bridge web-network

Pascal@Landau-Laptop MINGW64 /
$ docker network create --driver bridge web-network
20966495e04e9f9df9fd64fb6035a9e9bc3aa6d83186dcd23454e085a0d97648

Pascal@Landau-Laptop MINGW64 /
$ docker network ls
NETWORK ID          NAME                DRIVER              SCOPE
7019b0b37ba7        bridge              bridge              local
3820ad97cc92        host                host                local
03fecefbe8c9        none                null                local
20966495e04e        web-network         bridge              local

If the nginx container is still running, you can "connect" it to the new network via

Pascal@Landau-Laptop MINGW64 /
$ docker network connect web-network docker-nginx

Finally, we need to mount the code folder we mounted to the nginx container also in the php-fpm container in the same location. We can also specify the network in the run command via the --network option

docker run -di --name docker-php-fpm -v "D:\codebase\docker-php\app":/var/www --network web-network docker-php-fpm-image


Pascal@Landau-Laptop MINGW64 /
$ docker network connect web-network docker-nginx
We can verify that both containers are connected to the web-network by running docker network inspect web-network

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php/php-fpm
$ docker network inspect web-network
[
    {
        "Name": "web-network",
        "Id": "20966495e04e9f9df9fd64fb6035a9e9bc3aa6d83186dcd23454e085a0d97648",
        "Created": "2018-05-30T06:39:44.3107066Z",
        "Scope": "local",
        "Driver": "bridge",
        "EnableIPv6": false,
        "IPAM": {
            "Driver": "default",
            "Options": {},
            "Config": [
                {
                    "Subnet": "172.18.0.0/16",
                    "Gateway": "172.18.0.1"
                }
            ]
        },
        "Internal": false,
        "Attachable": false,
        "Ingress": false,
        "ConfigFrom": {
            "Network": ""
        },
        "ConfigOnly": false,
        "Containers": {
            "3358e813423165880d59c8ebc2cb4c563ee8ad1d401595f8bfcf763ff5db8f4a": {
                "Name": "docker-php-fpm",
                "EndpointID": "d2f1d6285a0932817e1fb8839bef3a6d178f5306a2116307dba200038ea2a3a3",
                "MacAddress": "02:42:ac:12:00:03",
                "IPv4Address": "172.18.0.3/16",
                "IPv6Address": ""
            },
            "eaa5c05942788985e90a80fa000723286e9b4e7179d0f6f431c0f5109e012764": {
                "Name": "docker-nginx",
                "EndpointID": "274fa9a6868aff656078a72e19c05fb87e4e86b83aaf12be9b943890140a421d",
                "MacAddress": "02:42:ac:12:00:02",
                "IPv4Address": "172.18.0.2/16",
                "IPv6Address": ""
            }
        },
        "Options": {},
        "Labels": {}
    }
]

Note the "Containers" key, that reveals that the docker-php-fpm container has the IP address 172.18.0.3 and the docker-nginx container is reachable via 172.18.0.2.

But can we actually connect from nginx to php-fpm? Let's find out:
Log into the nginx container and ping the IP

$ winpty docker exec -ti docker-nginx bash
bash: ping: command not found
root@eaa5c0594278:/# ping 172.18.0.3 -c 2
bash: ping: command not found

.. well, after we make the command available by installing iputils-ping

root@eaa5c0594278:/# apt-get update && apt-get install iputils-ping -y
root@eaa5c0594278:/# ping 172.18.0.3 -c 2
PING 172.18.0.3 (172.18.0.3) 56(84) bytes of data.
64 bytes from 172.18.0.3: icmp_seq=1 ttl=64 time=0.142 ms
64 bytes from 172.18.0.3: icmp_seq=2 ttl=64 time=0.162 ms

--- 172.18.0.3 ping statistics ---
2 packets transmitted, 2 received, 0% packet loss, time 1071ms
rtt min/avg/max/mdev = 0.142/0.152/0.162/0.010 ms
root@eaa5c0594278:/#

We can see the container. That's good. But we were also promised we could reach the container by its name (docker-php-fpm)...

root@eaa5c0594278:/# ping docker-php-fpm -c 2
PING docker-php-fpm (172.18.0.3) 56(84) bytes of data.
64 bytes from docker-php-fpm.web-network (172.18.0.3): icmp_seq=1 ttl=64 time=0.080 ms
64 bytes from docker-php-fpm.web-network (172.18.0.3): icmp_seq=2 ttl=64 time=0.131 ms

--- docker-php-fpm ping statistics ---
2 packets transmitted, 2 received, 0% packet loss, time 1045ms
rtt min/avg/max/mdev = 0.080/0.105/0.131/0.027 ms
root@eaa5c0594278:/#

And we can - awesome! Now we need to tell nginx to pass all PHP related requests to php-fpm by changing the nginx\conf.d\site.conf file on our windows host to 

# conf.d\site.conf
server {
    	listen       80;
    	server_name  localhost;
	root   /var/www;
	
   location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass docker-php-fpm:9000;
        include fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }


}

note the fastcgi_pass docker-php-fpm:9000; line that tells nginx how to reach our php-fpm service.

Reload nginx 

root@eaa5c0594278:/# nginx -s reload
2018/05/30 08:05:56 [notice] 295#295: signal process started

and modify the app\hello-world.php file to reflect that we're actually calling that from php-fpm:

#app\hello-world.php
<?php
  echo "Hello world (php)"; 
?>

Now open http://127.0.0.1:8080/hello-world.php on a browser on your host machine and be amazed that everything works as expected :)

[image: hello-world-php]


https://stackoverflow.com/questions/29905953/how-to-correctly-link-php-fpm-and-nginx-docker-containers

https://docs.docker.com/network/network-tutorial-standalone/#use-the-default-bridge-network

Putting it all together: meet docker-compose

Lets sum up what we have do now to get everything up and running.
1. start the CLI
2. start nginx
3. start fpm

docker run -di --name docker-php -v "D:\codebase\docker-php\app":/var/www --network web-network docker-php-image
docker run -di --name docker-nginx -p 8080:80 -v "D:\codebase\docker-php\nginx\conf.d":/etc/nginx/conf.d/ -v "D:\codebase\docker-php\app":/var/www  --network web-network docker-nginx-image
docker run -di --name docker-php-fpm -v "D:\codebase\docker-php\app":/var/www --network web-network docker-php-fpm-image

Hm. That's still alright I guess... but it also feels like "a lot". Wouldn't it be much better to have everything neatly defined in one place? I bet it is, so let me introduce you to docker-compose [https://docs.docker.com/compose/ ]:

"
Compose is a tool for defining and running multi-container Docker applications. With Compose, you use a YAML file to configure your application's services. Then, with a single command, you create and start all the services from your configuration.
"

Lets do this step by step, starting with the php-cli container.

# tell docker what version of the docker-compose.yml were using
version: '3'

# define the network
networks:
  web-network:

# start the services section
services:
  # define the name of our service
  # corresponds to the "--name" parameter
  docker-php-cli:
    # define the directory where the build should happened,
    # i.e. where the Dockerfile of the service is located
    # all paths are relative to the location of docker-compose.yml
    build: 
      context: ./php-cli
    # reserve a tty - otherwise the container shuts down immediately
    # corresponds to the "-i" flag
    tty: true
    # mount the app directory of the host to /var/www in the container
    # corresponds to the "-v" option
    volumes:
      - ./app:/var/www
    # connect to the network
    # corresponds to the "--network" option
    networks:
      - web-network

Before we get started, we're gonna clean up the old containers:

$ docker rm -f $(docker ps -aq)
To test the docker-compose.yml we need to run docker-compose up -d

$ docker-compose up -d
Creating network "docker-php_web-network" with the default driver
Building docker-php-cli
Step 1/2 : FROM php:7.0-cli
 ---> da771ba4e565
Step 2/2 : RUN pecl install xdebug-2.6.0     && docker-php-ext-enable xdebug
 ---> Using cache
 ---> 12be27256b12
Successfully built 12be27256b12
Successfully tagged docker-php_docker-php-cli:latest
Image for service docker-php-cli was built because it did not already exist. To rebuild this image you must use `docker-compose build` or `docker-compose up --build`.
Creating docker-php_docker-php-cli_1 ... done

Note that the image is build from scratch the when we run docker-compose up for the first time. A "docker ps -a" shows that the container is running fine.


Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker ps -a
CONTAINER ID        IMAGE                       COMMAND                  CREATED             STATUS              PORTS               NAMES
adf794f27315        docker-php_docker-php-cli   "docker-php-entrypoi…"   3 minutes ago       Up 2 minutes                            docker-php_docker-php-cli_1

$ winpty docker exec -it docker-php_docker-php-cli_1 bash
root@53289e9638d7:/# php /var/www/
hello-world.php  html/            index.html
root@53289e9638d7:/# php /var/www/
hello-world.php  html/            index.html
root@53289e9638d7:/# php /var/www/hello-world.php
Hello world (php)root@53289e9638d7:/#

And we can log in and execute source code from the host machine. Check.
Now run "docker-compose down" to shut the container down again.

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker-compose down
Stopping docker-php_docker-php-cli_1 ... done
Removing network docker-php_web-network

And add the remaining services to the docker-compose.yml

# tell docker what version of the docker-compose.yml were using
version: '3'

# define the network
networks:
  web-network:

# start the services section
services:
  # define the name of our service
  # corresponds to the "--name" parameter
  docker-php-cli:
    # define the directory where the build should happened,
    # i.e. where the Dockerfile of the service is located
    # all paths are relative to the location of docker-compose.yml
    build: 
      context: ./php-cli
    # reserve a tty - otherwise the container shuts down immediately
    # corresponds to the "-i" flag
    tty: true
    # mount the app directory of the host to /var/www in the container
    # corresponds to the "-v" option
    volumes:
      - ./app:/var/www
    # connect to the network
    # corresponds to the "--network" option
    networks:
      - web-network
  
  docker-nginx:
    build: 
      context: ./nginx
    # defines the port mapping
    # corresponds to the "-p" flag
    ports:
      - "8080:80"
    volumes:
      - ./app:/var/www
      - ./nginx/conf.d:/etc/nginx/conf.d
    networks:
      - web-network

  docker-php-fpm:
    build: 
      context: ./php-fpm
    volumes:
      - ./app:/var/www
    networks:
      - web-network

And up again...

Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php
$ docker-compose up -d
Building docker-nginx
Step 1/1 : FROM nginx:latest
 ---> ae513a47849c
Successfully built ae513a47849c
Successfully tagged docker-php_docker-nginx:latest
Image for service docker-nginx was built because it did not already exist. To rebuild this image you must use `docker-compose build` or `docker-compose up --build`.
Building docker-php-fpm
Step 1/2 : FROM php:7.0-fpm
 ---> a637000da5a3
Step 2/2 : RUN pecl install xdebug-2.6.0     && docker-php-ext-enable xdebug
 ---> Running in 4ec27516df54
downloading xdebug-2.6.0.tgz ...
Starting to download xdebug-2.6.0.tgz (283,644 bytes)
[...]
---> 120c8472b4f3
Successfully built 120c8472b4f3
Successfully tagged docker-php_docker-php-fpm:latest
Image for service docker-php-fpm was built because it did not already exist. To rebuild this image you must use `docker-compose build` or `docker-compose up --build`.
Creating docker-php_docker-nginx_1   ... done
Creating docker-php_docker-php-cli_1 ... done
Creating docker-php_docker-php-fpm_1 ... done


Only nginx and php-fpm needed to be built because the php-cli one already existed. Lets check if we can still open http://127.0.0.1:8080/hello-world.php in a browser on the host machine.

Yes we can! So instead of needing to run 3 different command with a bunch of parameters we're now down to "docker-compose up -d". Looks like an improvement to me ;)
