<?php
/**
 * Backup Translations Helper
 * Provides translation functions for backup scripts
 * 
 * @package FileManager
 * @subpackage Backup
 */

/**
 * Get translated string for backup logs
 * 
 * @param string $key Translation key
 * @param string $lang Language code (es_ES, en_US, fr_FR, de_DE)
 * @param mixed ...$params Optional parameters for sprintf
 * @return string Translated string
 */
function getBackupTranslation($key, $lang = 'es_ES', ...$params) {
    static $translations = null;
    static $loadedLang = null;
    
    // Load translations if not loaded or language changed
    if ($translations === null || $loadedLang !== $lang) {
        $translations = loadBackupTranslations($lang);
        $loadedLang = $lang;
    }
    
    // Get translation or return key if not found
    $text = isset($translations[$key]) ? $translations[$key] : $key;
    
    // Apply sprintf if parameters provided
    if (!empty($params)) {
        $text = vsprintf($text, $params);
    }
    
    return $text;
}

/**
 * Load translations from language file
 * 
 * @param string $lang Language code
 * @return array Translations array
 */
function loadBackupTranslations($lang = 'es_ES') {
    $translations = array();
    
    // Fallback languages
    $fallbackLangs = array($lang, 'en_US', 'es_ES');
    
    foreach ($fallbackLangs as $tryLang) {
        $langFile = dirname(__DIR__) . '/langs/' . $tryLang . '/filemanager.lang';
        
        if (file_exists($langFile)) {
            $lines = file($langFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments and section headers
                if (strpos($line, '#') === 0 || strpos($line, '[') === 0) {
                    continue;
                }
                
                // Parse key=value
                $pos = strpos($line, '=');
                if ($pos !== false) {
                    $key = trim(substr($line, 0, $pos));
                    $value = trim(substr($line, $pos + 1));
                    $translations[$key] = $value;
                }
            }
            
            // If we found translations, stop looking
            if (!empty($translations)) {
                break;
            }
        }
    }
    
    // Add backup-specific translations that might not be in lang file
    $backupTranslations = getDefaultBackupTranslations($lang);
    $translations = array_merge($backupTranslations, $translations);
    
    return $translations;
}

/**
 * Get default backup translations for a language
 * 
 * @param string $lang Language code
 * @return array Default translations
 */
function getDefaultBackupTranslations($lang = 'es_ES') {
    $translations = array(
        'es_ES' => array(
            // Backup process messages
            'backup_started' => 'Copia de seguridad iniciada',
            'backup_completed' => 'Copia de seguridad completada exitosamente',
            'backup_failed' => 'Copia de seguridad fallida',
            'backup_cancelled' => 'Copia de seguridad cancelada',
            'backup_progress' => 'Progreso: %s%%',
            'processing_file' => 'Procesando archivo: %s',
            'processing_folder' => 'Procesando carpeta: %s',
            'processing_table' => 'Procesando tabla: %s',
            'creating_zip' => 'Creando archivo ZIP...',
            'adding_file' => 'Añadiendo archivo: %s',
            'adding_table' => 'Añadiendo tabla: %s',
            'table_exported' => 'Tabla exportada: %s (%s filas)',
            'files_processed' => 'Archivos procesados: %s de %s',
            'folders_processed' => 'Carpetas procesadas: %s',
            'total_size' => 'Tamaño total: %s',
            'duration' => 'Duración: %s',
            'error_msg' => 'Error: %s',
            'warning_msg' => 'Advertencia: %s',
            'info_msg' => 'Info: %s',
            'skipped' => 'Omitido: %s',
            'finished_at' => 'Finalizado a las %s',
            'saved_to' => 'Guardado en: %s',
            'analyzing_database' => 'Analizando base de datos...',
            'analyzing_files' => 'Analizando archivos...',
            'starting_backup' => 'Iniciando copia de seguridad...',
            'compressing_files' => 'Comprimiendo archivos...',
            'exporting_database' => 'Exportando base de datos...',
            'finalizing_backup' => 'Finalizando copia de seguridad...',
            'cleaning_temp_files' => 'Limpiando archivos temporales...',
            'backup_type_database' => 'Base de Datos',
            'backup_type_files' => 'Archivos',
            'backup_type_complete' => 'Completo',
            'backup_type_automatic' => 'Automático',
            'heartbeat_update' => 'Actualización de heartbeat',
            'lock_file_created' => 'Archivo de bloqueo creado',
            'lock_file_removed' => 'Archivo de bloqueo eliminado',
            'directory_not_writable' => 'Directorio no tiene permisos de escritura: %s',
            'using_fallback_dir' => 'Usando directorio alternativo: %s',
            'zip_created' => 'Archivo ZIP creado: %s',
            'zip_size' => 'Tamaño del ZIP: %s',
            'total_files' => 'Total de archivos: %s',
            'total_folders' => 'Total de carpetas: %s',
            'total_tables' => 'Total de tablas: %s',
            'total_rows' => 'Total de filas: %s',
        ),
        'en_US' => array(
            'backup_started' => 'Backup started',
            'backup_completed' => 'Backup completed successfully',
            'backup_failed' => 'Backup failed',
            'backup_cancelled' => 'Backup cancelled',
            'backup_progress' => 'Progress: %s%%',
            'processing_file' => 'Processing file: %s',
            'processing_folder' => 'Processing folder: %s',
            'processing_table' => 'Processing table: %s',
            'creating_zip' => 'Creating ZIP file...',
            'adding_file' => 'Adding file: %s',
            'adding_table' => 'Adding table: %s',
            'table_exported' => 'Table exported: %s (%s rows)',
            'files_processed' => 'Files processed: %s of %s',
            'folders_processed' => 'Folders processed: %s',
            'total_size' => 'Total size: %s',
            'duration' => 'Duration: %s',
            'error_msg' => 'Error: %s',
            'warning_msg' => 'Warning: %s',
            'info_msg' => 'Info: %s',
            'skipped' => 'Skipped: %s',
            'finished_at' => 'Finished at %s',
            'saved_to' => 'Saved to: %s',
            'analyzing_database' => 'Analyzing database...',
            'analyzing_files' => 'Analyzing files...',
            'starting_backup' => 'Starting backup...',
            'compressing_files' => 'Compressing files...',
            'exporting_database' => 'Exporting database...',
            'finalizing_backup' => 'Finalizing backup...',
            'cleaning_temp_files' => 'Cleaning temporary files...',
            'backup_type_database' => 'Database',
            'backup_type_files' => 'Files',
            'backup_type_complete' => 'Complete',
            'backup_type_automatic' => 'Automatic',
            'heartbeat_update' => 'Heartbeat update',
            'lock_file_created' => 'Lock file created',
            'lock_file_removed' => 'Lock file removed',
            'directory_not_writable' => 'Directory not writable: %s',
            'using_fallback_dir' => 'Using fallback directory: %s',
            'zip_created' => 'ZIP file created: %s',
            'zip_size' => 'ZIP size: %s',
            'total_files' => 'Total files: %s',
            'total_folders' => 'Total folders: %s',
            'total_tables' => 'Total tables: %s',
            'total_rows' => 'Total rows: %s',
        ),
        'fr_FR' => array(
            'backup_started' => 'Sauvegarde démarrée',
            'backup_completed' => 'Sauvegarde terminée avec succès',
            'backup_failed' => 'Échec de la sauvegarde',
            'backup_cancelled' => 'Sauvegarde annulée',
            'backup_progress' => 'Progression : %s%%',
            'processing_file' => 'Traitement du fichier : %s',
            'processing_folder' => 'Traitement du dossier : %s',
            'processing_table' => 'Traitement de la table : %s',
            'creating_zip' => 'Création du fichier ZIP...',
            'adding_file' => 'Ajout du fichier : %s',
            'adding_table' => 'Ajout de la table : %s',
            'table_exported' => 'Table exportée : %s (%s lignes)',
            'files_processed' => 'Fichiers traités : %s sur %s',
            'folders_processed' => 'Dossiers traités : %s',
            'total_size' => 'Taille totale : %s',
            'duration' => 'Durée : %s',
            'error_msg' => 'Erreur : %s',
            'warning_msg' => 'Avertissement : %s',
            'info_msg' => 'Info : %s',
            'skipped' => 'Ignoré : %s',
            'finished_at' => 'Terminé à %s',
            'saved_to' => 'Enregistré dans : %s',
            'analyzing_database' => 'Analyse de la base de données...',
            'analyzing_files' => 'Analyse des fichiers...',
            'starting_backup' => 'Démarrage de la sauvegarde...',
            'compressing_files' => 'Compression des fichiers...',
            'exporting_database' => 'Exportation de la base de données...',
            'finalizing_backup' => 'Finalisation de la sauvegarde...',
            'cleaning_temp_files' => 'Nettoyage des fichiers temporaires...',
            'backup_type_database' => 'Base de données',
            'backup_type_files' => 'Fichiers',
            'backup_type_complete' => 'Complète',
            'backup_type_automatic' => 'Automatique',
            'heartbeat_update' => 'Mise à jour du heartbeat',
            'lock_file_created' => 'Fichier de verrouillage créé',
            'lock_file_removed' => 'Fichier de verrouillage supprimé',
            'directory_not_writable' => 'Répertoire non accessible en écriture : %s',
            'using_fallback_dir' => 'Utilisation du répertoire de secours : %s',
            'zip_created' => 'Fichier ZIP créé : %s',
            'zip_size' => 'Taille du ZIP : %s',
            'total_files' => 'Total fichiers : %s',
            'total_folders' => 'Total dossiers : %s',
            'total_tables' => 'Total tables : %s',
            'total_rows' => 'Total lignes : %s',
        ),
        'de_DE' => array(
            'backup_started' => 'Sicherung gestartet',
            'backup_completed' => 'Sicherung erfolgreich abgeschlossen',
            'backup_failed' => 'Sicherung fehlgeschlagen',
            'backup_cancelled' => 'Sicherung abgebrochen',
            'backup_progress' => 'Fortschritt: %s%%',
            'processing_file' => 'Verarbeitung der Datei: %s',
            'processing_folder' => 'Verarbeitung des Ordners: %s',
            'processing_table' => 'Verarbeitung der Tabelle: %s',
            'creating_zip' => 'ZIP-Datei wird erstellt...',
            'adding_file' => 'Datei wird hinzugefügt: %s',
            'adding_table' => 'Tabelle wird hinzugefügt: %s',
            'table_exported' => 'Tabelle exportiert: %s (%s Zeilen)',
            'files_processed' => 'Verarbeitete Dateien: %s von %s',
            'folders_processed' => 'Verarbeitete Ordner: %s',
            'total_size' => 'Gesamtgröße: %s',
            'duration' => 'Dauer: %s',
            'error_msg' => 'Fehler: %s',
            'warning_msg' => 'Warnung: %s',
            'info_msg' => 'Info: %s',
            'skipped' => 'Übersprungen: %s',
            'finished_at' => 'Abgeschlossen um %s',
            'saved_to' => 'Gespeichert unter: %s',
            'analyzing_database' => 'Datenbank wird analysiert...',
            'analyzing_files' => 'Dateien werden analysiert...',
            'starting_backup' => 'Sicherung wird gestartet...',
            'compressing_files' => 'Dateien werden komprimiert...',
            'exporting_database' => 'Datenbank wird exportiert...',
            'finalizing_backup' => 'Sicherung wird abgeschlossen...',
            'cleaning_temp_files' => 'Temporäre Dateien werden gelöscht...',
            'backup_type_database' => 'Datenbank',
            'backup_type_files' => 'Dateien',
            'backup_type_complete' => 'Vollständig',
            'backup_type_automatic' => 'Automatisch',
            'heartbeat_update' => 'Heartbeat-Aktualisierung',
            'lock_file_created' => 'Sperrdatei erstellt',
            'lock_file_removed' => 'Sperrdatei entfernt',
            'directory_not_writable' => 'Verzeichnis nicht beschreibbar: %s',
            'using_fallback_dir' => 'Fallback-Verzeichnis wird verwendet: %s',
            'zip_created' => 'ZIP-Datei erstellt: %s',
            'zip_size' => 'ZIP-Größe: %s',
            'total_files' => 'Dateien gesamt: %s',
            'total_folders' => 'Ordner gesamt: %s',
            'total_tables' => 'Tabellen gesamt: %s',
            'total_rows' => 'Zeilen gesamt: %s',
        ),
    );
    
    // Return translations for requested language, fallback to Spanish
    return isset($translations[$lang]) ? $translations[$lang] : $translations['es_ES'];
}

/**
 * Detect user language from FileManager config, Dolibarr config or browser
 * 
 * @return string Language code
 */
function detectBackupLanguage() {
    // First, try to get from FileManager configuration
    $filemanagerLangFile = dirname(__DIR__) . '/lib/filemanager.lib.php';
    if (file_exists($filemanagerLangFile)) {
        if (!function_exists('getFileManagerConfig')) {
            require_once $filemanagerLangFile;
        }
        if (function_exists('getFileManagerConfig')) {
            $config = getFileManagerConfig();
            if (isset($config['FILEMANAGER_LANGUAGE']) && $config['FILEMANAGER_LANGUAGE'] !== 'auto' && !empty($config['FILEMANAGER_LANGUAGE'])) {
                return $config['FILEMANAGER_LANGUAGE'];
            }
        }
    }
    
    // Try to get from Dolibarr user config
    if (defined('DOL_DOCUMENT_ROOT')) {
        global $user, $langs;
        
        if (isset($user->conf->MAIN_LANG_DEFAULT) && !empty($user->conf->MAIN_LANG_DEFAULT)) {
            return $user->conf->MAIN_LANG_DEFAULT;
        }
        
        if (isset($langs->defaultlang) && !empty($langs->defaultlang)) {
            return $langs->defaultlang;
        }
    }
    
    // Try to get from session
    if (isset($_SESSION['MAIN_LANG_DEFAULT']) && !empty($_SESSION['MAIN_LANG_DEFAULT'])) {
        return $_SESSION['MAIN_LANG_DEFAULT'];
    }
    
    // Try to get from browser
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        $langMap = array(
            'es' => 'es_ES',
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
        );
        
        if (isset($langMap[$browserLang])) {
            return $langMap[$browserLang];
        }
    }
    
    // Default to Spanish
    return 'es_ES';
}

/**
 * Short alias for getBackupTranslation with auto language detection
 * 
 * @param string $key Translation key
 * @param mixed ...$params Optional parameters for sprintf
 * @return string Translated string
 */
function _bt($key, ...$params) {
    static $lang = null;
    
    if ($lang === null) {
        $lang = detectBackupLanguage();
    }
    
    return getBackupTranslation($key, $lang, ...$params);
}

