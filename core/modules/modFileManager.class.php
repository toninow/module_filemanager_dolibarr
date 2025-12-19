<?php
/* Copyright (C) 2024  Your Name or Company
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       custom/filemanager/core/modules/modFileManager.class.php
 * \ingroup    filemanager
 * \brief      FileManager Pro - Advanced file management and backup system for Dolibarr
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modFileManager
 * FileManager Pro module descriptor
 */
class modFileManager extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Module ID (must be unique)
        // Use a free id here (See https://wiki.dolibarr.org/index.php/List_of_modules_id)
        $this->numero = 500100;

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'filemanager';

        // Family can be 'base', 'crm', 'financial', 'hr', 'projects', 'products', 'ecm', 'technic', 'interface', 'other'
        $this->family = "tools";

        // Module position in the family (1 to 100)
        $this->module_position = '90';

        // Module name (translatable)
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description (translatable)
        $this->description = "FileManager Pro - Gestión avanzada de archivos y backups";
        
        // Long description (translatable)
        $this->descriptionlong = "Módulo profesional para la gestión de archivos de Dolibarr con explorador visual, operaciones de archivos (copiar, mover, renombrar, eliminar), sistema completo de backups (base de datos, archivos, completo), backups automáticos programados, papelera de reciclaje y diseño responsive.";

        // Editor information
        $this->editor_name = 'Antonio Benalcazar';
        $this->editor_url = 'https://antoniobenalcazar.in';

        // Version
        $this->version = '1.0.0';

        // Minimum PHP version - Compatible desde PHP 7.0+
        $this->phpmin = array(7, 0);

        // Minimum Dolibarr version - Compatible desde Dolibarr 13+
        $this->need_dolibarr_version = array(13, 0);

        // Const name for module state
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Module icon
        $this->picto = 'folder';

        // Module parts
        $this->module_parts = array(
            'triggers' => 0,
            'login' => 0,
            'substitutions' => 0,
            'menus' => 1,
            'tpl' => 0,
            'barcode' => 0,
            'models' => 0,
            'theme' => 0,
            'css' => array('/filemanager/css/filemanager.css'),
            'js' => array('/filemanager/js/filemanager.js'),
            'hooks' => array(),
            'moduleforexternal' => 0,
        );

        // Data directories to create when module is enabled
        $this->dirs = array(
            "/filemanager/temp",
            "/filemanager/backups",
        );

        // Config page URL
        $this->config_page_url = array("setup.php@filemanager");

        // Dependencies
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();

        // Language files
        $this->langfiles = array("filemanager@filemanager");

        // Constants
        $this->const = array(
            1 => array('FILEMANAGER_ROOT_PATH', 'chaine', DOL_DOCUMENT_ROOT, 'Root path for file manager', 0, 'current', 1),
            2 => array('FILEMANAGER_PROTECTED_FOLDERS', 'chaine', 'conf,install', 'Protected folders (comma separated)', 0, 'current', 1),
            3 => array('FILEMANAGER_MAX_UPLOAD_SIZE', 'chaine', '104857600', 'Maximum upload size in bytes (100MB)', 0, 'current', 1),
        );

        // Tabs
        $this->tabs = array();

        // Dictionaries
        $this->dictionaries = array();

        // Boxes/Widgets
        $this->boxes = array();

        // Cronjobs
        $this->cronjobs = array(
            0 => array(
                'label' => 'FileManager Auto Backup',
                'jobtype' => 'method',
                'class' => '/filemanager/class/filemanager.class.php',
                'objectname' => 'FileManager',
                'method' => 'runAutoBackup',
                'parameters' => '',
                'comment' => 'Execute automatic backups based on configuration',
                'frequency' => 1,
                'unitfrequency' => 86400,
                'status' => 0,
                'test' => '$conf->filemanager->enabled',
                'priority' => 50,
            ),
        );

        // Permissions provided by this module
        $this->rights = array();
        $r = 0;

        // Permission to read/access file manager
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Acceder al FileManager';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'read';
        $this->rights[$r][5] = '';
        $r++;

        // Permission to write/modify files
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Crear y modificar archivos';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'write';
        $this->rights[$r][5] = '';
        $r++;

        // Permission to delete files
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Eliminar archivos';
        $this->rights[$r][2] = 'd';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'delete';
        $this->rights[$r][5] = '';
        $r++;

        // Permission to manage backups
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Gestionar copias de seguridad';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'backup';
        $this->rights[$r][5] = '';
        $r++;

        // Permission to configure module
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Configurar FileManager';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'setup';
        $this->rights[$r][5] = '';
        $r++;

        // Menus
        $this->menu = array();
        $r = 0;

        // Top menu entry
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=tools',
            'type' => 'left',
            'titre' => 'FileManager',
            'prefix' => img_picto('', 'folder', 'class="paddingright pictofixedwidth"'),
            'mainmenu' => 'tools',
            'leftmenu' => 'filemanager',
            'url' => '/filemanager/index.php',
            'langs' => 'filemanager@filemanager',
            'position' => 1000 + $r,
            'enabled' => '$conf->filemanager->enabled',
            'perms' => '$user->rights->filemanager->read',
            'target' => '',
            'user' => 0,
        );

        // Submenu: Configuración
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=filemanager',
            'type' => 'left',
            'titre' => 'Configuración',
            'mainmenu' => 'tools',
            'leftmenu' => 'filemanager_setup',
            'url' => '/filemanager/admin/setup.php',
            'langs' => 'filemanager@filemanager',
            'position' => 1000 + $r,
            'enabled' => '$conf->filemanager->enabled',
            'perms' => '$user->rights->filemanager->setup',
            'target' => '',
            'user' => 0,
        );
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     * It also creates data directories
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf;

        $result = $this->_load_tables('/filemanager/sql/');
        if ($result < 0) {
            return -1;
        }

        // Create backup directory if not exists
        $backupDir = DOL_DATA_ROOT.'/filemanager/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        // Create logs directory if not exists
        $logsDir = DOL_DATA_ROOT.'/filemanager/logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }

        return $this->_init(array(), $options);
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted
     *
     * @param string $options Options when disabling module
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
