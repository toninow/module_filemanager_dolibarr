<?php
/* Copyright (C) 2024  Open-DSI  <support@open-dsi.fr>
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
 * \file       custom/filemanager/core/class/filemanagerperms.class.php
 * \ingroup    filemanager
 * \brief      FileManager permissions class
 */

/**
 * Class to manage permissions of FileManager module
 */
class FileManagerPerms
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Load permissions for FileManager module
     *
     * @param DoliDB $db Database handler
     * @return int 1 if OK, 0 if KO
     */
    public function loadPermissions($db)
    {
        global $conf, $langs;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."rights_def (entity, libelle, module, perms, subperms, type, bydefault) VALUES";
        $sql .= " (0, 'FileManager', 'filemanager', 'read', '', 'boolean', 1),";
        $sql .= " (0, 'FileManager', 'filemanager', 'write', '', 'boolean', 0),";
        $sql .= " (0, 'FileManager', 'filemanager', 'delete', '', 'boolean', 0);";

        $resql = $db->query($sql);
        if (!$resql) {
            dol_syslog("Error in loadPermissions for filemanager", LOG_ERR);
            return 0;
        }

        return 1;
    }
}


