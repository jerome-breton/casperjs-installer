<?php

/*
 * This file is part of the "jerome-breton/casperjs-installer" package.
 *
 * The content is released under the MIT License. Please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CasperJsInstaller;

use Composer\Script\Event;
use Composer\Composer;

use Composer\Package\Package;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;

class Installer
{
    const CASPERJS_NAME = 'CasperJS';

    const CASPERJS_TARGETDIR = '/jerome-breton/casperjs';

    /**
     * Operating system dependend installation of CasperJS
     */
    public static function installCasperJS(Event $event)
    {
        \PhantomInstaller\Installer::installPhantomJS($event);

        $composer = $event->getComposer();

        $version = self::getVersion($composer);

        $url = self::getURL($version);

        $binDir = $composer->getConfig()->get('bin-dir');

        // the installation folder depends on the vendor-dir (default prefix is './vendor')
        $targetDir = $composer->getConfig()->get('vendor-dir') . self::CASPERJS_TARGETDIR;

        // Create Composer In-Memory Package

        $versionParser = new VersionParser();
        $normVersion = $versionParser->normalize($version);

        $package = new Package(self::CASPERJS_NAME, $normVersion, $version);
        $package->setTargetDir($targetDir);
        $package->setInstallationSource('dist');
        $package->setDistType('zip');
        $package->setDistUrl($url);

        // Download the Archive

        $downloadManager = $composer->getDownloadManager();
        $downloadManager->download($package, $targetDir, false);

        // Create CasperJS launcher in the "bin" folder
        self::createCasperJsBinaryToBinFolder($targetDir, $binDir);
    }

    /**
     * Returns the PhantomJS version number.
     *
     * Firstly, we search for a version number in the local repository,
     * secondly, in the root package.
     * A version specification of "dev-master#<commit-reference>" is disallowed.
     *
     * @param Composer $composer
     * @return string $version Version
     */
    public static function getVersion($composer)
    {
        $packages = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();

        foreach($packages as $package) {
            if($package->getName() === 'jerome-breton/casperjs-installer') {
                $version = $package->getPrettyVersion();
            }
        }

        // version was not found in the local repository, let's take a look at the root package
        if($version == null) {
            $version = self::getRequiredVersion($composer->getPackage(), 'jerome-breton/casperjs-installer');
        }

        if($version == 'dev-master'){
            return 'master';
        }

        return $version;
    }

    /**
     * Returns the version for the given package either from the "require" or "require-dev" packages array.
     *
     * @param RootPackageInterface $package
     * @param string $packageName
     * @throws \RuntimeException
     * @return mixed
     */
    public static function getRequiredVersion(RootPackageInterface $package, $packageName = 'jerome-breton/casperjs-installer')
    {
        foreach (array($package->getRequires(), $package->getDevRequires()) as $requiredPackages) {
            if (isset($requiredPackages[$packageName])) {
                return $requiredPackages[$packageName]->getPrettyConstraint();
            }
        }
        throw new \RuntimeException('Can not determine required version of ' . $packageName);
    }

    /**
     * Create CasperJS launcher in the "bin" folder
     * Takes different "folder structure" of the archives and different "binary file names" into account.
     */
    public static function createCasperJsBinaryToBinFolder($targetDir, $binDir)
    {
        if (!is_dir($binDir)) {
            mkdir($binDir);
        }
        $os = self::getOS();
        $sourcePath = $targetDir.'/bin/casperjs';
        $phantomPath = $binDir . '/phantomjs';
        $targetPath = $binDir . '/casperjs';
        if ($os === 'windows') {
            // the suffix for binaries on windows is ".exe"
            $sourcePath .= '.exe';
            $phantomPath .= '.exe';
            $targetPath .= '.bat';
            file_put_contents($targetPath, "SET PHANTOMJS_EXECUTABLE=$phantomPath\n$sourcePath %*");
        }
        if ($os == 'linux' || $os == 'macosx') {
            file_put_contents($targetPath, "#!/bin/bash\nPHANTOMJS_EXECUTABLE=$phantomPath $sourcePath $*");
            chmod($targetPath, 0755);
        }
    }

    /**
     * Returns the URL of the PhantomJS distribution for the installing OS.
     *
     * @param string $version
     * @return string Download URL
     */
    public static function getURL($version)
    {
        return 'https://github.com/n1k0/casperjs/zipball/'.$version;
    }

    /**
     * Returns the Operating System.
     *
     * @return string OS, e.g. macosx, windows, linux.
     */
    public static function getOS()
    {
        $uname = strtolower(php_uname());

        if (strpos($uname, "darwin") !== false) {
            return 'macosx';
        } elseif (strpos($uname, "win") !== false) {
            return 'windows';
        } elseif (strpos($uname, "linux") !== false) {
            return 'linux';
        } else {
            return 'unknown';
        }
    }

    /**
     * Returns the Bit-Size.
     *
     * @return string BitSize, e.g. 32, 64.
     */
    public static function getBitSize()
    {
        if (PHP_INT_SIZE === 4) {
            return 32;
        }

        if (PHP_INT_SIZE === 8) {
            return 64;
        }

        return PHP_INT_SIZE; // 16-bit?
    }
}
