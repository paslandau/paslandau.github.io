---
extends: _layouts.post
section: content
title: "How to build a CI Pipeline with Jenkins and Docker for PHP Apps [Tutorial Part 4]"
subheading: "... and configure it to automatically build pull requests."
h1: "Building a CI Pipeline with Jenkins for Docker-ized PHP Applications"
description: ""
author: "Pascal Landau"
published_at: ""
vgwort: ""
category: "development"
slug: "jenkins-ci-pipeline-for-dockerized-php-applications"
---

In the fourth part of this tutorial series on developing PHP on Docker we'll add our first additional
environment (CI - Continous Integration) to the picture. This will complement our development workflow
by automatically building and testing our application on every pull request / merge to a specific branch.

New to Docker? Better start from the beginning:
- [Setting up PHP, PHP-FPM and NGINX for local development on Docker](/blog/php-php-fpm-and-nginx-on-docker-in-windows-10/)
- [Setting up PhpStorm with Xdebug for local development on Docker](/blog/setup-phpstorm-with-xdebug-on-docker/)
- [Running a complete PHP Development Environment/Infrastructure on Docker](/blog/running-complete-php-development-environment-on-docker/)
-

**Note**: The setup that I am going to use is for CI purposes only! I do **not** recommend that you use it
in production. We'll still have unnecessary software in the images (e.g. for running the tests) and don't really need to
worry too much about security as the containers will only run for a short amount of time.

Getting ready for production will be the topic of the next part in this series.

Please subscribe to the [RSS feed](/feed.xml) or [via email](#newsletter) to get automatic notifications when that article comes out :)

## Table of contents

## Introduction
- what is CI
- great because it enforces quality ==> embrace the power of "habit" in code
- no more faulty deployments
- history of changes & their effects (e.g. code metrics, code coverage, ...)

## Changes in docker / docker-compose for "building" applications
- no more syncing between host and container ==> build instead
  - affects code base
  - affects log files
- PHP problem: same code in multiple containers (phpfpm, nginx, worker, ...)
  - volume vs copy
  - builder pattern vs multi-stage builds, problems:
    - `depends_on` in docker-compose doesn't affect build order
    - docker-compose builds all targets (==> BuildKit not available yet)
    - no more "workspace" in favor of lightweight "application"
- overriding docker-compose files
- introduce "ENV" variable for targets

## Incorporating CI targets in the Makefile
- targets must be runnable from "outside of the container"
- introduce "ENV" variable to choose correct docker-compose files

## Setting up Jenkins
- use docker to set up jenkins
  - problem: using Docker in Docker (dind)
  - solution: mount `/var/run/docker.sock:/var/run/docker.sock` from host in jenkins container
- use Volume for `$JENKINS_HOME`
- installing plugins
- basic configuration
  - language
  - emails / users
  - blue ocean
  - ...
- how to provide default users and config for jenkins itself and plugins in the Dockerfile?
  - put files in `/usr/share/jenkins/ref/` instead of `$JENKINS_HOME`
- Creating a pipeline
 - scripting / declarative
 - Jenkinsfile in .ci/ folder in repo
- creating a "default" pipeline job
 - configure bitbucket / github
 - add ssh credentials
 - pull code, use 'make' for qa/testing/code metrics
 - How to get the code metrics out of the container? 
 - configure emails after builds via `email-ext` plugin
 - trigger from UI
- creating a "PR" pipeline job
 - Use hooks on github / bitbucket
 - configure parametrized Job
 - send PR ID in the job, checkout only that PR for running the pipeline
 - trigger via curl
   ````
   # get CSRF crumb
   curl -u admin:admin -s 'http://jenkins.atmo.local:8080/crumbIssuer/api/xml?xpath=concat(//crumbRequestField,":",//crumb)'
   
   # ==> e.g.: Jenkins-Crumb:2d319669134e923842ab55a10a6cb36d
   
   # get PR Id from bitbucket, e.g. "44"
   # https://bitbucket.collins.kg/projects/SEM/repos/atmo/pull-requests/44/overview
   # ------------------------------------------------------------------^^^
   # start build
   curl -u admin:admin -X POST --data "PR=44" -H "Jenkins-Crumb:2d319669134e923842ab55a10a6cb36d" http://jenkins.atmo.local:8080/job/atmo-pr/buildWithParameters?token=YOUR_TOKEN
   ````
   - https://bjurr.com/continuous-integration-with-bitbucket-server-and-jenkins/
   - https://github.com/tomasbjerre/pull-request-notifier-for-bitbucket/blob/master/README_jenkins.md

## Setting up Github / Bitbucket
 
