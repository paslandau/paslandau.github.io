---
extends: _layouts.post
section: content
title: "How to setup PhpStorm with Xdebug on Docker [Tutorial Part 2]"
subheading: "... to debug PHP CLI scripts, PHP-FPM from the Browser, Unit Tests, etc."
h1: "Setting up PhpStorm with Xdebug for local development on Docker"
description: "Detailed instructions on how to setup PhpStorm  properly to work with XDebug in Docker containers (cli and fpm) for local development."
author: "Pascal Landau"
published_at: "2018-07-29 22:00:00"
vgwort: ""
category: "development"
slug: "setup-phpstorm-with-xdebug-on-docker"
---

In the second part of this tutorial series on developing PHP on Docker we're taking a good hard look
at PhpStorm and Xdebug. We will
- learn how to run scripts from within PhpStorm on Docker (e.g. Unit Tests)
- understand how Xdebug and PhpStorm work together in order to debug pretty much everything locally
  (CLI scripts, HTTP calls triggered from the browser, PHP workers/daemons)

And just as a reminder, the first part is over at 
[Setting up PHP, PHP-FPM and NGINX for local development on Docker](/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/).

**Note**: The setup that I am going to use is for demonstration purposes only! I do **not** recommend that you use it
"as is" as your development setup. Some problems that I won't solve here include:
- everything is owned by root (no dedicated user; that will in particular be problematic for linux users)
- SSH login credentials are hard-coded in the container (inherently insecure)
- `host.docker.internal` will only exist for Windows and Mac users 8, NOT for unix users

There will be a third part of this series that will deal with all of those (and some more common) problems and 
aims at providing a consistent development environment for all developers in a team (regardless of the OS they are using).

TOC
- the docker containers
-- general preconditions
-- xdebug installed, all synced to the same codebase
-- Troubleshooting for linux users:
--- make host.docker.internal available
--- use correct user to run scripts
-- final docker-compose file
- How xdebug works
-- graphic
-- connect-back
- php scripts from php storm
- Troubleshooting
-- enable xdebug log
-- containers can see hosts and vice-versa
-- ports are open / connection works
-- PHP is listening for connections

## Setup: The docker containers
We will need three different containers in order to test all our use cases:
1. php-fpm 
2. nginx
3. php-cli (workspace [for cli scripts and unit tests] and as daemon / worker)

Luckily, we already have a good understanding on how to create those, although we'll need to make some 
adjustments to make everything work smoothly with PhpStorm. I'm gonna walk you through all the necessary changes,
but I'd still recommend to clone the corresponding git repository [docker-php-tutorial](https://github.com/paslandau/docker-php-tutorial)
(unless you've already done that in part 1), checkout branch [`part_2`](https://github.com/paslandau/docker-php-tutorial/tree/part_2) and
build the containers now.

As in part 1, I'm assuming your codebase lives at `/c/codesbase`:

````
cd /c/codebase/
git clone https://github.com/paslandau/docker-php-tutorial.git
cd docker-php-tutorial
git checkout part_2
docker-compose docker-compose build
````

Further, make sure to open `/c/codebase/docker-php-tutorial` as a project in PhpStorm.

## Running PHP from PhpStorm in Docker
In general, there are two ways to run PHP from PhpStorm using Docker:
1. via the built-in Docker setup
2. via deployment configuration (treating docker more or less like a VM)

### Run PHP via built-in Docker setup
This is the "easier" way and should mostly work "out of the box". When you run a PHP script using this method, PhpStorm will start a 
docker container and configure it automatically (path mappings, network setup, ...). Next, the script in question is executed and the container 
is stopped afterwards. Though this is nice, it doesn't give me enough have enough control for my taste (e.g. I prefer to have a container 
running all the time and not only for the execution time of the script). However, here are the steps to make this work:

#### Enable docker to communicate on port 2375 
Open the Docker Setting in tab "General" and activate the checkbox that says 
"Expose daemon on tcp://localhost:2375 without TLS".

[screenshot]

#### Configure Docker in PhpStorm
In PhpStorm, open settings and navigate to `File | Settings | Build, Execution, Deployment | Docker`. Fill out `Name` and `Engine API URL`:
- Name: Docker
- Engine API URL: tcp://localhost:2375

PhpStorm will automatically validate your settings and show a "Connection successful" below the path mappings box:

[screenshot]

#### Configure PHP CLI Interpreter
Open settings and navigate to `File | Settings | Languages & Frameworks | PHP`. Click on the three dots "..." next to "CLI Interpreter".

[screenshot]

In the newly opened pop up click on the "+" sign on the top left and choose "From Docker,Vagrant,VM,Remote..."

[screenshot]

Next, choose "Docker" from the radio buttons and select our previously created Docker server (named "Docker").
As image, choose "docker-php-tutorial_docker-php-cli:latest" (which is one of the images used in this tutorial). If you don't see this 
this image you've probably not yet built the containers. In that case, please checkout the repo and build the containers: 

````
cd /c/codebase/
git clone https://github.com/paslandau/docker-php-tutorial.git
cd docker-php-tutorial
git checkout part_2
docker-compose docker-compose build
````

[screenshot]

PhpStorm will no try to create the container and figure out if it can run PHP. If all goes well, you should see the following screenshot
with information about the PHP and Xdebug versions in the image/container.

_Note_: Sometimes, this does not work immediately. If that's the case for you, try to click the "Refresh" icon next to "PHP executable".

[screenshot]

After you hit okay, you'll be back in the PHP Interpreter screen where our newly configured Docker interpreter should be already selected:

[screenshot]

Note that PhpStorm has automatically configure the path mappings as `-v` command line option for the Docker container. After hitting okay
one last time, everything is set up.

#### Run/debug a php script on docker
To verify that everything is working, open the file `app/hello-world.php` in PhpStorm, right click in the editor pane and choose "Run".

[screenshot]

PhpStorm will start the configured container and run the script. The output is then visible in at the bottom of the IDE:

[screenshot]

Since we're using an image that has Xdebug installed, we can also set a breakpoint and use "Debug" instead of "Run" to trigger a debug session:

[screenshot]

PhpStorm should stop on the marked line. 

[screenshot]

When you take a look at the "Console" panel at the bottom of the IDE, you should see something like this:

````
docker://docker-php-tutorial_docker-php-cli:latest/php -dxdebug.remote_enable=1 -dxdebug.remote_mode=req -dxdebug.remote_port=9000 -dxdebug.remote_host=192.168.10.1 /opt/project/app/hello-world.php
````

Please keep the `-dxdebug.remote_host=192.168.10.1` option in mind - this will be "interesting" when we set up a Docker-bases PHP Interpreter
via deployment configuration ;)

PS: You find the official documentation for the built-in Docker support at 
[Docker Support in PhpStorm](https://confluence.jetbrains.com/display/PhpStorm/Docker+Support+in+PhpStorm).

### Run PHP on Docker via deployment configuration
The previously explained method is nice, but it is lacking flexibility and it's also pretty slow as the container used to run
the script needs to be started each time we want to execute something. Luckily, there is an additional way of running PHP scripts
on Docker in PhpStorm, which is closely related to the Vagrant setup that I explained in 
[Configuring PhpStorm to use the vagrant box](https://www.pascallandau.com/blog/phpstorm-with-vagrant-using-laravel-homestead-on-windows-10/#configuring-phpstorm-to-use-the-vagrant-box).

To make this work, we will keep a docker container running all the time and configure PhpStorm to connect to it via SSH. Thus, PhpStorm
effectively treats the docker container as any other remote host.

#### Preparing the "workspace" container
Please make sure to checkout my demo repository and switch to the correct branch first:

````
cd /c/codebase/
git clone https://github.com/paslandau/docker-php-tutorial.git
cd docker-php-tutorial
git checkout part_2
````

For now, we only need the `php-cli` container. In it, we need to setup the xdebug extension (already done and explained in the previous part) and
an SSH server so that we can log in via SSH. Let's take a look a the `Dockerfile`:

````
FROM php:7.0-cli

RUN apt-get update -yqq \
 && apt-get install -yqq \
    # install sshd
    openssh-server \
    # install ping and netcat (for debugging xdebug connectivity)
    iputils-ping netcat \
    # fix ssh start up bug
    # @see https://github.com/ansible/ansible-container/issues/141
 && mkdir /var/run/sshd \
;

# add default public key to authorized_keys
COPY ./ssh/insecure_id_rsa.pub /root/insecure_id_rsa.pub
RUN mkdir -p /root/.ssh \
 && cat /root/insecure_id_rsa.pub >> /root/.ssh/authorized_keys \
 && rm -rf /root/insecure_id_rsa.pub \
;

RUN pecl -q install xdebug-2.6.0 \
;
COPY ./conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# @see https://docs.docker.com/engine/examples/running_ssh_service/
CMD ["/usr/sbin/sshd", "-D"]

````

and the `docker-compose.yml`:
````
  docker-php-cli:
    # define the directory where the build should happened,
    # i.e. where the Dockerfile of the service is located
    # all paths are relative to the location of docker-compose.yml
    build: 
      context: ./php-cli
    # make the SSH port available via port mapping
    ports:
      - "2222:22"
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
````

There are four things to note:
1. installing the server 
2. adding the ssh keys to actually log in
3. changing the default `CMD` to keep the SSH daemon running
4. port- and volume mapping

##### Installing the server
The server installation is straight forward:
````
apt-get install -yqq openssh-server
````
the only none-intuitive thing is, that we need to "manually" create the directory `/var/run/sshd`, [due to some bug]( https://github.com/ansible/ansible-container/issues/141).

##### Adding the ssh keys
For the ssh keys, I'm choosing the easy route (for now) and use a pre-generated ssh key pair (see `php-cli/ssh/*`).
The content of the public key is appended to `/root/.ssh/authorized_keys` so that I can log in to the container as user `root` using the 
corresponding private key from `php-cli/ssh/insecure_id_rsa`.

**Caution**: Of course, this is massively insecure! Those keys are part of the repository, making them available to everybody with access to the repo.
That makes sense for this publicly available tutorial (because everything works "out of the box" for everybody following along) but it is also one 
of the reasons you should **not** use that repo as your **actual** development setup.

Again, there will be another part of this tutorial in which I'll present a solution to this problem (using volumes to share my local ssh keys with a 
container and an `ENTRYPOINT` to put them in the right place).

##### Keep the SSH daemon running
For SSH to work, we must start `sshd` and keep it running in the container. We achieve this by using `CMD ["/usr/sbin/sshd", "-D"]` in the 
Dockerfile, following the official docker example [Dockerize an SSH service](https://docs.docker.com/engine/examples/running_ssh_service/).

##### Port- and volume mapping
Since docker containers do not have deterministic IP addresses, we map port 22 from the container to port 2222 on our host machine and we 
further provide a path mapping from our local `./app` folder to `/var/www` in the container. Both pieces of information a required when
we configure PhpStorm later on.

Now that everything is in place, let's build and run the container:

````
cd /c/codebase/docker-php-tutorial
docker-compose up -d docker-php-cli
````

yielding

````
Pascal@Landau-Laptop MINGW64 /d/codebase/docker-php-tutorial (part_2)
$ docker-compose up -d docker-php-cli
Creating docker-php-tutorial_docker-php-cli_1 ... done
````

_Note_: One might argue, that it's kinda "defeating" the purpose of docker, when we now treat it as a VM, installing SSH and neglecting it's
"one process per container" rule. But honestly, I don't care about that when
it comes to my local development setup as my main goal is to have something lightweight, that is easily shareable with my team to have a 
consistent infrastructure setup ;)

#### Configure the deployment configuration
In PhpStorm, navigate to `File | Settings | Build, Execution, Deployment | Deployment`. 
In the newly opened pop up click on the "+" sign on the top left and choose "From Docker,Vagrant,VM,Remote..." with:
- Name: Docker (SSH)
- Type: SFTP

[screenshot]

In the `Connection` tab, choose the following settings:
- SFTP host: 127.0.0.1
- Port: 2222
- User name: root
- Auth type: Key pair (OpenSSH or PuTTY)
- Private key file: `C:\codebase\docker-php-tutorial\php-cli\ssh\insecure_id_rsa`

_Notes_:
- the "Port" corresponds to the port mapping that we defined in the `docker-compose.yml` file
- the "Private key file" is the "insecure" ssh key that matches the public key we specified in the `php-cli/Dockerfile`

[screenshot]

Hit the "Test SFT connection" button to test the settings. You should see

[screenshot]

(there might also appear a fingerprint warning because we're using 127.0.0.1 as host. You can simply ignore that warning).

Now choose the `Mapping` tab and fill it the fields as follows:
- Local path: `C:\codebase\docker-php-tutorial\app`
- Deployment path on server 'Docker (SSH)': `/var/www/`

Those mappings correspond to the volume definition for the `docker-php-cli` service in `docker-compose.yml`:
````
[...]
    # mount the app directory of the host to /var/www in the container
    # corresponds to the "-v" option
    volumes:
      - ./app:/var/www
[...]
````

[screenshot]
Next, we need to create an PHP Interpreter based on our newly created Deployment Configuration. 
Open settings and navigate to `File | Settings | Languages & Frameworks | PHP`. Click on the three dots "..." next to "CLI Interpreter".

[screenshot]

In the newly opened pop up click on the "+" sign on the top left and choose "From Docker,Vagrant,VM,Remote..."

[screenshot]

Next, choose "Deployment Configuration" from the radio buttons and select the "Docker (SSH)" entry. Please make sure to enter 
`/usr/local/bin/php` as path for the PHP executable (as PhpStorm by default will set this path to `/usr/bin/php`).

[screenshot]

Set "Docker (SSH)" as name for the new interpreter and click Okay. Confirm the new PHP Interpreter to close the settings dialog.

#### Run/debug a php script on docker
To verify that everything is working, open the file `app/hello-world.php` in PhpStorm, right click in the editor pane and choose "Run".

[screenshot]

PhpStorm will start the configured container and run the script. The output is then visible in at the bottom of the IDE:

[screenshot]

Since we're using an image that has Xdebug installed, we can also set a breakpoint and use "Debug" instead of "Run" to trigger a debug session:

[screenshot]

Hm weird... Although this worked flawlessly when we used the built-in functionality, it does not when we use the deployment configuration and shows
a "Connection with `Xdebug 2.6.0` not established." error.

[screenshot]

#### Fix Xdebug on PhpStorm when run from a Docker container

Long story short: There is a bug in the networking setup of Docker for Win that makes PhpStorm use the wrong `remote_host` when it starts a 
debugging session. When you take a look at the "Console" panel at the bottom of the IDE, you should see something like this:

````
sftp://root@127.0.0.1:2222/usr/local/bin/php -dxdebug.remote_enable=1 -dxdebug.remote_mode=req -dxdebug.remote_port=9000 -dxdebug.remote_host=172.18.0.1 /var/www/hello-world.php
````

The `-dxdebug.remote_host=172.18.0.1` option is our suspect here. Luckily, since 
[Docker v18.03](https://docs.docker.com/docker-for-windows/networking/#use-cases-and-workarounds) there is a "magic" DNS entry called `host.docker.internal`
that we can use to reach the host from a container.

So, how can we solve this? PhpStorm enables us to 
[pass custom options to Xdebug](https://www.jetbrains.com/help/phpstorm/configuring-xdebug.html#d165872e407) when a debugging session is initiated.
Open `File | Settings | Languages & Frameworks | PHP` and click on the "..." next to "PHP Interpreter" to bring up the interpreters. Choose 
"Docker (SSH)" in the left pane and click on the little folder icon on the bottom of the window next to "Configuration options". In the pop up enter
`xdebug.remote_host` as key and `host.docker.internal` as value and hit "OK".

[screenshot]

This results in the configuration setting `-dxdebug.remote_host=host.docker.internal` that is now appended to the remaining (default) arguments 
that PhpStorm uses and will **override** any existing options (including the inccorect `xdebug.remote_host`).

[screenshot]

Initiating a debug session on `app/hello-world.php` will now finally stop the execution as expected and the 
"Console" panel at the bottom of the IDE, shows

````
sftp://root@127.0.0.1:2222/usr/local/bin/php -dxdebug.remote_enable=1 -dxdebug.remote_mode=req -dxdebug.remote_port=9000 -dxdebug.remote_host=172.18.0.1 -dxdebug.remote_host=host.docker.internal /var/www/hello-world.php
````

## Xdebug all the things!

### How Xdebug works
I've used Xdebug for a couple of years now, but I've actually never _really_ understood what is happening under the
hood and how my IDE "magically" suspends the execution at a specific line in my source code. That has also never 
been a problem, because mostly "it just worked". But with docker, it didn't and I felt quite helpless because 
I just didn't know how to "debug" the problem. I can imagine I'm not alone in this, so I'm gonna dedicate at least a 
small chapter in this tutorial to Xdebug itself.

Fortunately, the Xdebug homepage does a really good job at explaining the communication flow. 
The following information is taken from their [(Remote) Communication Set Up explanation](https://xdebug.org/docs/remote#communication):

> With remote debugging, Xdebug embedded in PHP acts like the client, and the IDE as the server. The following animation shows how the communication channel is set-up:
> [![Xdebug Communication Set Up](https://xdebug.org/images/docs/dbgp-setup.gif)](https://xdebug.org/images/docs/dbgp-setup.gif)
> - The IP of the server is 10.0.1.2 with HTTP on port 80
> - The IDE is on IP 10.0.1.42, so xdebug.remote_host is set to 10.0.1.42
> - The IDE listens on port 9000, so xdebug.remote_port is set to 9000
> - The HTTP request is started on the machine running the IDE
> - Xdebug connects to 10.0.1.42:9000
> - Debugging runs, HTTP Response provided

In addition, I'd like to add some points that are important from my perspective:
- the xdebug extension has to be installed and enabled on the server we want to debug
- `$something` (e.g. a HTTP request through the browser; PhpStorm when executing a unit test) triggers a PHP execution
- xdebug steps in and tries to establish a communication with `$something` by connecting "back" on a preconfigured port
  (9000 by default). The IDE should thus listen on the same port for incoming connections.
  - this one was especially new to me because I always thought xdebug is somehow running on my local machine / in my IDE
- if the connection can be established, xdebug starts to send information, e.g. which "server" it is 
  running on and what file / line is being executed
- your IDE receives the information and tries to match it to your source code (usually something that you need to configure upfront,
  in PhpStorm it's called "Path Mapping")
- if there's a breakpoint registered at that specific location, the execution is suspended

By understanding the full cycle of a debug session, we can now rule out every potential source of error step by step.

## How Xdebug works

## Table of contents
<ul>
<li><a id="introduction">Introduction</a>
    <ul>
    <li><a href="#precondiction">Preconditions</a></li>
    <li><a href="#why-use-docker">Why use Docker?</a></li>
    <li><a href="#transition-vagrant">Transition from Vagrant</a></li>
    </ul>
</li>
<li><a href="#setup-docker">Setup Docker</a>    
<li><a href="#setup-php-cli">Setting up the PHP cli container</a>
    <ul>
        <li><a href="#xdebug-php">Installing Xdebug in the PHP container</a></li>
        <li><a href="#dockerfile">Persisting image changes with a Dockerfile</a></li>
    </ul>
</li> 
<li><a href="#webstack">Setting up a web stack with php-fpm and nginx</a>
    <ul>
        <li><a href="#setup-nginx">Setting up nginx</a></li>
        <li><a href="#setup-php-fpm">Setting up php-fpm</a>
            <ul>
                <li><a href="#php-fpm-xdebug">Installing xdebug</a></li>
            </ul>
        </li> 
        <li><a href="#connecting-nginx-php-fpm">Connecting nginx and php-fpm</a></li>
    </ul>
</li> 
<li><a href="#docker-compose">Putting it all together: Meet docker-compose</a></li>
<li><a href="#tl-dr">The tl;dr</a></li>
</ul>

## <a id="introduction"></a>Introduction
### <a id="precondiction"></a>Preconditions
I'm assuming that you have installed [Git bash for Windows](https://git-scm.com/download/win). If not, please do that before, 
see [Setting up the software: Git and Git Bash](http://www.pascallandau.com/blog/phpstorm-with-vagrant-using-laravel-homestead-on-windows-10/#git-and-git-bash).

### <a id="why-use-docker"></a>Why use Docker?
I won't go into too much detail what Docker is and why you should use it, because 
[others](https://www.linode.com/docs/applications/containers/when-and-why-to-use-docker/) 
[have](https://www.zdnet.com/article/what-is-docker-and-why-is-it-so-darn-popular/)
[already](https://stackoverflow.com/questions/16647069/should-i-use-vagrant-or-docker-for-creating-an-isolated-environment)
talked about this extensively.

As for me, my main reasons were
- Symlinks in vagrant didn't work the way they should
- VMs become bloated and hard to manage over time
- Setup in the team involved a lot of work
- I wanted to learn Docker for quite some time because you hear a lot about it

In general, Docker is kind of like a virtual machine, so it allows us to develop in an OS of our choice (e.g. Windows) 
but run the code in the same environment as it will in production (e.g. on a linux server). Thanks to its core principles, 
it makes the separation of services really easy (e.g. having a dedicated server for your database) which - again - 
is something that should happen on production anyway.

### <a id="transition-vagrant"></a>Transition from Vagrant
On Windows, you can either use the [Docker Toolbox](https://docs.docker.com/toolbox/toolbox_install_windows/) 
(which is essentially a VM with Docker setup on it) or the Hyper-V based [Docker for Windows](https://www.docker.com/docker-windows). 
This tutorial will only look at the latter.

**A word of caution**: Unfortunately, we cannot have other Gods besides Docker (on Windows). 
The native Docker client requires Hyper-V to be activated which in turn will cause Virtualbox to not work any longer. 
Thus, we will not be able to use Vagrant and Docker alongside each other. 
This was actually the main reason it took me so long to start working with Docker.

## <a id="setup-docker"></a>Setup Docker
First, [download Docker for Windows](https://store.docker.com/editions/community/docker-ce-desktop-windows)
(requires Microsoft Windows 10 Professional or Enterprise 64-bit). The version I am using in this tutorial is `18.03.1-ce-win65`.
During the installation, 
leave the option "Use Windows Containers instead of Linux containers" unticked as we intend to develop on linux containers 
(you can change it later anyway).

[![Install docker](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/use-linux-containers.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/use-linux-containers.PNG)

After the installation finishes, we need to log out of Windows and in again. 
Docker should start automatically. If not, there should be a "Docker for Windows" icon placed on your desktop.
If Hyper-V is not activated yet, Docker will automatically urge you to do so now.

[![Activate Hype-V](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/enable-hyper-v-and-containers.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/enable-hyper-v-and-containers.PNG)

If you agree, Hyper-V and container features are activated and a reboot is initiated. 
See [Install Hyper-V on Windows 10](https://docs.microsoft.com/en-us/virtualization/hyper-v-on-windows/quick-start/enable-hyper-v)
to deactivate it again.

**Caution**: VirtualBox will stop working afterwards! Starting one of my previous machines from the VirtualBox interface 
or via `vagrant up` fails with the error message

> VT-x is not available (VERR_VMX_NO_VMX)

[![Virtual box error](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/virtual-box-error.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/virtual-box-error.PNG)
[![Vagrant error](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/vagrant-error.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/vagrant-error.PNG)

After rebooting, Docker will start automatically and a welcome screen appears.
 
[![Docker welcome screen](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/welcome-screen.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/welcome-screen.PNG)

We can ignore that (close the window). 
In addition, a new icon is added to your system tray. A right-click reveals the context menu. 

[![Docker settings in system tray](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/system-tray-icon.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/system-tray-icon.PNG)

Open the tab "Shared Devices" and tick the hard drives on your host machine that you want to share with Docker containers. 

_Note: We will still need to define explicit path mappings for the actual containers later on, but the hard drive that the path belongs 
to must be made available here. After clicking "Apply", you will be prompted for your credentials_

[![Docker settings: Shared devices](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/settings-shared-drives.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/settings-shared-drives.PNG)
[![Docker settings: Credential prompt](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/settings-shared-drives-credentials.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/settings-shared-drives-credentials.PNG)

Next, open tab "Advanced". You don't actually have to change any of the settings but if you (like me) 
don't have `C:/` set up as you biggest partition, you might want to change the "Disk image location". 
I'm putting mine at `C:\Hyper-V\Virtual Hard Disks\MobyLinuxVM.vhdx`. It might take some minutes for Docker to process the changes.

Docker "physically" stores the container images in that location.

[![Docker settings: Advanced](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/settings-advanced-disk-image-location.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/install-docker/settings-advanced-disk-image-location.PNG)

Congratulations, Docker is now set up on your machine 😊

## <a id="setup-php-cli"></a>Setting up the PHP cli container 
Now that we have the general stuff out of the way, let's set up our first container. 
I've created the directory `C:/codebase/docker-php/` and will run the remaining examples in there. 

Firstly, lets create a directory for our sourcecode:
```
mkdir -p "C:/codebase/docker-php/app"
```

For the sake of simplicity, we will stick to the [official PHP image](https://hub.docker.com/_/php/) and run:

```
docker run -d --name docker-php -v "C:/codebase/docker-php/app":/var/www php:7.0-cli
```

Which means:

```
docker run                               // run a container
-d                                       // in the background (detached)
--name docker-php                        // named docker-php
-v "C:/codebase/docker-php/app":/var/www // sync the directory C:/codebase/docker-php/app on the 
                                         // windows host with /var/www in the container
php:7.0-cli                              // use this image to build the container
```

The result looks something like this:
```
$ docker run -d --name docker-php -v "C:/codebase/docker-php/app":/var/www php:7.0-cli
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

Weird. For some reason, we don't see our newly created container there. Let's check with the `-a` flag to list **all** containers, 
even the ones that are not running.

````
$ docker ps -a
CONTAINER ID    	IMAGE           	COMMAND              	CREATED         	STATUS                  	PORTS           	NAMES
56af890e1a61    	php:7.0-cli     	"docker-php-entrypoi…"   27 seconds ago  	Exited (0) 25 seconds ago                   	docker-php
````

Aha. So the container was created, but immediately stopped (see `Created 27 seconds ago; Exited (0) 25 seconds ago`). 
That's because a container only [lives as long as it's main process is running](https://stackoverflow.com/a/28214133/413531).
According to [the docs](https://docs.docker.com/config/containers/multi-service_container/),
 
> A container's main running process is the ENTRYPOINT and/or CMD at the end of the Dockerfile." 

[This answer explains the difference between CMD and ENTRYPOINT](https://stackoverflow.com/a/21564990/413531) quite well. 
Since we don't have a Dockerfile defined, we would need to look at the 
[Dockerfile of the base image](https://github.com/docker-library/php/blob/27c65bbd606d1745765b89bf43f39b06efad1e43/7.0/stretch/cli/Dockerfile) 
we're using, but I actually don't wanna go down this rabbit hole for now. Basically, the "problem" is, that the
container doesn't have a long running process / service defined, (as the php-fpm or the nginx containers do later on).
To keep the container alive, we need to add the `-i` flag to the `docker run` command:
````
docker run -di --name docker-php -v "C:/codebase/docker-php/app":/var/www php:7.0-cli
````

But then this happens:
````
Pascal@Landau-Laptop MINGW64 /
$ docker run -di --name docker-php -v "C:/codebase/docker-php/app":/var/www php:7.0-cli
C:\Program Files\Docker\Docker\Resources\bin\docker.exe: Error response from daemon: Conflict. The container name "/docker-php" is already in use by container "56af890e1a61f8ffa5528b040756dc62a94c0b929c29df82b9bf5dec6255321f". You have to remove (or rename) that container to be able to reuse that name.
See 'C:\Program Files\Docker\Docker\Resources\bin\docker.exe run --help'.
````
Apparently, we cannot use the same name (`docker-php`) again. Bummer. So, let's remove the previous container first via 
````
docker rm docker-php
````
and try again afterwards:

````
Pascal@Landau-Laptop MINGW64 /
$ docker rm docker-php
docker-php

Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
docker run -di --name docker-php -v "C:/codebase/docker-php/app":/var/www php:7.0-cli
7b3024a542a2d25fd36cef96f4ea689ec7ebb758818758300097a7be3ad2c2f6

Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker ps
CONTAINER ID    	IMAGE           	COMMAND              	CREATED         	STATUS          	PORTS           	NAMES
7b3024a542a2    	php:7.0-cli     	"docker-php-entrypoi…"   5 seconds ago   	Up 4 seconds                        	docker-php
````

Sweet, so now that the container is up and running, let's "[log in](https://stackoverflow.com/a/30173220)" via 
````
docker exec -it docker-php bash
````

You might get the following error message

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker exec -it docker-php bash
the input device is not a TTY.  If you are using mintty, try prefixing the command with 'winpty'
````

If so, prefixing the command with `winpty` should help:

````
winpty docker exec -it docker-php bash
````

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
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

Remember the path mapping, that we specified? Let's create a simple "hello world" script **on the windows 10 host machine** 
at `C:\codebase\docker-php\app\hello-world.php` to make sure it works:

````
cd "C:\codebase\docker-php\app"
echo '<?php echo "Hello World (php)\n"; ?>' > hello-world.php
````

Should look like this on the host machine:

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
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

Let's run the script **in the container** via 
````
php /var/www/hello-world.php
````

````
root@7b3024a542a2:/# php /var/www/hello-world.php
Hello World
````

Purrfect. We created the file on our host system and it's automatically available in the container. 

### <a id="xdebug-php"></a>Installing Xdebug in the PHP container
Since we intend to use Docker for our local development setup, the ability to debug is mandatory. So let's extend our image with the xdebug extension.
The readme of the official Docker PHP repository does a good job at explaining 
[how to install extensions](https://github.com/docker-library/docs/blob/master/php/README.md#how-to-install-more-php-extensions). 
For xdebug, we'll use PECL. To install the extension, make sure to be logged into the container and run 
````
pecl install xdebug-2.6.0
````

You should see an output like this:

````
root@7b3024a542a2:/# pecl install xdebug-2.6.0
[...]
Build process completed successfully
Installing '/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so'
install ok: channel://pecl.php.net/xdebug-2.6.0
configuration option "php_ini" is not set to php.ini location
You should add "zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so" to php.ini
````

The xdebug extension has been build and saved in `/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so`. 
To actually activate it, run 
````
docker-php-ext-enable xdebug
````

That helper command will place the file `docker-php-ext-xdebug.ini` in the directory for additional php ini files with the content

````
zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so
````
which enables the extension. Btw. you can locate the additional php ini files folder by running 

````
php -i | grep "additional .ini"
````

Result:
````
root@7b3024a542a2:/# php -i | grep "additional .ini"
Scan this dir for additional .ini files => /usr/local/etc/php/conf.d
````

When we check the contents of that folder, we will indeed find the `xdebug.ini` file with the before mentioned content and `php -m` reveals, 
that xdebug is actually active.

````
root@7b3024a542a2:/# ls -alh /usr/local/etc/php/conf.d
total 12K
drwxr-sr-x 1 root staff 4.0K May 28 13:30 .
drwxr-sr-x 1 root staff 4.0K Apr 30 20:34 ..
-rw-r--r-- 1 root staff   81 May 28 13:30 docker-php-ext-xdebug.ini
root@7b3024a542a2:/# cat /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so
root@7b3024a542a2:/# php -m | grep xdebug
xdebug
````

Now we'll log out of the container (type "exit" or hit `CTRL` +`D`) and stop the container via 
````
docker stop docker-php
````

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker stop docker-php
docker-php

Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker ps -a
CONTAINER ID    	IMAGE           	COMMAND              	CREATED         	STATUS                   	PORTS           	NAMES
7b3024a542a2    	php:7.0-cli     	"docker-php-entrypoi…"   2 hours ago     	Exited (137) 7 seconds ago                   	docker-php
````

Now we start the container again via 

````
docker start docker-php
````

log back in and check if xdebug is still there:

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker start docker-php
docker-php

Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ winpty docker exec -it docker-php bash
root@7b3024a542a2:/# php -m | grep xdebug
xdebug
````

And... it is! So the changes we made "survived" a restart of the container. But: They won't survive a "rebuild" of the container.
First we stop and remove the container via 
````
docker rm -f docker-php
````

The `-f` flag forces the container to stop. Otherwise we would need an additional `docker stop docker-php` before.

Then we rebuild it, log in

````
docker run -di --name docker-php -v "C:/codebase/docker-php/":/codebase php:7.0-cli
inpty docker exec -it docker-php bash
````

and check for xdebug:
````
php -m | grep xdebug
````
... which won't be there anymore.

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker rm -f docker-php
docker-php

Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker run -di --name docker-php -v "C:/codebase/docker-php/":/codebase php:7.0-cli
1da17524418f5327760eb113904b7ceec30f22b41e4b4bd77f9fa2f7b92b4ead

Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ winpty docker exec -it docker-php bash
root@1da17524418f:/# php -m | grep xdebug
root@1da17524418f:/#
````

Note the new container ID (before: `7b3024a542a2`; now: `1da17524418f`) and that `php -m | grep xdebug` doesn't yield anything.

### <a id="dockerfile"></a>Persisting image changes with a Dockerfile
Simply put, a [Dockerfile](https://docs.docker.com/engine/reference/builder/) describes the changes we make to a base image, 
so we (and everybody else) can easily recreate the same environment. In our case, 
we need to define the PHP base image that we used as well as instructions for installing and enabling xdebug.
To clearly separate infrastructure from code, we'll create a new directory at `C:/codebase/docker-php/php-cli/`. 
Create a file named `Dockerfile` in this directory

````
mkdir "C:/codebase/docker-php/php-cli/"
touch "C:/codebase/docker-php/php-cli/Dockerfile"
````

and give it the following content:

````
FROM php:7.0-cli
RUN pecl install xdebug-2.6.0 \
	&& docker-php-ext-enable xdebug
````

Change to the `C:/codebase/docker-php/php-cli/` directory and build the image based on that Dockerfile

````
cd "C:/codebase/docker-php/php-cli/"
docker build -t docker-php-image -f Dockerfile .
````

The `-f Dockerfile` is actually optional as this is the default anyway. "docker-php-image" is the name of our new image. 

If you encounter the following error 
````
"docker build" requires exactly 1 argument.
See 'docker build --help'.

Usage:  docker build [OPTIONS] PATH | URL | - [flags]

Build an image from a Dockerfile
````

you probably missed the trailing `.` at the end of `docker build -t docker-php-image -f Dockerfile .` ;)

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-cli
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
````

Note, that the building takes longer than before, because Docker now needs to do the extra work of installing xdebug. 
Instead of using the base `php:7.0-cli` image, we'll now use our new, shiny `docker-php-image` image to start the container and check for xdebug.

````
docker run -di --name docker-php -v "C:/codebase/docker-php/app":/var/www docker-php-image
````

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-cli
$ docker run -di --name docker-php -v "C:/codebase/docker-php/app":/var/www docker-php-image
C:\Program Files\Docker\Docker\Resources\bin\docker.exe: Error response from daemon: Conflict. The container name "/docker-php" is already in use by container "2e84cb536fc573142a9951331b16393e3028d9c6eff87f89cfda682279634a2b". You have to remove (or rename) that container to be able to reuse that name.
See 'C:\Program Files\Docker\Docker\Resources\bin\docker.exe run --help'.
````

Aaaand we get an error, because we tried to use the same name ("docker-php"), that we used for the previous, still running container.
Sigh.. fortunately we already know how to solve that via

````
docker rm -f docker-php
````

Retry 

````
docker run -di --name docker-php -v "C:/codebase/docker-php/app":/var/www docker-php-image
````

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-cli
$ docker rm -f docker-php
docker-php

Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-cli
$ docker run -di --name docker-php -v "C:/codebase/docker-php/app":/var/www docker-php-image
f27cc1310c836b15b7062e1fd381f283250a85133fb379c4cf1f891dec63770b

Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-cli
$ winpty docker exec -it docker-php bash
root@f27cc1310c83:/# php -m | grep xdebug
xdebug
````

Yep, all good. Btw. since we "only" want to check if xdebug was installed, we could also simply pass `-m` to the `docker run` command:

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-cli
$ docker run docker-php-image php -m | grep xdebug
xdebug
````

Be aware that this will create a new container every time it's run (, note the first entry with the wonderful name "distracted_mclean"):

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-cli
$ docker ps -a
CONTAINER ID    	IMAGE           	COMMAND              	CREATED         	STATUS                   	PORTS           	NAMES
abc9fec8a88b    	docker-php-image	"docker-php-entrypoi…"   4 minutes ago   	Exited (0) 4 minutes ago                     	distracted_mclean
f27cc1310c83    	docker-php-image	"docker-php-entrypoi…"   10 minutes ago  	Exited (137) 6 minutes ago                   	docker-php
````

Before we move on, let's []stop and remove all containers via](https://coderwall.com/p/ewk0mq/stop-remove-all-docker-containers).
````
docker rm -f $(docker ps -aq)
````

The `$(docker ps -aq)` part returns only the numeric ids of all containers and passes them to the `docker rm -f` command.

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-cli
$ docker rm -f $(docker ps -aq)
abc9fec8a88b
f27cc1310c83
````

## <a id="webstack"></a>Setting up a web stack with php-fpm and nginx
Since most people are probably not only working on CLI scripts but rather on web pages, 
the next step in this tutorial is about setting up an nginx web server and connect it to php-fpm.

### <a id="setup-nginx"></a>Setting up nginx
We're gonna use the [official nginx image](https://hub.docker.com/_/nginx/) and since we don't know anything about that image yet, 
let's run and explore it a bit:

````
docker run -di nginx:latest
````

yields

````
Pascal@Landau-Laptop MINGW64 /
$ docker run -di nginx:latest
Unable to find image 'nginx:latest' locally
latest: Pulling from library/nginx
[...]
Status: Downloaded newer image for nginx:latest
15c6b8d8a2bff873f353d24dc9c40d3008da9396029b3f1d9db7caeebedd3f50
````

Note that we only used the minimum number of arguments here. Since we did not specify a name, we will simply use the ID instead to log in
(so be sure to use the one that your shell returned - don't just copy the line below :P)

````
$ winpty docker exec -it 15c6b8d8a2bff873f353d24dc9c40d3008da9396029b3f1d9db7caeebedd3f50 bash
root@15c6b8d8a2bf:/#
````

We would expect that there is an nginx process running, but upon checking with `ps aux` we get 
````
bash: ps: command not found" as a response. 
````
 
 This is common when using docker images, because they are usually kept as minimal as possible. 
 Although this is a good practice in production, it is kind of cumbersome in development. 
 So, let's install `ps` via 
````
apt-get update && apt-get install -y procps
````

and try again:

````
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
````

Ah. Much better. Lets dig a little deeper and see how the process is configured via `nginx -V`

````
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
````

Sweet, so the configuration file is placed in the default location at `/etc/nginx/nginx.conf` 
(see `--conf-path=/etc/nginx/nginx.conf`). Checking that file will show us, where we need to place additional config files 
(e.g. for the configuration of our web site). Run

````
cat /etc/nginx/nginx.conf
````

... and see

````
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
````

Note the line `include /etc/nginx/conf.d/*.conf` at the end of the file. In this directory, we'll find the default nginx config:

````
ls -alh /etc/nginx/conf.d/
cat /etc/nginx/conf.d/default.conf
````

````
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
````

So the server is listening on port 80. Unfortunately, we cannot reach the web server from our windows host machine, 
as there is currently (2018-05-31) an [open bug for accessing container IPs from a windows host](https://github.com/docker/for-win/issues/221) 
(don't worry, we'll fix that with port mappings in a second)). 
So, in order to verify that the server is actually  working, we'll install `curl` inside the nginx container and fetch `127.0.0.1:80`:

````
apt-get install curl -y
curl localhost:80
````

Looks like this:

````
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
````

Looks good! Now let's customize some stuff:
- point the root to `/var/www`
- place a "Hello world" index file in `/var/www/index.html`

````
sed -i "s#/usr/share/nginx/html#/var/www#" /etc/nginx/conf.d/default.conf
mkdir -p /var/www
echo "Hello world!" > /var/www/index.html
````

To make the changes become effective, we need to [reload nginx](http://nginx.org/en/docs/beginners_guide.html#control) via 

````
nginx -s reload
````

````
root@15c6b8d8a2bf:/# nginx -s reload
2018/05/29 09:22:54 [notice] 351#351: signal process started
````

Check with curl, et voilá:

````
root@15c6b8d8a2bf:/# curl 127.0.0.1:80
Hello world!
````

With all that new information we can set up our nginx image with the following folder structure on the host machine:

````
C:\codebase\docker-php
+ nginx\
  + conf.d\
    - site.conf
  - Dockerfile
+ app\
  - index.html
  - hello-world.php
````

`nginx\Dockerfile`
````
FROM nginx:latest
````

`nginx\conf.d\site.conf`
````
server {
    listen      80;
    server_name localhost;
    root        /var/www;
}
````

`app\index.html`
````
Hello World
````

Clean up the "exploration" nginx container, `cd` into `/c/codebase/docker-php/nginx` and build the new image: 

````
docker rm -f $(docker ps -aq)
cd /c/codebase/docker-php/nginx
docker build -t docker-nginx-image .
````

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker rm -f $(docker ps -aq)
15c6b8d8a2bf
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ cd nginx
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/nginx
$ docker build -t docker-nginx-image .
Sending build context to Docker daemon  3.584kB
Step 1/1 : FROM nginx:latest
 ---> ae513a47849c
Successfully built ae513a47849c
Successfully tagged docker-nginx-image:latest
SECURITY WARNING: You are building a Docker image from Windows against a non-Windows Docker host. All files and directories added to build context will have '-rwxr-xr-x' permissions. It is recommended to double check and reset permissions for sensitive files and directories.
````

And then run the "new" container via 

````
docker run -di --name docker-nginx -p 8080:80 -v "C:\codebase\docker-php\nginx\conf.d":/etc/nginx/conf.d/ -v "C:\codebase\docker-php\app":/var/www docker-nginx-image
````

where
````
-p 8080:80                                                  // maps port 8080 on the windows host to port 80 in the container
-v "C:\codebase\docker-php\nginx\conf.d":/etc/nginx/conf.d/ // mounts the conf.d folder on the host to the correct directory in the container
-v "C:\codebase\docker-php\app":/var/www                    // mounts the "code" directory in the correct place
````

Thanks to the port mapping we can now simply open http://127.0.0.1:8080/ in a browser on the host machine 
and see the content of our `app\index.html` file.

[![nginx index file](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/webstack/hello-world-nginx.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/webstack/hello-world-nginx.PNG)

If you want some more information about running nginx on Docker, check out 
[this tutorial](https://www.digitalocean.com/community/tutorials/how-to-run-nginx-in-a-docker-container-on-ubuntu-14-04).

Before we move on, let's clean up

````
docker stop docker-nginx
````

### <a id="setup-php-fpm"></a>Setting up php-fpm
We are already familiar with the official docker PHP image but have only used the cli-only version so far. 
FPM ones can be pulled in by using the `-fpm` tags (e.g. like `php:7.0-fpm`).
As with nginx, let's explore the php-fpm image first:

````
docker run -di --name php-fpm-test php:7.0-fpm
````

The first thing to note is, that the image automatically exposes port 9000 as a `docker ps` reveals:

`````
$ docker ps
CONTAINER ID        IMAGE               COMMAND                  CREATED             STATUS                      PORTS                  NAMES
c5d23b694563        php:7.0-fpm         "docker-php-entrypoi…"   4 hours ago         Up 4 hours                  9000/tcp               php-fpm-test
`````

When we examine the Dockerfile that was used to build the image 
(click [here](https://hub.docker.com/r/library/php/) and search for the "7.0-fpm" tag 
that currently (2018-05-31) links [here](https://github.com/docker-library/php/blob/27c65bbd606d1745765b89bf43f39b06efad1e43/7.0/stretch/fpm/Dockerfile)), 
we can see that it contains an `EXPOSE 9000` at the bottom.

What else we can we figure out...

````
winpty docker exec -it php-fpm-test bash
````

First, will check where the configuration files are located via `php-fpm -i | grep config`:

````
root@c5d23b694563:/var/www/html# php-fpm -i | grep config
Configure Command =>  './configure'  '--build=x86_64-linux-gnu' '--with-config-file-path=/usr/local/etc/php' '--with-config-file-scan-dir=/usr/local/etc/php/conf.d' '--enable-option-checking=fatal' '--disable-c
gi' '--with-mhash' '--enable-ftp' '--enable-mbstring' '--enable-mysqlnd' '--with-curl' '--with-libedit' '--with-openssl' '--with-zlib' '--with-libdir=lib/x86_64-linux-gnu' '--enable-fpm' '--with-fpm-user=www-da
ta' '--with-fpm-group=www-data' 'build_alias=x86_64-linux-gnu'
fpm.config => no value => no value
[...]

````
`--with-config-file-path=/usr/local/etc/php` is our suspect. So it is very likely, 
that we will find the [global directives config file](https://myjeeva.com/php-fpm-configuration-101.html#global-directives) at 
`/usr/local/etc/php-fpm.conf` (unfortunately, we cannot resolve the location directly). 
`grep`'ing this file for `include=` reveals the location for the 
[pool directives config](https://myjeeva.com/php-fpm-configuration-101.html#pool-directives):

````
grep "include=" /usr/local/etc/php-fpm.conf
````

````
root@c5d23b694563:/var/www/html# grep "include=" /usr/local/etc/php-fpm.conf
include=etc/php-fpm.d/*.conf
````

Hm - a relative path. That looks kinda odd? Let's get a little more context with the `-C` option for `grep`:

````
grep -C 6 "include=" /usr/local/etc/php-fpm.conf
````

````
root@c5d23b694563:/var/www/html# grep -C 6 "include=" /usr/local/etc/php-fpm.conf
; Include one or more files. If glob(3) exists, it is used to include a bunch of
; files from a glob(3) pattern. This directive can be used everywhere in the
; file.
; Relative path can also be used. They will be prefixed by:
;  - the global prefix if it's been set (-p argument)
;  - /usr/local otherwise
include=etc/php-fpm.d/*.conf
````

Ah - that makes more sense. So we need to resolve `etc/php-fpm.d/*.conf` relative to `/usr/local`. 
Resulting in `/usr/local/etc/php-fpm.d/*.conf` (usually you'll at least find a `www.conf` file in there). 
The pool config determines amongst other things how php-fpm listens for connections (e.g. via Unix socket or via TCP IP:port).

````
cat /usr/local/etc/php-fpm.d/www.conf
````

````
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
````

php-fpm ist listening on port 9000 on 127.0.0.1 (localhost). So it makes total sense to expose port 9000.

#### <a id="php-fpm-xdebug"></a>Installing xdebug
Since we probably also want to debug php-fpm, xdebug needs to be setup as well. The process is pretty much the same as for the cli image:

````
pecl install xdebug-2.6.0
docker-php-ext-enable xdebug
php-fpm -m | grep xdebug
````

Of course we'll also put that in its own Dockerfile:

````
C:\codebase\docker-php
+ php-fpm\
  - Dockerfile
````

`php-fpm\Dockerfile`
````
FROM php:7.0-fpm
RUN pecl install xdebug-2.6.0 \
    && docker-php-ext-enable xdebug
````

Clean up the test container and build the new image
````
docker rm -f php-fpm-test
cd /c/codebase/docker-php/php-fpm
docker build -t docker-php-fpm-image .
````

### <a id="connecting-nginx-php-fpm"></a>Connecting nginx and php-fpm
Now that we have containers for nginx and php-fpm, we need to connect them. 
To do so, we have to make sure that both containers are in the same network and can talk to each other
([which is a common problem](https://stackoverflow.com/questions/29905953/how-to-correctly-link-php-fpm-and-nginx-docker-containers)). 
Docker provides so called 
[user defined bridge networks](https://docs.docker.com/network/network-tutorial-standalone/#use-user-defined-bridge-networks) 
allowing **automatic service discovery**. That basically means, 
that our nginx container can use _the name_ of the php-fpm container to connect to it. 
Otherwise we would have to figure out the containers _IP address_ in the default network every time we start the containers.

````
docker network ls
````
 

reveals a list of the current networks

````
Pascal@Landau-Laptop MINGW64 /
$ docker network ls
NETWORK ID          NAME                DRIVER              SCOPE
7019b0b37ba7        bridge              bridge              local
3820ad97cc92        host                host                local
03fecefbe8c9        none                null                loca
````

Now let's add a new one called `web-network` for our web stack via 

````
docker network create --driver bridge web-network
````

````
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
````

Start the nginx container and connect it to the new network via

````
docker start docker-nginx
docker network connect web-network docker-nginx
````

Finally, we need to mount the local code folder `app\` we mounted to the nginx container at `/var/www`
also in the php-fpm container in the same location:

````
docker run -di --name docker-php-fpm -v "C:\codebase\docker-php\app":/var/www --network web-network docker-php-fpm-image
````

Note that we specified the network in the run command via the `--network` option.
We can verify that both containers are connected to the `web-network` by running 
````
docker network inspect web-network
````

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-fpm
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
````

The "Containers" key reveals that the `docker-php-fpm` container has the IP address 172.18.0.3 
and the docker-nginx container is reachable via 172.18.0.2. 
But can we actually connect from nginx to php-fpm? Let's find out:

Log into the nginx container 
````
winpty docker exec -ti docker-nginx bash
````
and ping the IP
````
ping 172.18.0.3 -c 2
````

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php/php-fpm
$ winpty docker exec -ti docker-nginx bash
root@eaa5c0594278:/# ping 172.18.0.3 -c 2
bash: ping: command not found
````

.. well, after we make the command available by installing `iputils-ping`:

````
apt-get update && apt-get install iputils-ping -y
ping 172.18.0.3 -c 2
````

````
root@eaa5c0594278:/# apt-get update && apt-get install iputils-ping -y
root@eaa5c0594278:/# ping 172.18.0.3 -c 2
PING 172.18.0.3 (172.18.0.3) 56(84) bytes of data.
64 bytes from 172.18.0.3: icmp_seq=1 ttl=64 time=0.142 ms
64 bytes from 172.18.0.3: icmp_seq=2 ttl=64 time=0.162 ms

--- 172.18.0.3 ping statistics ---
2 packets transmitted, 2 received, 0% packet loss, time 1071ms
rtt min/avg/max/mdev = 0.142/0.152/0.162/0.010 ms
````

We can ping the container - that's good. But we were also promised we could reach the container by its name `docker-php-fpm`:
````
ping docker-php-fpm -c 2
````

````
root@eaa5c0594278:/# ping docker-php-fpm -c 2
PING docker-php-fpm (172.18.0.3) 56(84) bytes of data.
64 bytes from docker-php-fpm.web-network (172.18.0.3): icmp_seq=1 ttl=64 time=0.080 ms
64 bytes from docker-php-fpm.web-network (172.18.0.3): icmp_seq=2 ttl=64 time=0.131 ms

--- docker-php-fpm ping statistics ---
2 packets transmitted, 2 received, 0% packet loss, time 1045ms
rtt min/avg/max/mdev = 0.080/0.105/0.131/0.027 ms
````

And we can - awesome! Now we need to tell nginx to pass all PHP related requests to php-fpm by changing the 
`nginx\conf.d\site.conf` file on our windows host to 

````
server {
    listen      80;
    server_name localhost;
    root        /var/www;
	
   location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass docker-php-fpm:9000;
        include fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
````

Note the `fastcgi_pass docker-php-fpm:9000;` line that tells nginx how to reach our php-fpm service.
Because we mounted the `nginx\conf.d` folder, we just need to reload nginx:

````
nginx -s reload
````

and open http://127.0.0.1:8080/hello-world.php on a browser on your host machine.

[![php-fpm hello world](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/webstack/hello-world-php-fpm.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/webstack/hello-world-php-fpm.PNG)

Btw. there's also a good tutorial on geekyplatypus.com on how to 
[Dockerise your PHP application with Nginx and PHP7-FPM](http://geekyplatypus.com/dockerise-your-php-application-with-nginx-and-php7-fpm/).
But since it's using docker-compose you might want to read the next chapter first :)

## <a id="docker-compose"></a>Putting it all together: Meet docker-compose
Lets sum up what we have do now to get everything up and running:
1. start php-cli
2. start nginx
3. start php-fpm

````
docker run -di --name docker-php -v "C:\codebase\docker-php\app":/var/www --network web-network docker-php-image
docker run -di --name docker-nginx -p 8080:80 -v "C:\codebase\docker-php\nginx\conf.d":/etc/nginx/conf.d/ -v "C:\codebase\docker-php\app":/var/www  --network web-network docker-nginx-image
docker run -di --name docker-php-fpm -v "C:\codebase\docker-php\app":/var/www --network web-network docker-php-fpm-image
````

Hm. That's alright I guess... but it also feels like "a lot". Wouldn't it be much better to have everything neatly defined in one place? 
I bet so! Let me introduce you to [docker-compose](https://docs.docker.com/compose/)

> Compose is a tool for defining and running multi-container Docker applications. 
> With Compose, you use a YAML file to configure your application's services. 
> Then, with a single command, you create and start all the services from your configuration.

Lets do this step by step, starting with the php-cli container. Create the file `C:\codebase\docker-php\docker-compose.yml`:

````
# tell docker what version of the docker-compose.yml we're using
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
````

Before we get started, we're gonna clean up the old containers:

````
docker rm -f $(docker ps -aq)
````

To test the docker-compose.yml we need to run `docker-compose up -d` from `C:\codebase\docker-php`

````
cd "C:\codebase\docker-php"
docker-compose up -d
````


````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
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
````

Note that the image is build from scratch when we run `docker-compose up` for the first time. 
A `docker ps -a` shows that the container is running fine, we can log in and execute source code from the host machine.

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker ps -a
CONTAINER ID        IMAGE                       COMMAND                  CREATED             STATUS              PORTS               NAMES
adf794f27315        docker-php_docker-php-cli   "docker-php-entrypoi…"   3 minutes ago       Up 2 minutes                            docker-php_docker-php-cli_1
````

Logging in
````
winpty docker exec -it docker-php_docker-php-cli_1 bash
````
and running 
````
php /var/www/hello-world.php
````

works as before

````
root@adf794f27315:/# php /var/www/hello-world.php
Hello World (php)
````

Now log out of the container and run 

````
docker-compose down 
````

to shut the container down again:

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
$ docker-compose down
Stopping docker-php_docker-php-cli_1 ... done
Removing docker-php_docker-php-cli_1 ... done
Removing network docker-php_web-network
````

Add the remaining services to the `docker-compose.yml` file:

````
# tell docker what version of the docker-compose.yml we're using
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
    tty: true
    volumes:
      - ./app:/var/www
      - ./nginx/conf.C:/etc/nginx/conf.d
    networks:
      - web-network

  docker-php-fpm:
    build: 
      context: ./php-fpm
    tty: true
    volumes:
      - ./app:/var/www
    networks:
      - web-network
````

And up again...

````
docker-compose up -d
````

````
Pascal@Landau-Laptop MINGW64 /c/codebase/docker-php
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
````

Only nginx and php-fpm needed to be built because the php-cli one already existed. 
Lets check if we can still open http://127.0.0.1:8080/hello-world.php in a browser on the host machine:

[![php-fpm hello world](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/webstack/hello-world-php-fpm.PNG)](/img/php-php-fpm-and-nginx-on-docker-in-windows-10/webstack/hello-world-php-fpm.PNG)

Yes we can! So instead of needing to run 3 different command with a bunch of parameters we're now down to 
`docker-compose up -d`. Looks like an improvement to me ;)

## <a id="tl-dr"></a>The tl;dr
The whole article is a lot to take in and it is most likely not the most efficient approach when you "just want to get started".
So in this section we'll boil it down to only the necessary steps without in depth explanations.

- [Download Docker for Windows](https://store.docker.com/editions/community/docker-ce-desktop-windows)
- [Install Docker](#setup-docker)
  - activate Hyper-V (Virtual Box will stop working) 
  - enable Disk Sharing in the settings
- Set up the following folder structure
    ````
    C:\codebase\docker-php
    + nginx\
      + conf.d\
        - site.conf
      - Dockerfile
    + php-cli\
      - Dockerfile
    + php-fpm\
      - Dockerfile
    + app\
      - index.html
      - hello-world.html
    - docker-compose.yml
    ````
  - or simply `git clone git@github.com:paslandau/docker-php-tutorial.git docker-php && git checkout part_1`
- Open a shell at `C:\codebase\docker-php`
- run `docker-compose up -d`
- check in browser via
  - 127.0.0.1:8080
  - 127.0.0.1:8080/hello-world.php
- run `docker-compose down`
 
Your application code lives in the `app\` folder and changes are automatically available to the containers.
This setup denotes the end of the first tutorial. In the next part we will learn how to set up Docker in PHPStorm,
especially in combination with xdebug.