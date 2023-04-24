<p align="center">
<img src="https://www.sd-bee.com/upload/O0W1b3s20_logosite.png" alt="Simply Done Bee Service Gateway" /><br>
SD bee - Simply Done<br>
<strong>Design, automate and deploy processes for digital tasks</strong>
</p>

"SD bee" is a software program for delivering web pages and web apps for designing, automating and deploying processes required for digitalisation.

## WHAT'S INCLUDED

The "SD bee service gateway" package contains a library of software and tools for deploying lightweight backend service APIs, such as Google Cloud Functions, that SD bee tasks can invoke.

For cloud deployment, it is best practice to bundle code so that the smallest possible memory is required when providing a service. This package is used to deploy specific services with specifc providers from a wide range of services.

Additionnally, you can group several API deployments in a single API gateway, either using the gateway provided or the API gateway facilities of your cloud provider.

For more information on SD bee, please see the SD bee repository on GitHub.

## LICENSE

The software is published under the GNU GENERAL PUBLIC LICENSE Version 3.
see LICENSE.md

## CREATOR

SD bee was created by Quentin Cornwell
[Find me on LinkedIn](https://www.linkedin.com/in/quentin-cornwell-895b0a/)

## CONTRIBUTING

SD bee is in search of software and business developpers interested in using the Software for a single company or for providing an online service.

## CONFIGURATION

the build/addService command adds a service to  a specific deployment. Syntax, from the top directory is :

build/addService path-to-service deployment-name

## DEPLOYMENT

The build/deploy command installs sofwtare packages required for the choses services and deploys a cloud function. Syntax, from the top directory is :

build/deploy deployment-name caller ..

## SECURITY

Security relies on the security mechanisms of the cloud being used. 

For GCP, the cloud functions are set up to only accept requests from the sd-bee project you have already setup.

