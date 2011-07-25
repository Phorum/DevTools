<?php
/**
 * This script implements the Phorum_ModuleBuilder class.
 *
 * @author Maurice Makaay
 * @copyright Phorum
 * @package Phorum
 * @category DevTools
 */

require_once dirname(__FILE__) . "/ModuleInfo.php";
require_once dirname(__FILE__) . "/PackageBuilder.php";

/**
 * The Phorum_ModuleBuilder class.
 *
 * This class provides functionality for packaging a Phorum module
 * into a format that can be used for distribution.
 *
 * @package Phorum
 */
class Phorum_ModuleBuilder extends Phorum_PackageBuilder
{
    /**
     * An object that contains information about the Phorum module.
     *
     * @var Phorum_ModuleInfo
     */
    protected $_module_info;

    /**
     * Create a new Phorum ModuleBuilder object.
     *
     * @param string $path
     *   The path for the directory where the module code is stored (this is
     *   the directory with the info.txt in it).
     *
     * @param string $build_dir
     *   The working directory for building the package (default is "/tmp").
     *
     * @throws Exception
     *   when illegal parameters are used.
     */
    public function __construct($path, $build_dir = "/tmp")
    {
        if (!is_dir($path)) throw new Exception(
           "Illegal module source dir '$path' provided: " .
           "not a directory"
        );

        $info_file = realpath("$path/info.txt");
        if (!file_exists($info_file)) throw new Exception(
            "Illegal module source dir '$path' provided: " .
            "no info.txt file found"
        );

        if (!is_dir($build_dir)) throw new Exception(
           "Illegal build dir '$build_dir' provided: not a directory"
        );

        // Initialize a module info object, used for gathering information
        // about the module. We will use this object at various places during
        // building the package.
        $this->_module_info = new Phorum_ModuleInfo();
        $this->_module_info->load($info_file);

        parent::__construct($this->_module_info->getID(), $path, $build_dir);
    }

    /**
     * Retrieve the version of the package.
     * This version is read from the module info.txt file.
     *
     * @return string
     */
    protected function _getPackageVersion()
    {
        return $this->_module_info->getVersion();
    }

    /**
     * Retrieve the directory where the package is built.
     *
     * Overridden from Phorum_PackageBuilder to keep out the
     * version number of the directory. For Phorum modules, it's
     * easiest to not have the version number included in the
     * packaged directory.
     *
     * @return string
     */
    protected function _getBuildPackageDir()
    {
        return $this->_build_dir . '/' . $this->_package_name;
    }

    protected function _substituteTags($data)
    {
        $data = str_replace(
            '@TITLE@', $this->_module_info->getTitle(), $data);

        $data = str_replace(
            '@MODULE_ID@', $this->_module_info->getId(), $data);

        $data = str_replace(
            '@REQUIRED_VERSION@',
            $this->_module_info->getRequiredVersion(), $data);

        $description = wordwrap(
            strip_tags($this->_module_info->getDescription()), 72);
        $data = str_replace('@DESCRIPTION@', $description, $data);

        return parent::_substituteTags($data);
    }
}

