---
extends: _layouts.post
section: content
title: "How to setup PHPStorm with Vagrant and Laravel [Tutorial]"
subheading: "... on a Windows machine. Guide should still be useful for Linux/Mac users ;)"
h1: "Setting up PHPStorm with Vagrant and Laravel"
author: "Pascal Landau"
published_at: "2016-05-29 16:38:13"
description: "Step-by-Step tutorial for setting up a new PHPStorm project for a fresh Laravel installation running on Vagrant."
---

In this tutorial I'll show my typical procedure when setting up an new development environment on a fresh Windows 10 laptop.
It's not like I do this every day (as 'typical' might suggest) but when I started my current job, I had to do it 
several times for me (switched my laptop) as well as for some of my co-workers. I'm going to cover this step-by-step
and will include (hopefully) all necessary information for you to get this setup running as well. 

Let's get to it, shall we?

##Contents


##Software overview
On the software side we're going to use:
- PHP 7.0.7 with Xdebug 2.4
- PHPStorm 2016.1.2 + Plugins
- Oracle Virtual Box 
- Vagrant 1.7.4
- Ruby
- Git Bash
- Composer 1.1 
- Laravel 5.2
- Phrase Express (optional)

##Setup PHP 7 (for local development)
I will do almost all of my development in a virtual machine because the final product usually runs on a unix server, but
from time to time I find it helpful to have a local setup available as well.

###Installation
- Download the current version of PHP 7 from the [PHP download page for Windows](http://windows.php.net/download/).
  Simply take the version at the top of the right panel which should be the latest NTS (non thread safe) build. 
  If you have 64-bit system, choose the _VC14 x64 Non Thread Safe_ variant.
  Click on the link labeled "zip" to start the download. 
- create a new folder named `php7` in your Program Files directory (e.g. `C:\Program Files`) and unzip the downloaded archive there
- double-click the file "php.exe" to confirm no error message shows up. You might encounter the error
_Unable to start the program as VCRUNTIME140.dll is missing on your computer. Try reinstalling the program to fix this problem._
It can be fixed by downloading and installing the corresponding file from [here](https://www.microsoft.com/en-us/download/details.aspx?id=48145).
Please make sure you pick the architecture (x64/x86) that matches the previously downloaded PHP7 version!

We're almost done, but we should modify the `PATH` variable in order to make PHP globally available (and thus usable by 
other tools like composer).

###The `PATH` variable
Simply put, the `PATH` variable defines where Windows looks for executable files when the specified file is not found 
in the current directory. So lets say you would like to know the current PHP version on your system, then 
[stackoverflow](http://stackoverflow.com/a/15517857/413531) will tell you something along the lines of
```
C:\>php -v
```

But you will probably get this

> php: command not found

or this

>'php' is not recognized as an internal or external command,
> operable program or batch file.

That command would only work if our current working directory (the location from where we executed the `php -v` command) would
contain the correct php.exe file. In other words: Calling the command is not location-agnostic yet. To make it, though,
we need to modify the `PATH` environment variable and make it aware of the location PHP is installed at.
 
To do so, we need to modify the System Properties... and might as well learn some nifty shortcuts to get there along the way :)

- open the "run" window by pressing WIN + R
- type "SystemPropertiesAdvanced" and hit Enter
- click on "Environment Variables..." at the bottom of the window
- select the `PATH` entry in the list of system variables (lower half) and click "Edit..."
- hit the "New" button and enter the full path to the directory that should be used to look up commands/programs
- confirm with "OK"
- open a new shell by hitting WIN + R, type "cmd" and confirm with Enter
  - any existing shell will *not* be aware of the changes in the environment variables!
- running `php -v` again should now yield something like this:
```
C:\>php -v
PHP 7.0.7 (cli) (built: May 25 2016 13:08:31) ( NTS )
Copyright (c) 1997-2016 The PHP Group
Zend Engine v3.0.0, Copyright (c) 1998-2016 Zend Technologies
```

##Setup PHPStorm (for local development)

###Installation
- Download the current version of PHPStorm from the [PHPStorm download page](https://www.jetbrains.com/phpstorm/download/)
  - Yes, it's paid but so far the best IDE I've come across + you get a 30 day free trial ;)
- Double-click the downloaded file (probably something like PhpStorm-2016.1.2.exe) and follow the instructions. 
  Nothing fancy there.
- After the installation finished, run PhpStorm. You should be greeted with the question for importing previous settings.
  We'll go with the "I do not have a previous version of PhpStorm or I do not want to import my settings" option as we
  can always do that later.
- After accepting the Privacy Policy you'll be prompted for the license activation. Unless you have a valid license,
  go with the "Evaluation for free for 30 days" option and accept the license agreement afterwards.
- PhpStorm will now start and ask you for the Inital Configuration - that is Keymap scheme, IDE theme and colors/fonts.
  Unless you have anything to change a that point, go with the defaults and hit OK.
- In the following New Project screen choose "PHP Empty Project" in the left hand list and hit "Create"
 
That'll conclude the installation :)
 
###Setup for local PHP development
First, let's create a new PHP file by right-clicking on the project folder and choosing New &gt; PHP File named `test.php`
with the following content:
```
<?php
$i = 1;
echo $i."\n";
```
Now select Run > Run... and choose the test.php with the small PHP icon in the front. A new window should appear because
we did not specify a PHP interpreter yet so PHPStorm doesn't know how to run the file

...