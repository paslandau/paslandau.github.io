---
extends: _layouts.post
section: content
title: "How to setup Laravel with PhpUnit on Vagrant in PhpStorm [Tutorial]"
subheading: "... on a Windows 10 machine. With some workflow tips."
h1: "Setting up Laravel with PhpUnit on Vagrant in PhpStorm"
description: "Step-by-Step tutorial for setting up a fresh Laravel installation running on a Homestead Vagrant box."
author: "Pascal Landau"
published_at: "2016-07-17 01:11:52"
vgwort: "1e8a8793fe474eba988e3d25d7618127"
slug: "laravel-phpunit-vagrant-phpstorm"
---

In this third part we will set up a fresh Laravel installation and configure everything to run it on Vagrant, triggered by PhpStorm. 
That includes:
- install Laravel and Laravel Homestead
- configure Vagrant through Homestead
- enable Laravel-specific configurations in PhpStorm
- run PhpUnit unit tests via PhpStorm on Vagrant

And just as a reminder, the first part is over at 
[Setting up PHP7 with Xdebug 2.4 for PhpStorm](http://www.pascallandau.com/blog/php7-with-xdebug-2-4-for-phpstorm-on-windows-10/), the second
at [Setting up PhpStorm with Vagrant and Laravel Homestead](http://www.pascallandau.com/blog/phpstorm-with-vagrant-using-laravel-homestead-on-windows-10/).

## Setting up Laravel
Now that we have the basics covered, you should have got a fairly decent understanding how vagrant and PhpStorm play together. But it's still all a little
hacky and doesn't feel "right". In this section I'll make up for that by explaining how I set up a completely fresh installation of Laravel (5.2) and configure
it to run on a homestead vagrant box for a "real world development" scenario. For now let's assume, that we have no vagrant box configured 
(i.e. there is no virtual machine running, yet).

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
  more convenient way to deal with the setup of the vagrant homestead box as it enables us to use an easier yaml syntax to
  define the properties we really need.
- run `vendor/bin/homestead make` (if that fails, run `vendor/bin/homestead.bat make` instead), which yields:
  ```
  $ vendor/bin/homestead make
  Homestead Installed!
  ```
  The command also places a `Homestead.yaml` file as well as a `Vagrantfile` in the project directory. 
  [![Project folder after running vendor/bin/homestead make](/img/laravel-with-phpunit-on-vagrant-in-phpstorm-on-windows-10/laravel/homestead-make.PNG)](/img/laravel-with-phpunit-on-vagrant-in-phpstorm-on-windows-10/laravel/homestead-make.PNG)
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
  - make sure the `ip` (`192.168.10.10` in the example above) is not already used in your local network
  - ``` 
      folders:
          - map: "C:/Users/Pascal/PhpstormProjects/LaravelExample"
            to: "/home/vagrant/laravelexample"
    ``` 
     - `map` should point to the absolute path to the repository on your **local** (host) machine.
     - `to` denotes the path on your **vagrant** (remote) machine that is mapped to the above mentioned path on your local machine,
       so that you can access your local files within the vagrant box.
  - ``` 
      sites:
          - map: homestead.app
            to: "/home/vagrant/laravelexample/public"
    ``` 
     - `map: homestead.app` denotes the hostname that the nginx is looking for to serve content on
      you _should_ adjust that entry if you are going to have multiple projects (e.g. to laravelexample.app instead of homestead.app) 
      although it not strictly necessary since nginx will respond to other hostnames as well
     - `to: "/home/vagrant/laravelexample/public"` denotes the absolute path within the vagrant box that the above mentioned hostname uses as lookup path for content.
      This should be the path to the `public` folder of the repository on your **vagrant machine**
  - if you already have an SSH key pair that is located in the `.ssh` folder in your home directory, you can leave the following lines in place:
    ```
      authorize: ~/.ssh/id_rsa.pub
      
      keys:
          - ~/.ssh/id_rsa
    ```
    Otherwise, you should delete them. They are responsible for a) making it possible to connect
    to the box by using your own ssh key and b) letting vagrant use your private key (which might come in handy
    if you need to open up an SSH tunnel for example - but that's another story ;))
  - finally, to make your life a little easier, add `192.168.10.10 laravelexample.app` and  `192.168.10.10 www.laravelexample.app` 
    to the `host` file on your local machine. 
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
      If there are any issues editing the `host` file, [How-to Geek comes to the rescue. Again.](http://www.howtogeek.com/howto/27350/beginner-geek-how-to-edit-your-hosts-file/)
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
- cool, now let's start vagrant via `vagrant up`.
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
  Please note, that this will create a `.vagrant` folder in the directory of your laravel app.
- we should now be able to ssh into the virtual machine by using `vagrant ssh`
  ```
    $ vagrant ssh
    Welcome to Ubuntu 14.04.4 LTS (GNU/Linux 3.19.0-25-generic x86_64)
    
     * Documentation:  https://help.ubuntu.com/
    vagrant@laravelexample:~$
  ```
- navigate to the application root directory via `cd /home/vagrant/laravelexample/` which should look like this:
  ```
  vagrant@laravelexample:~/laravelexample$ cd /home/vagrant/laravelexample/
  vagrant@laravelexample:~/laravelexample$ ll
  total 157
  drwxrwxrwx 1 vagrant vagrant   4096 Jun 26 22:04 ./
  drwxr-xr-x 7 vagrant vagrant   4096 Jul 17 13:27 ../
  drwxrwxrwx 1 vagrant vagrant   4096 Jun 26 13:40 app/
  -rwxrwxrwx 1 vagrant vagrant   1646 Jun 26 13:40 artisan*
  drwxrwxrwx 1 vagrant vagrant      0 Jun 26 13:40 bootstrap/
  -rwxrwxrwx 1 vagrant vagrant   1309 Jun 26 13:43 composer.json*
  -rwxrwxrwx 1 vagrant vagrant 114898 Jun 26 13:44 composer.lock*
  drwxrwxrwx 1 vagrant vagrant   4096 Jun 26 13:40 config/
  drwxrwxrwx 1 vagrant vagrant   4096 Jun 26 13:40 database/
  -rwxrwxrwx 1 vagrant vagrant    423 Jun 26 13:40 .env.example*
  -rwxrwxrwx 1 vagrant vagrant     61 Jun 26 13:40 .gitattributes*
  -rwxrwxrwx 1 vagrant vagrant     73 Jun 26 13:40 .gitignore*
  -rwxrwxrwx 1 vagrant vagrant    503 Jun 26 13:40 gulpfile.js*
  -rwxrwxrwx 1 vagrant vagrant    332 Jun 26 22:04 Homestead.yaml*
  drwxrwxrwx 1 vagrant vagrant   4096 Jun 26 13:23 .idea/
  -rwxrwxrwx 1 vagrant vagrant    212 Jun 26 13:40 package.json*
  -rwxrwxrwx 1 vagrant vagrant   1026 Jun 26 13:40 phpunit.xml*
  drwxrwxrwx 1 vagrant vagrant   4096 Jun 26 13:40 public/
  -rwxrwxrwx 1 vagrant vagrant   1918 Jun 26 13:40 readme.md*
  drwxrwxrwx 1 vagrant vagrant      0 Jun 26 13:40 resources/
  -rwxrwxrwx 1 vagrant vagrant    567 Jun 26 13:40 server.php*
  drwxrwxrwx 1 vagrant vagrant      0 Jun 26 13:40 storage/
  drwxrwxrwx 1 vagrant vagrant      0 Jun 26 13:40 tests/
  drwxrwxrwx 1 vagrant vagrant      0 Jun 26 21:56 .vagrant/
  -rwxrwxrwx 1 vagrant vagrant    900 Jun 26 13:51 Vagrantfile*
  drwxrwxrwx 1 vagrant vagrant   4096 Jun 26 13:46 vendor/
  ```

Before we move on, let's follow the remaining [installation instructions in the laravel docs](https://laravel.com/docs/5.2), that is:
- create a `.env` file via `cp .env.example .env`
  ```
  vagrant@laravelexample:~/laravelexample$ cp .env.example .env
  ```
- generate an application key via `php artisan key:generate`
  ```
  vagrant@laravelexample:~/laravelexample$ php artisan key:generate
  Application key [base64:OhVwfzcFp40LaboJyCQAGS1briBwhYDupgvWJD/YYFE=] set successfully.
  ```

If we did everything right, we should now be able to open a browser and point it to http://laravelexample.app and see the 
Laravel welcome screen:
[![Laravel welcome screen](/img/laravel-with-phpunit-on-vagrant-in-phpstorm-on-windows-10/laravel/laravel-welcome-screen.PNG)](/img/laravel-with-phpunit-on-vagrant-in-phpstorm-on-windows-10/laravel/laravel-welcome-screen.PNG)

Let's take a step back and think about what we did and what it means:
So we basically set up a new Laravel installation (code-wise) and configured a vagrant homestead box via Laravel Homestead.
And all we need to do to get started, is to navigate into the directory of our app and run `vagrant up`. Seems not too shabby.
But what if we could make this even more comfortable?

### Convenience commands
Since we're use git bash, we can make use of [command aliases](https://wiki.ubuntuusers.de/alias/). This is pretty straight forward:
- Open up a new bash on your host machine
- type `cd ~` to navigate to your home directory
- type `vi .bashrc` to open up the vi editor with your [`.bashrc`](https://wiki.ubuntuusers.de/Bash/bashrc/) file (that is basically a configuration file for bash)
- add an alias like this:
  ```
  alias aliasname='command'
  ```
  which makes `aliasname` available to bash and will execute `command`.
- after you are finished modifying the file, type `:wq` to save and close vi. Of course, you can also use another editor ;) If you have any problems,
  [this post](http://superuser.com/a/602896) might help
- Now you need to either close and open your current bash session or type `. ~/.bashrc` (which is the same as `source ~/.bashrc`) to reload the changes

I usually define the following aliases for a new project:
```
alias ledir='cd "C:\\Users\\Pascal\\PhpstormProjects\\LaravelExample"'
alias leup='ledir && vagrant up'
alias ledown='ledir && vagrant halt'
alias lessh='ledir && vagrant ssh'
alias lein='leup && lessh'
```

where the `le`-prefix is just an abbreviation for "LaravelExample" and `"C:\\Users\\Pascal\\PhpstormProjects\\LaravelExample"` is the directory of the 
LaravelExample app on my host machine. So now, all I need to do when I start to work on that project is:
- open a new bash "anywhere"
- type `lein`
and that will start vagrant and ssh into it :)

## Configure PhpStorm
- same as before...
### Install laravel plugin
### Install IDE-helper
- suggest debugbar

## Setup PhpUnit
### phpunit.xml
- .env overriding
  - database settings
- Migrate refresh testcase class
- configure test db
  - benefit: having the exact same behaviour in your tests
- Setup command
  - (re)build databases

## Housekeeping
- init git
- .gitignore entries
  - Homestead.yaml
  - .vagrant
  - _ide_helper*
- helpful setup readme