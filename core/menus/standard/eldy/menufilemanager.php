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
 * \file       custom/filemanager/core/menus/standard/eldy/menufilemanager.php
 * \ingroup    filemanager
 * \brief      FileManager menu
 */

/**
 * Menu class for FileManager
 */
class MenuFileManager
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Show menu for FileManager
     *
     * @param array $menu Array of menu items
     * @return int 1 if OK, 0 if KO
     */
    public function showMenu($menu)
    {
        global $conf, $langs, $user;

        if (empty($conf->filemanager->enabled)) {
            return 0;
        }

        if (!$user->rights->filemanager->read) {
            return 0;
        }

        $menu[] = array(
            'url' => DOL_URL_ROOT.'/custom/filemanager/index.php',
            'langs' => 'filemanager@filemanager',
            'enabled' => '$conf->filemanager->enabled',
            'perms' => '$user->rights->filemanager->read',
            'target' => '',
            'user' => 2,
            'module' => 'filemanager'
        );

        return 1;
    }
}


