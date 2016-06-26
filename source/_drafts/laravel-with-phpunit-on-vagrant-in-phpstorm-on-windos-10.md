---
extends: _layouts.post
section: content
title: "How to setup PhpStorm with Vagrant and Laravel Homestead [Tutorial]"
subheading: "... on a Windows 10 machine. With PHPUnit. Plus Git and Git bash"
h1: "Setting up PhpStorm with Vagrant and Laravel Homestead"
description: "Step-by-Step tutorial for setting up a new PHPStorm project for a fresh Laravel installation running on a Homestead Vagrant box."
author: "Pascal Landau"
published_at: "2016-05-31 01:11:52"
vgwort: "9cf2ebaf25a5461f806db747de63335c"
slug: "phpstorm-vagrant-laravel-phpunit"
---

In this third part we're going to cover the setup of Vagrant as local development environment. In the end we'll learn how to
- install and configure VirtualBox, Vagrant and Laravel Homestead
- setup Vagrant in PhpStorm for (remote) PHP execution and debugging
- run PHPUnit unit tests via PhpStorm on Vagrant

And just as a reminder, the first part is over at 
[Setting up PHP7 with Xdebug 2.4 for PhpStorm](http://www.pascallandau.com/blog/php7-with-xdebug-2-4-for-phpstorm-on-windows-10/).


## The real deal: Setting up Laravel (with PHPUnit)
Now that we have the basics covered, you should have got a fairly decent understanding how vagrant and PhpStorm play together. But it's still all a little
hacky and doesn't feel "right". In this section I'll make up for that by explaining how I set up a completely fresh installation of Laravel (5.2) and configure
it to run on a homestead vagrant box for a "real world development" scenario.

### Install laravel/laravel
- create a new PhpStorm project via `File > New Project...` and name it "LaravelExample"
- open the PhpStorm terminal and run `composer create-project laravel/laravel tmp`. This will initialize a fresh 
  Laravel installation including dependencies. This isn't optimal, since we're doing this from our local machine and not
  from within the vagrant box we're using later on. This might be a problem when the local PHP setup is (vastly) different from
  the one in the vagrant box since the downloaded packages might differ. But on the other hand it's not really a big deal since we
  can just run composer update once the vagrant box is running (from within the box).

### Install laravel/homestead
- unfortunately, [composer cannot create a new project in an existing directory](https://github.com/composer/composer/issues/1135), so we
  need to copy the contents of "tmp" afterwards into the parent directory "LaravelExample" and delete the "tmp" directory manually.
- next, make sure the current working directory of the shell is the PhpStorm project folder
- run `composer require laravel/homestead --dev`. The [laravel/homestead](https://github.com/laravel/homestead) package gives a 
  more convenient way to deal with the setup of the vagrant homestead box it enables us the use an easier yaml syntax to
  define the properties we really need.
- run `vendor/bin/homestead make` (if that fails, run `vendor/bin/homestead.bat make` instead), which yields:
  ```
  $ vendor/bin/homestead make
  Homestead Installed!
  ```
  The command also places a `Homestead.yaml` file as well as a `Vagrantfile` in the project directory. 
  [![Project folder after running vendor/bin/homestead make](/img/phpstorm-with-vagrant-using-laravel-homestead-on-windows-10/laravel/homestead-make.PNG)](/img/phpstorm-with-vagrant-using-laravel-homestead-on-windows-10/laravel/homestead-make.PNG)
  Technically, that would be all you need to do,
  because everything is configured to work out of the box (e.g. the configuration of shared folders), but I would like to go over some details
  just to make sure it is clear whats going on here.
- open the `Homestead.yaml` file which should look similar to this:
  ```
  ---
  ip: "192.168.10.10"
  memory: 2048
  cpus: 1
  hostname: laravelexample
  name: laravelexample
  provider: virtualbox
  
  authorize: ~/.ssh/id_rsa.pub
  
  keys:
      - ~/.ssh/id_rsa
  
  folders:
      - map: "C:/Users/Pascal/PhpstormProjects/LaravelExample"
        to: "/home/vagrant/laravelexample"
  
  sites:
      - map: homestead.app
        to: "/home/vagrant/laravelexample/public"
  
  databases:
      - homestead
  ```
 There are a few things I would like to adjust:
  - make sure the `ip` is not already used in your local network
  - ``` 
      folders:
          - map: "C:/Users/Pascal/PhpstormProjects/LaravelExample"
            to: "/home/vagrant/laravelexample"
    ``` 
     - `map` should point to the absolute path to the repository on your **local** machine.
     - `to` denotes the path on your **vagrant machine** that is mapped to the above mentioned path on your local machine,
       so that you can access your local files within the vagrant box.
  - ``` 
      sites:
          - map: homestead.app
            to: "/home/vagrant/laravelexample/public"
    ``` 
     - `map: homestead.app` denotes the hostname that the nginx is looking for to serve content on
      you _should_ adjust that entry if you are going to have multiple projects (e.g. to laravelexample.app instead of homestead.app) 
      although it not strictly necessary since nginx will even respond to another hostname
     - `to: "/home/vagrant/laravelexample/public"` denotes the absolute path withing the vagrant box that the above mentioned hostname uses as lookup path for content.
      This should be the path to the `public` folder of the repository on your **vagrant machine**
  - if you already have an SSH key pair that is located in your home directory, you can leave the following lines in place:
    ```
      authorize: ~/.ssh/id_rsa.pub
      
      keys:
          - ~/.ssh/id_rsa
    ```
    Otherwise, you should delete them. They are responsible for a) making it possible to connect
    to the box by using your own ssh key and b) letting vagrant use your private key (which might come in handy
    if you need to open up an SSH tunnel for example - but that's for another story ;))
  - finally, to make your life a little easier, add `192.168.10.10 laravelexample.app` to your `host` file. 
    The default location on Windows is `C:\Windows\System32\drivers\etc`. You will probably need to copy the file
    to another location, edit it there and then copy it again to `C:\Windows\System32\drivers\etc`. The file should look like this:
      ```
      # Copyright (c) 1993-2009 Microsoft Corp.
      #
      # This is a sample HOSTS file used by Microsoft TCP/IP for Windows.
      #
      # This file contains the mappings of IP addresses to host names. Each
      # entry should be kept on an individual line. The IP address should
      # be placed in the first column followed by the corresponding host name.
      # The IP address and the host name should be separated by at least one
      # space.
      #
      # Additionally, comments (such as these) may be inserted on individual
      # lines or following the machine name denoted by a '#' symbol.
      #
      # For example:
      #
      #      102.54.94.97     rhino.acme.com          # source server
      #       38.25.63.10     x.acme.com              # x client host
      
      # localhost name resolution is handled within DNS itself.
      #	127.0.0.1       localhost
      #	::1             localhost
      
      192.168.10.10 laravelexample.app
      192.168.10.10 www.laravelexample.app
      ```
      This adjustment makes it possible to open a browser on your host machine and point it to `laravelexample.app`
      or `www.laravelexample.app` which will serve the content of your laravel installation _running within the vagrant box_.
- `Homestead.yaml` should now look like this:
    ```
    ---
    ip: "192.168.10.10"
    memory: 2048
    cpus: 1
    hostname: laravelexample
    name: laravelexample
    provider: virtualbox
  
    folders:
        - map: "C:/Users/Pascal/PhpstormProjects/LaravelExample"
          to: "/home/vagrant/laravelexample"
    
    sites:
        - map: homestead.app
          to: "/home/vagrant/laravelexample/public"
    
    databases:
        - homestead
    ```
- cool, now let's start vagrant via `vagrant up`:
  ```
  $ vagrant up
  Bringing machine 'default' up with 'virtualbox' provider...
  ==> default: Importing base box 'laravel/homestead'...
  ==> default: Matching MAC address for NAT networking...
  ==> default: Checking if box 'laravel/homestead' is up to date...
  ==> default: Setting the name of the VM: laravelexample
  ==> default: Fixed port collision for 22 => 2222. Now on port 2200.
  ==> default: Clearing any previously set network interfaces...
  ==> default: Preparing network interfaces based on configuration...
      default: Adapter 1: nat
      default: Adapter 2: hostonly
  ==> default: Forwarding ports...
      default: 80 => 8000 (adapter 1)
      default: 443 => 44300 (adapter 1)
      default: 3306 => 33060 (adapter 1)
      default: 5432 => 54320 (adapter 1)
      default: 22 => 2200 (adapter 1)
  ==> default: Running 'pre-boot' VM customizations...
  ==> default: Booting VM...
  ==> default: Waiting for machine to boot. This may take a few minutes...
      default: SSH address: 127.0.0.1:2200
      default: SSH username: vagrant
      default: SSH auth method: private key
      default: Warning: Connection timeout. Retrying...
      default:
      default: Vagrant insecure key detected. Vagrant will automatically replace
      default: this with a newly generated keypair for better security.
      default:
      default: Inserting generated public key within guest...
      default: Removing insecure key from the guest if it's present...
      default: Key inserted! Disconnecting and reconnecting using new SSH key...
  ==> default: Machine booted and ready!
  ==> default: Checking for guest additions in VM...
  ==> default: Setting hostname...
  ==> default: Configuring and enabling network interfaces...
  ==> default: Mounting shared folders...
      default: /vagrant => C:/Users/Pascal/PhpstormProjects/LaravelExample
      default: /home/vagrant/laravelexample => C:/Users/Pascal/PhpstormProjects/LaravelExample
  ==> default: Running provisioner: shell...
      default: Running: C:/Users/Pascal/AppData/Local/Temp/vagrant-shell20160627-11412-1e33p4n.sh
  ==> default: Running provisioner: shell...
      default: Running: C:/Users/Pascal/AppData/Local/Temp/vagrant-shell20160627-11412-bun3u6.sh
  ==> default: nginx stop/waiting
  ==> default: nginx start/running, process 2202
  ==> default: php7.0-fpm stop/waiting
  ==> default: php7.0-fpm start/running, process 2220
  ==> default: Running provisioner: shell...
      default: Running: C:/Users/Pascal/AppData/Local/Temp/vagrant-shell20160627-11412-8xmse3.sh
  ==> default: mysql:
  ==> default: [Warning] Using a password on the command line interface can be insecure.
  ==> default: Running provisioner: shell...
      default: Running: C:/Users/Pascal/AppData/Local/Temp/vagrant-shell20160627-11412-ct1lb2.sh
  ==> default: createdb: database creation failed: ERROR:  database "homestead" already exists
  ==> default: Running provisioner: shell...
      default: Running: C:/Users/Pascal/AppData/Local/Temp/vagrant-shell20160627-11412-lhjib5.sh
  ==> default: Running provisioner: shell...
      default: Running: inline script
  ==> default: You are running composer with xdebug enabled. This has a major impact on runtime performance. See https://getcomposer.org/xdebug
  ==> default: Updating to version 1.1.3.
  ==> default:     Downloading: Connecting...
  ==> default:
  ==> default:     Downloading: 100%
  ==> default:
  ==> default:
  ==> default: Use composer self-update --rollback to return to version 1.0.0
  ```
### Configure PhpStorm
- same as before...

#### Setup PHPUnit
- ...