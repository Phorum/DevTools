<?php
/**
 * This script implements the Phorum_ModuleInfo class.
 *
 * @author Maurice Makaay
 * @copyright Phorum
 * @package Phorum
 * @category DevTools
 */

/**
 * The Phorum_PackageBuilder class.
 *
 * This class provides functionality for packaging an application
 * into a format that can be used for distribution.
 *
 * @package Phorum
 */
class Phorum_ModuleInfo
{
    /**
     * The id for the module. This determines the directory in which
     * the module will be stored (phorumdir/mods/<id>) and the
     * name of the PHP script that implements the module
     * (phorumdir/mods/<id>/<id>.php).
     *
     * @var string
     */
    protected $_id;

    /**
     * The title of the module (info.txt "title" field).
     *
     * @var string
     */
    protected $_title;

    /**
     * A description of the module (info.txt "desc" field).
     *
     * @var string
     */
    protected $_description;

    /**
     * The author of the module (info.txt "author" field).
     *
     * @var string
     */
    protected $_author;

    /**
     * The URL for the module project (info.txt "url" field).
     *
     * @var string
     */
    protected $_url;

    /**
     * The version of the module (info.txt "version" field).
     *
     * @var string
     */
    protected $_version;

    /**
     * The required version of Phorum for the module
     * (info.txt "required_version" field).
     *
     * @var string
     */
    protected $_required_version;

    /**
     * The categories for the module (info.txt "category" fields).
     *
     * @var array
     */
    protected $_categories;

    /**
     * The hooks for the module (info.txt "hook" fields).
     *
     * @var array
     */
    protected $_hooks;

    /**
     * The compatibility hooks for the module (info.txt "compat" fields).
     *
     * @var array
     */
    protected $_compat;

    /**
     * The priorities for the module (info.txt "priority" fields).
     *
     * @var array
     */
    protected $_priorities;

    /**
     * The version of the dblayer for the module (info.txt "dbversion" field).
     *
     * @var string
     */
    protected $_dbversion;

    /**
     * Create a new Phorum ModuleInfo object.
     */
    public function __construct()
    {
        ini_set('track_errors', TRUE);

        $this->reset();
    }

    /**
     * Reset all module information data.
     */
    public function reset()
    {
        $this->_id               = NULL;
        $this->_title            = NULL;
        $this->_description      = NULL;
        $this->_version          = NULL;
        $this->_required_version = NULL;
        $this->_dbversion        = NULL;
        $this->_categories       = array();
        $this->_hooks            = array();
        $this->_compat           = array();
        $this->_priorities       = array(
            "hook"   => array(),
            "module" => array()
        );
    }

    /**
     * Load module info from a module's info.txt file.
     *
     * @param string $info_file
     *   The path for a module's info.txt file.
     */
    public function load($info_file)
    {
        $fp = @fopen($info_file, "r");
        if ($fp === FALSE) throw new Exception(
            "Cannot read module info: $php_errormsg"
        );

        $line_nr = 0;
        while (!feof($fp))
        {
            $line = fgets($fp);
            $line_nr ++;

            if (trim($line) == '' || preg_match('/^\s*#/', $line)) {
                continue;
            }
            elseif (preg_match('/^(\w+)\s*:\s*(.*?)\s*$/', $line, $m))
            {
                $directive = strtolower($m[1]);
                $value     = $m[2];

                $where = "at line $line_nr of $info_file";

                switch ($directive)
                {
                    case "id":
                        $this->_id = $value;
                        break;

                    case "title":
                        $this->_title = $value;
                        break;

                    case "desc":
                        $this->_description = $value;
                        break;

                    case "author":
                        $this->_author = $value;
                        break;

                    case "url":
                        $this->_url = $value;
                        break;

                    case "version":
                        $this->_version = $value;
                        break;

                    case "require_version":
                    case "required_version":
                        $this->required_version = $value;
                        break;

                    case "category":
                        $categories = preg_split('/\s*,\s*/', $value);
                        foreach ($categories as $category) {
                            $this->_categories[$category] = $category;
                        }
                        break;

                    case "priority":
                        if (preg_match('/^run\s+hook\s+(.+)\s+(before|after)\s(.+)$/i', $value, $m)) {
                            $this->_priorities['hook'][$m[1]][] = $m;
                        } elseif (preg_match('/^run\s+module\s+(before|after)\s(.+)$/i', $value, $m)) {
                            $this->_priorities['module'][] = $m;
                        } else {
                            throw new Exception(
                                "Invalid $directive $where: " .
                                "cannot parse priority '$value'"
                            );
                        }

                        break;

                    case "hook":
                    case "compat":
                        if ($directive == 'hook') {
                            $store =& $this->_hooks;
                        } else {
                            $store =& $this->_compat;
                        }
                        if (preg_match('/^(\w+)\|(.*)$/', $value, $m))
                        {
                            if (trim($m[2]) == '' &&
                                ($directive != 'hook' || $m[1] != 'lang')) {
                                throw new Exception(
                                    "Invalid $directive directive $where: " .
                                    "missing value after the '|' character"
                                );
                            }
                            if (isset($store[$m[1]])) throw new Exception(
                                "Duplicate $directive directive '$m[1]' $where"
                            );
                            $store[$m[1]] = $m[2];
                        }
                        else throw new Exception(
                            "Illegal value for $directive directive $where"
                        );
                        break;

                    case "dbversion":
                        if (!preg_match('/^\d{8}$/', $value)) throw new Exception(
                            "Invalid $directive directive $where: " .
                            "expected 8 numbers (format YYYYMMDDXX)"
                        );
                        $this->_dbversion = $value;
                        break;

                    default:
                        throw new Exception(
                            "Unknown directive '$directive' $where"
                        );
                        break;
                }
            }
            else
            {
                throw new Exception(
                    "Cannot parse line $line_nr of $info_file: $line"
                );
            }
        }

        fclose($fp);

        // Determine the directory in which the info.txt is stored.
        $parent_dir = dirname(realpath($info_file));

        // If no id is provided in the info.txt, then try to derive the id.
        if (trim($this->_id) == '')
        {
            // The id is assumed to match this directory (this matches the
            // normal directory layout for modules when installed in the
            // Phorum tree: phorumdir/mods/<id>).
            $id = basename($parent_dir);

            // Allow a directory format like "Module-<id>" (this is the format
            // that we use for the repositories on github). We strip the
            // directory name down to <id> alone.
            $id = preg_replace('/^.*-/', '', $id);

            // Check if the id is likely to be the module id.
            if (!preg_match('/^\w+$/', $id)) throw new Exception(
                "Cannot autodetect the module id: based on the directory " .
                "where info.txt is stored, we guessed '$id', but that " .
                "module id does not match the required format (letters, " .
                "numbers, underscores). Either rename the directory or " .
                "provide the 'id' directive in the module's info.txt."
            );
            if (!file_exists("$parent_dir/$id.php")) throw new Exception(
                "Cannot autodetect the module id: based on the directory " .
                "where info.txt is stored, we guessed '$id', but the " .
                "expected module script '$parent_dir/$id.php' does not exist." .
                "Either rename the directory, create the module script or " .
                "provide the 'id' directive in the module's info.txt."
            );

            $this->_id = $id;
        }
        else
        {
            $id = $this->_id;
            if (!file_exists("$parent_dir/$id.php")) throw new Exception(
                "Based on the module id '$id' from info.txt, the module " .
                "script '$parent_dir/$id.php' is expected, however it does " .
                "not exist. Either rename the directory, create the module " .
                "script or provide the correct 'id' directive in the " .
                "module's info.txt."
            );
        }

        $this->validate();
    }

    /**
     * Validate the module data.
     *
     * @throws Exception
     *   when the module data is invalid.
     */
    public function validate()
    {
    }

    /**
     * Retrieve the module id.
     *
     * @return string
     */
    public function getID()
    {
        if (trim($this->_id) == '') throw new Exception(
            "No id is set for the module."
        );
        return $this->_id;
    }

    /**
     * Retrieve the module version.
     *
     * @return string
     */
    public function getVersion()
    {
        if (trim($this->_version) == '') throw new Exception(
            "No version is set for the module."
        );
        return $this->_version;
    }

    /**
     * Retrieve the module title.
     *
     * @return string
     */
    public function getTitle()
    {
        if (trim($this->_title) == '') throw new Exception(
            "No title is set for the module."
        );
        return $this->_title;
    }

    /**
     * Retrieve the module description.
     *
     * @return string
     */
    public function getDescription()
    {
        if (trim($this->_description) == '') throw new Exception(
            "No description is set for the module."
        );
        return $this->_description;
    }
}

