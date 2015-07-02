casperjs-installer [![Latest Stable Version](https://poser.pugx.org/jerome-breton/casperjs-installer/v/stable)](https://packagist.org/packages/jerome-breton/casperjs-installer) [![Total Downloads](https://poser.pugx.org/jerome-breton/casperjs-installer/downloads)](https://packagist.org/packages/jerome-breton/casperjs-installer) [![Latest Unstable Version](https://poser.pugx.org/jerome-breton/casperjs-installer/v/unstable)](https://packagist.org/packages/jerome-breton/casperjs-installer) [![License](https://poser.pugx.org/jerome-breton/casperjs-installer/license)](https://packagist.org/packages/jerome-breton/casperjs-installer)
===================

A Composer package which installs the CasperJS and PhantomJS binary (Linux, Windows, Mac) into the bin path of your 
project.

## Installation

To install CasperJS and PhantomJS as a local, per-project dependency to your project, simply add a dependency on 
`jerome-breton/casperjs-installer` to your project's `composer.json` file.


```json
{
    "require": {
        "jerome-breton/casperjs-installer": "dev-master"
    },
    "scripts": {
        "post-install-cmd": [
            "CasperJsInstaller\\Installer::install"
        ],
        "post-update-cmd": [
            "CasperJsInstaller\\Installer::install"
        ]
    }
}
```

For a development dependency, change `require` to `require-dev`.

The version number of the package specifies the CasperJS version! But for now, no versions of PhantomJS are prebuilt
for 1.0.* versions, so for now, only dev-master is working, and will fetch the last 1.9.* PhantomJS version.

The download source used is: https://github.com/n1k0/casperjs/zipball/master

You can set the Composer configuration directive `bin-dir` to change the 
[vendor binaries](https://getcomposer.org/doc/articles/vendor-binaries.md#can-vendor-binaries-be-installed-somewhere-other-than-vendor-bin-) 
installation folder. **Important! Composer will install the binaries into `vendor\bin` by default.**

The `scripts` section is necessary, because currently Composer does not pass events to the handler scripts of 
dependencies. If you leave it away, you might execute the installer manually.

Now, assuming that the scripts section is set up as required, CasperJS and PhantomJS binary
will be installed into the bin folder and updated alongside the project's Composer dependencies.

## How does this work internally?

1. **Fetching the CasperJS Installer**
In your composer.json you require the package "casperjs-installer".
The package is fetched by composer and stored into `./vendor/jerome-breton/casperjs-installer`.
It contains only one file the `CasperJsInstaller\\Installer`.

2. **Fetching PhantomJS**
This installer depends on [jakoch/phantomjs-installer](https://github.com/jakoch/phantomjs-installer) to install 
PhantomJS and follows the same strategy. This project has been created with major part of Jakoch work. The 
CasperJsInstaller will call Jakoch's `PhantomJSInstaller\\Installer`.

2. **Platform-specific download of PhantomJS**
The `PhantomInstaller\\Installer` is run as a "post-install-cmd". That's why you need the "scripts" section in your 
"composer.json". The installer creates a new composer in-memory package "casperjs" and downloads the correct Phantom 
version to the folder `./vendor/jerome-breton/casperjs`. All CasperJS files reside there, especially the `samples`.

3. **Installation into bin folder**
A launcher is created to declare PhantomJS path and launch CasperJS from `./vendor/jerome-breton/casperjs` to your 
composer configured `bin-dir` folder.
