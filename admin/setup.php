<?php
/**
 * Setup del modulo FileManager - Version funcional
 */

// Incluir el entorno de Dolibarr
require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// ========== VERIFICACION DE SEGURIDAD 1: MODULO ACTIVADO ==========
if (empty($conf->global->MAIN_MODULE_FILEMANAGER)) {
    // Módulo desactivado - mostrar página de error
    llxHeader('', 'FileManager - Módulo Desactivado');
    print '<div style="max-width: 800px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;">';
    print '<div style="font-size: 72px; color: #dc3545; margin-bottom: 20px;">⚠️</div>';
    print '<h1 style="color: #dc3545; margin-bottom: 20px;">Módulo FileManager Desactivado</h1>';
    print '<p style="font-size: 18px; color: #6c757d; margin-bottom: 30px;">';
    print 'El módulo FileManager está actualmente desactivado en Dolibarr.<br>';
    print 'Para utilizar esta funcionalidad, debe activar el módulo desde el menú de administración.';
    print '</p>';
    print '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px;">';
    print '<p style="margin: 0; color: #495057;"><strong>¿Necesita ayuda?</strong></p>';
    print '<p style="margin: 10px 0 0 0; color: #6c757d;">Contacte al administrador de Dolibarr para activar el módulo.</p>';
    print '</div>';
    print '</div>';
    llxFooter();
    exit;
}

// ========== VERIFICACIÓN DE SEGURIDAD 2: PERMISOS DE ADMINISTRADOR ==========
if (!$user->admin) {
    // Usuario no es administrador - mostrar página de error
    llxHeader('', 'FileManager - Acceso Denegado');
    print '<div style="max-width: 800px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;">';
    print '<div style="font-size: 72px; color: #dc3545; margin-bottom: 20px;">🔒</div>';
    print '<h1 style="color: #dc3545; margin-bottom: 20px;">Acceso Denegado</h1>';
    print '<p style="font-size: 18px; color: #6c757d; margin-bottom: 30px;">';
    print 'No tiene permisos de administrador para acceder al FileManager.<br>';
    print 'Esta funcionalidad está restringida únicamente a administradores de Dolibarr.';
    print '</p>';
    print '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px;">';
    print '<p style="margin: 0; color: #495057;"><strong>¿Necesita acceso al FileManager?</strong></p>';
    print '<p style="margin: 10px 0 0 0; color: #6c757d;">Contacte al administrador de Dolibarr para solicitar permisos de administrador.</p>';
    print '</div>';
    print '</div>';
    llxFooter();
    exit;
}

$langs->loadLangs(array('admin','other'));
$token = newToken();

// Cargar configuración actual desde la base de datos
require_once '../lib/filemanager.lib.php';
require_once '../lib/filemanager_i18n.php';
$config = getFileManagerConfig();

// FORZAR el idioma seleccionado en FileManager si no es "auto"
if (isset($config['FILEMANAGER_LANGUAGE']) && $config['FILEMANAGER_LANGUAGE'] !== 'auto') {
    $fm_lang_short = $config['FILEMANAGER_LANGUAGE'];
    
    // Convertir código corto a código largo de Dolibarr
    $lang_map = array(
        'es' => 'es_ES',
        'en' => 'en_US',
        'fr' => 'fr_FR',
        'de' => 'de_DE'
    );
    
    $fm_lang = isset($lang_map[$fm_lang_short]) ? $lang_map[$fm_lang_short] : $fm_lang_short;
    
    // Forzar el idioma en el objeto $langs
    $langs->setDefaultLang($fm_lang);
    $langs->load('main');
    $langs->load('admin');
} else {
    $fm_lang = $langs->defaultlang;
}

// Cargar traducciones de FileManager
$langs->load('filemanager@filemanager');

// Traducciones según el idioma seleccionado
$fm_translations = array(
    'es_ES' => array(
        'BackupDatabase' => 'Base de Datos',
        'BackupDatabaseDesc' => 'Exporta todas las tablas SQL en formato comprimido',
        'BackupFiles' => 'Archivos',
        'BackupFilesDesc' => 'Comprime todos los archivos de la instalación',
        'BackupComplete' => 'Backup Completo',
        'BackupCompleteDesc' => 'Base de datos + Archivos en un solo ZIP',
        'BackupEnabled' => 'Activo',
        'BackupDisabled' => 'Inactivo',
        'Recommended' => 'Recomendado',
        'Language' => 'Idioma',
        'Automatic' => 'Automático',
        'Configuration' => 'Configuración',
        'Backups' => 'Copias de Seguridad',
        'History' => 'Historial',
        'FileManager' => 'Administrador de Archivos',
        'ViewLogs' => 'Ver Registros',
        'Download' => 'Descargar',
        'Delete' => 'Eliminar',
        'Size' => 'Tamaño',
        'Date' => 'Fecha',
        'Type' => 'Tipo',
        'Never' => 'Nunca',
        'NotScheduled' => 'No programado',
        'About' => 'Acerca de',
        'ModuleVersion' => 'Versión del Módulo',
        'ModuleAuthor' => 'Autor',
        'ModuleLicense' => 'Licencia',
        'ModuleDescription' => 'Descripción',
        'ModuleFeatures' => 'Características',
        'ModuleSupport' => 'Soporte',
        'ModuleWebsite' => 'Sitio Web',
        'ModuleDocumentation' => 'Documentación',
        'General' => 'General',
        'Permissions' => 'Permisos',
        'RootPath' => 'Ruta raíz del FileManager',
        'LanguageForUI' => 'Idioma para la interfaz y logs',
        'FileLimit' => 'Límite de archivos por carpeta',
        'AllowDelete' => 'Permitir eliminación de archivos',
        'AllowUpload' => 'Permitir subida de archivos',
        'ShowDeleteButton' => 'Mostrar botón de eliminación',
        'AllowProtectedActions' => 'Permitir acciones en archivos protegidos',
        'ProtectedWarning' => 'ADVERTENCIA: Permite eliminar archivos críticos del sistema',
        'AllowedFileTypes' => 'Tipos de Archivos Permitidos',
        'SaveConfiguration' => 'Guardar Configuración',
        'SelectAll' => 'Todo',
        'DeselectAll' => 'Ninguno',
        'SecurityTip' => 'Consejo:',
        'SecurityTipText' => 'Selecciona solo las extensiones necesarias para mayor seguridad',
        'PersonalizeFileManager' => 'Personaliza el comportamiento del FileManager',
        'AvailableBackups' => 'Backups disponibles',
        'ActivityLog' => 'Registro de Actividad',
        'CurrentStatus' => 'Estado Actual',
        'Status' => 'Estado',
        'Frequency' => 'Frecuencia',
        'LastBackup' => 'Última Copia',
        'NextBackup' => 'Próxima Copia',
        'TimeRemaining' => 'Tiempo restante',
        'BackupProgress' => 'Progreso del Backup',
        'BackupLog' => 'Log del Backup',
        'Active' => 'Activo',
        'Inactive' => 'Inactivo',
        'Daily' => 'Diaria',
        'Weekly' => 'Semanal',
        'Monthly' => 'Mensual',
        'DatabaseOnly' => 'Solo Base de Datos',
        'FilesOnly' => 'Solo Archivos',
        'Complete' => 'Completo',
        'BackupType' => 'Tipo de Backup',
        'ExecutionTime' => 'Hora de Ejecución (Reloj del Servidor)',
        'EnableCronJob' => 'Activar Cron Job',
        'LatestRecords' => 'Últimos 100 registros',
        'SavedSuccessfully' => 'Configuraciones guardadas correctamente',
        'ErrorInvalidPath' => 'Error: La ruta especificada no existe o no tiene permisos de escritura',
        'CompleteActivityLog' => 'Registro completo de actividad del FileManager',
        'TotalRecords' => 'Total Registros',
        'Today' => 'Hoy',
        'SearchFilters' => 'Filtros de Búsqueda',
        'ActionType' => 'Tipo de Acción',
        'AllActions' => 'Todas las acciones',
        'AllUsers' => 'Todos los Usuarios',
        'From' => 'Desde',
        'To' => 'Hasta',
        'Search' => 'Buscar',
        'Clear' => 'Limpiar',
        'Refresh' => 'Actualizar',
        'DeleteAll' => 'Eliminar Todos',
        'DateTime' => 'Fecha/Hora',
        'User' => 'Usuario',
        'Action' => 'Acción',
        'FilePath' => 'Archivo/Ruta',
        'Details' => 'Detalles',
        'Actions' => 'Acciones',
        'NoRecordsAvailable' => 'No hay registros disponibles',
        'ActivityRecordsWillAppear' => 'Los registros de actividad aparecerán aquí',
        'Unknown' => 'Desconocido',
        'Never' => 'Nunca',
        'NotScheduled' => 'No programado',
    ),
    'en_US' => array(
        'BackupDatabase' => 'Database',
        'BackupDatabaseDesc' => 'Export all SQL tables in compressed format',
        'BackupFiles' => 'Files',
        'BackupFilesDesc' => 'Compress all installation files',
        'BackupComplete' => 'Complete Backup',
        'BackupCompleteDesc' => 'Database + Files in one ZIP',
        'BackupEnabled' => 'Active',
        'BackupDisabled' => 'Inactive',
        'Recommended' => 'Recommended',
        'Language' => 'Language',
        'Automatic' => 'Automatic',
        'Configuration' => 'Configuration',
        'Backups' => 'Backups',
        'History' => 'History',
        'FileManager' => 'File Manager',
        'ViewLogs' => 'View Logs',
        'Download' => 'Download',
        'Delete' => 'Delete',
        'Size' => 'Size',
        'Date' => 'Date',
        'Type' => 'Type',
        'Never' => 'Never',
        'NotScheduled' => 'Not scheduled',
        'About' => 'About',
        'ModuleVersion' => 'Module Version',
        'ModuleAuthor' => 'Author',
        'ModuleLicense' => 'License',
        'ModuleDescription' => 'Description',
        'ModuleFeatures' => 'Features',
        'ModuleSupport' => 'Support',
        'ModuleWebsite' => 'Website',
        'ModuleDocumentation' => 'Documentation',
        'General' => 'General',
        'Permissions' => 'Permissions',
        'RootPath' => 'FileManager Root Path',
        'LanguageForUI' => 'Language for interface and logs',
        'FileLimit' => 'File limit per folder',
        'AllowDelete' => 'Allow file deletion',
        'AllowUpload' => 'Allow file upload',
        'ShowDeleteButton' => 'Show delete button',
        'AllowProtectedActions' => 'Allow actions on protected files',
        'ProtectedWarning' => 'WARNING: Allows deleting critical system files',
        'AllowedFileTypes' => 'Allowed File Types',
        'SaveConfiguration' => 'Save Configuration',
        'SelectAll' => 'All',
        'DeselectAll' => 'None',
        'SecurityTip' => 'Tip:',
        'SecurityTipText' => 'Select only the extensions you really need for better security',
        'PersonalizeFileManager' => 'Customize FileManager behavior',
        'AvailableBackups' => 'Available Backups',
        'ActivityLog' => 'Activity Log',
        'CurrentStatus' => 'Current Status',
        'Status' => 'Status',
        'Frequency' => 'Frequency',
        'LastBackup' => 'Last Backup',
        'NextBackup' => 'Next Backup',
        'TimeRemaining' => 'Time Remaining',
        'BackupProgress' => 'Backup Progress',
        'BackupLog' => 'Backup Log',
        'Active' => 'Active',
        'Inactive' => 'Inactive',
        'Daily' => 'Daily',
        'Weekly' => 'Weekly',
        'Monthly' => 'Monthly',
        'DatabaseOnly' => 'Database Only',
        'FilesOnly' => 'Files Only',
        'Complete' => 'Complete',
        'BackupType' => 'Backup Type',
        'ExecutionTime' => 'Execution Time (Server Clock)',
        'EnableCronJob' => 'Enable Cron Job',
        'LatestRecords' => 'Latest 100 records',
        'SavedSuccessfully' => 'Configuration saved successfully',
        'ErrorInvalidPath' => 'Error: The specified path does not exist or does not have write permissions',
        'CompleteActivityLog' => 'Complete FileManager activity log',
        'TotalRecords' => 'Total Records',
        'Today' => 'Today',
        'SearchFilters' => 'Search Filters',
        'ActionType' => 'Action Type',
        'AllActions' => 'All actions',
        'AllUsers' => 'All Users',
        'From' => 'From',
        'To' => 'To',
        'Search' => 'Search',
        'Clear' => 'Clear',
        'Refresh' => 'Refresh',
        'DeleteAll' => 'Delete All',
        'DateTime' => 'Date/Time',
        'User' => 'User',
        'Action' => 'Action',
        'FilePath' => 'File/Path',
        'Details' => 'Details',
        'Actions' => 'Actions',
        'NoRecordsAvailable' => 'No records available',
        'ActivityRecordsWillAppear' => 'Activity records will appear here',
        'Unknown' => 'Unknown',
        'Never' => 'Never',
        'NotScheduled' => 'Not scheduled',
    ),
    'fr_FR' => array(
        'BackupDatabase' => 'Base de données',
        'BackupDatabaseDesc' => 'Exporte toutes les tables SQL en format compressé',
        'BackupFiles' => 'Fichiers',
        'BackupFilesDesc' => 'Compresse tous les fichiers de l\'installation',
        'BackupComplete' => 'Sauvegarde complète',
        'BackupCompleteDesc' => 'Base de données + Fichiers dans un ZIP',
        'BackupEnabled' => 'Actif',
        'BackupDisabled' => 'Inactif',
        'Recommended' => 'Recommandé',
        'Language' => 'Langue',
        'Automatic' => 'Automatique',
        'Configuration' => 'Configuration',
        'Backups' => 'Sauvegardes',
        'History' => 'Historique',
        'FileManager' => 'Gestionnaire de fichiers',
        'ViewLogs' => 'Voir les journaux',
        'Download' => 'Télécharger',
        'Delete' => 'Supprimer',
        'Size' => 'Taille',
        'Date' => 'Date',
        'Type' => 'Type',
        'Never' => 'Jamais',
        'NotScheduled' => 'Non programmé',
        'About' => 'À propos',
        'ModuleVersion' => 'Version du Module',
        'ModuleAuthor' => 'Auteur',
        'ModuleLicense' => 'Licence',
        'ModuleDescription' => 'Description',
        'ModuleFeatures' => 'Fonctionnalités',
        'ModuleSupport' => 'Support',
        'ModuleWebsite' => 'Site Web',
        'ModuleDocumentation' => 'Documentation',
        'General' => 'Général',
        'Permissions' => 'Permissions',
        'RootPath' => 'Chemin racine du FileManager',
        'LanguageForUI' => 'Langue pour l\'interface et les journaux',
        'FileLimit' => 'Limite de fichiers par dossier',
        'AllowDelete' => 'Autoriser la suppression de fichiers',
        'AllowUpload' => 'Autoriser le téléchargement de fichiers',
        'ShowDeleteButton' => 'Afficher le bouton de suppression',
        'AllowProtectedActions' => 'Autoriser les actions sur les fichiers protégés',
        'ProtectedWarning' => 'AVERTISSEMENT: Permet de supprimer les fichiers système critiques',
        'AllowedFileTypes' => 'Types de fichiers autorisés',
        'SaveConfiguration' => 'Enregistrer la Configuration',
        'SelectAll' => 'Tout',
        'DeselectAll' => 'Aucun',
        'SecurityTip' => 'Astuce:',
        'SecurityTipText' => 'Sélectionnez uniquement les extensions dont vous avez vraiment besoin pour une meilleure sécurité',
        'PersonalizeFileManager' => 'Personnaliser le comportement de FileManager',
        'AvailableBackups' => 'Sauvegardes disponibles',
        'ActivityLog' => 'Journal d\'Activité',
        'CurrentStatus' => 'État Actuel',
        'Status' => 'État',
        'Frequency' => 'Fréquence',
        'LastBackup' => 'Dernière Sauvegarde',
        'NextBackup' => 'Prochaine Sauvegarde',
        'TimeRemaining' => 'Temps Restant',
        'BackupProgress' => 'Progression de la Sauvegarde',
        'BackupLog' => 'Journal de la Sauvegarde',
        'Active' => 'Actif',
        'Inactive' => 'Inactif',
        'Daily' => 'Quotidienne',
        'Weekly' => 'Hebdomadaire',
        'Monthly' => 'Mensuelle',
        'DatabaseOnly' => 'Base de Données uniquement',
        'FilesOnly' => 'Fichiers uniquement',
        'Complete' => 'Complète',
        'BackupType' => 'Type de Sauvegarde',
        'ExecutionTime' => 'Heure d\'Exécution (Horloge du Serveur)',
        'EnableCronJob' => 'Activer le Cron Job',
        'LatestRecords' => 'Derniers 100 enregistrements',
        'SavedSuccessfully' => 'Configuration enregistrée avec succès',
        'ErrorInvalidPath' => 'Erreur: Le chemin spécifié n\'existe pas ou n\'a pas de permissions d\'écriture',
        'CompleteActivityLog' => 'Journal complet d\'activité du FileManager',
        'TotalRecords' => 'Total Enregistrements',
        'Today' => 'Aujourd\'hui',
        'SearchFilters' => 'Filtres de Recherche',
        'ActionType' => 'Type d\'Action',
        'AllActions' => 'Toutes les actions',
        'AllUsers' => 'Tous les Utilisateurs',
        'From' => 'De',
        'To' => 'À',
        'Search' => 'Rechercher',
        'Clear' => 'Effacer',
        'Refresh' => 'Actualiser',
        'DeleteAll' => 'Tout Supprimer',
        'DateTime' => 'Date/Heure',
        'User' => 'Utilisateur',
        'Action' => 'Action',
        'FilePath' => 'Fichier/Chemin',
        'Details' => 'Détails',
        'Actions' => 'Actions',
        'NoRecordsAvailable' => 'Aucun enregistrement disponible',
        'ActivityRecordsWillAppear' => 'Les enregistrements d\'activité apparaîtront ici',
        'Unknown' => 'Inconnu',
        'Never' => 'Jamais',
        'NotScheduled' => 'Non programmé',
    ),
    'de_DE' => array(
        'BackupDatabase' => 'Datenbank',
        'BackupDatabaseDesc' => 'Exportiert alle SQL-Tabellen im komprimierten Format',
        'BackupFiles' => 'Dateien',
        'BackupFilesDesc' => 'Komprimiert alle Installationsdateien',
        'BackupComplete' => 'Vollständiges Backup',
        'BackupCompleteDesc' => 'Datenbank + Dateien in einer ZIP',
        'BackupEnabled' => 'Aktiv',
        'BackupDisabled' => 'Inaktiv',
        'Recommended' => 'Empfohlen',
        'Language' => 'Sprache',
        'Automatic' => 'Automatisch',
        'Configuration' => 'Konfiguration',
        'Backups' => 'Sicherungen',
        'History' => 'Verlauf',
        'FileManager' => 'Dateimanager',
        'ViewLogs' => 'Protokolle anzeigen',
        'Download' => 'Herunterladen',
        'Delete' => 'Löschen',
        'Size' => 'Größe',
        'Date' => 'Datum',
        'Type' => 'Typ',
        'Never' => 'Nie',
        'NotScheduled' => 'Nicht geplant',
        'About' => 'Über',
        'ModuleVersion' => 'Modulversion',
        'ModuleAuthor' => 'Autor',
        'ModuleLicense' => 'Lizenz',
        'ModuleDescription' => 'Beschreibung',
        'ModuleFeatures' => 'Funktionen',
        'ModuleSupport' => 'Support',
        'ModuleWebsite' => 'Webseite',
        'ModuleDocumentation' => 'Dokumentation',
        'General' => 'Allgemein',
        'Permissions' => 'Berechtigungen',
        'RootPath' => 'FileManager-Stammpfad',
        'LanguageForUI' => 'Sprache für Oberfläche und Protokolle',
        'FileLimit' => 'Dateilimit pro Ordner',
        'AllowDelete' => 'Löschen von Dateien erlauben',
        'AllowUpload' => 'Hochladen von Dateien erlauben',
        'ShowDeleteButton' => 'Lösch-Schaltfläche anzeigen',
        'AllowProtectedActions' => 'Aktionen auf geschützte Dateien erlauben',
        'ProtectedWarning' => 'WARNUNG: Ermöglicht das Löschen kritischer Systemdateien',
        'AllowedFileTypes' => 'Erlaubte Dateitypen',
        'SaveConfiguration' => 'Konfiguration Speichern',
        'SelectAll' => 'Alle',
        'DeselectAll' => 'Keine',
        'SecurityTip' => 'Tipp:',
        'SecurityTipText' => 'Wählen Sie nur die Erweiterungen aus, die Sie wirklich benötigen, für bessere Sicherheit',
        'PersonalizeFileManager' => 'FileManager-Verhalten anpassen',
        'AvailableBackups' => 'Verfügbare Sicherungen',
        'ActivityLog' => 'Aktivitätsprotokoll',
        'CurrentStatus' => 'Aktueller Status',
        'Status' => 'Status',
        'Frequency' => 'Häufigkeit',
        'LastBackup' => 'Letzte Sicherung',
        'NextBackup' => 'Nächste Sicherung',
        'TimeRemaining' => 'Verbleibende Zeit',
        'BackupProgress' => 'Sicherungsfortschritt',
        'BackupLog' => 'Sicherungsprotokoll',
        'Active' => 'Aktiv',
        'Inactive' => 'Inaktiv',
        'Daily' => 'Täglich',
        'Weekly' => 'Wöchentlich',
        'Monthly' => 'Monatlich',
        'DatabaseOnly' => 'Nur Datenbank',
        'FilesOnly' => 'Nur Dateien',
        'Complete' => 'Vollständig',
        'BackupType' => 'Sicherungstyp',
        'ExecutionTime' => 'Ausführungszeit (Server-Uhr)',
        'EnableCronJob' => 'Cron Job aktivieren',
        'LatestRecords' => 'Neueste 100 Einträge',
        'SavedSuccessfully' => 'Konfiguration erfolgreich gespeichert',
        'ErrorInvalidPath' => 'Fehler: Der angegebene Pfad existiert nicht oder hat keine Schreibberechtigungen',
        'CompleteActivityLog' => 'Vollständiges FileManager-Aktivitätsprotokoll',
        'TotalRecords' => 'Gesamt Einträge',
        'Today' => 'Heute',
        'SearchFilters' => 'Suchfilter',
        'ActionType' => 'Aktionstyp',
        'AllActions' => 'Alle Aktionen',
        'AllUsers' => 'Alle Benutzer',
        'From' => 'Von',
        'To' => 'Bis',
        'Search' => 'Suchen',
        'Clear' => 'Löschen',
        'Refresh' => 'Aktualisieren',
        'DeleteAll' => 'Alle Löschen',
        'DateTime' => 'Datum/Zeit',
        'User' => 'Benutzer',
        'Action' => 'Aktion',
        'FilePath' => 'Datei/Pfad',
        'Details' => 'Details',
        'Actions' => 'Aktionen',
        'NoRecordsAvailable' => 'Keine Einträge verfügbar',
        'ActivityRecordsWillAppear' => 'Aktivitätseinträge werden hier erscheinen',
        'Unknown' => 'Unbekannt',
        'Never' => 'Nie',
        'NotScheduled' => 'Nicht geplant',
    ),
);

// Determinar el idioma a usar
$use_lang = $fm_lang;
if ($use_lang === 'auto' || !isset($fm_translations[$use_lang])) {
    // Mapear idioma de Dolibarr
    $dol_lang = $langs->defaultlang;
    if (strpos($dol_lang, 'es') === 0) $use_lang = 'es_ES';
    elseif (strpos($dol_lang, 'en') === 0) $use_lang = 'en_US';
    elseif (strpos($dol_lang, 'fr') === 0) $use_lang = 'fr_FR';
    elseif (strpos($dol_lang, 'de') === 0) $use_lang = 'de_DE';
    else $use_lang = 'es_ES';
}

// Cargar traducciones en $langs
foreach ($fm_translations[$use_lang] as $key => $value) {
    $langs->tab_translate[$key] = $value;
}

// Función helper para traducciones de FileManager
function fmTrans($key) {
    global $langs;
    return isset($langs->tab_translate[$key]) ? $langs->tab_translate[$key] : $key;
}

// Si no existe configuración, usar valores por defecto
if (empty($config)) {
    $config = array(
        'FILEMANAGER_ROOT_PATH' => DOL_DOCUMENT_ROOT,
        'FILEMANAGER_ALLOW_DELETE' => 1,
        'FILEMANAGER_ALLOW_UPLOAD' => 1,
        'FILEMANAGER_SHOW_DELETE_BUTTON' => 1,
        'FILEMANAGER_MAX_FILES' => 100,
        'FILEMANAGER_ALLOWED_EXTENSIONS' => array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar'),
        'FILEMANAGER_ALLOW_PROTECTED_ACTIONS' => 0
    );
}

// Guardar valor antiguo para comparar después
$old_protected_actions = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;

// Procesar formulario si se envió
if ($_POST) {
    // Verificar token CSRF
    if (function_exists('checkToken')) {
        $token_ok = checkToken();
        if (!$token_ok) {
            accessforbidden();
        }
    }
    
    if (isset($_POST['save_config'])) {
        $config_updated = false;
        
        // Procesar ruta raíz
        if (isset($_POST['root_path']) && !empty($_POST['root_path'])) {
            $new_path = trim($_POST['root_path']);
            if (is_dir($new_path) && is_writable($new_path)) {
                $config['FILEMANAGER_ROOT_PATH'] = $new_path;
                $config_updated = true;
            } else {
                setEventMessages('Error: La ruta especificada no existe o no tiene permisos de escritura', null, 'errors');
            }
        }
        
        // Procesar extensiones permitidas
        if (isset($_POST['allowed_extensions']) && is_array($_POST['allowed_extensions'])) {
            $extensions = array_map('strtolower', $_POST['allowed_extensions']);
            $extensions = array_unique($extensions);
            $config['FILEMANAGER_ALLOWED_EXTENSIONS'] = $extensions;
            $config_updated = true;
        } else {
            $config['FILEMANAGER_ALLOWED_EXTENSIONS'] = array();
            $config_updated = true;
        }
        
        // Procesar idioma
        if (isset($_POST['filemanager_language'])) {
            $config['FILEMANAGER_LANGUAGE'] = trim($_POST['filemanager_language']);
            $config_updated = true;
        }
        
        // Procesar otras configuraciones
        $config['FILEMANAGER_ALLOW_DELETE'] = isset($_POST['allow_delete']) ? 1 : 0;
        $config['FILEMANAGER_ALLOW_UPLOAD'] = isset($_POST['allow_upload']) ? 1 : 0;
        $config['FILEMANAGER_SHOW_DELETE_BUTTON'] = isset($_POST['show_delete_button']) ? 1 : 0;
        $config['FILEMANAGER_MAX_FILES'] = isset($_POST['max_files']) ? intval($_POST['max_files']) : 100;
        // Detectar si el checkbox está marcado
        // IMPORTANTE: Un checkbox solo envía valor si está marcado. Si no está marcado, no aparece en $_POST
        $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] = isset($_POST['allow_protected_actions']) ? 1 : 0;
        
        // Guardar todas las configuraciones en la base de datos usando SQL directo
        if ($db) {
            $entity = isset($conf->entity) ? $conf->entity : 0;
            
            // Función helper para guardar constante
            $saveConst = function($name, $value) use ($db, $entity) {
                $sql_check = "SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "const WHERE name = '" . $db->escape($name) . "' AND entity = " . (int)$entity;
                $res_check = $db->query($sql_check);
                $obj_check = $db->fetch_object($res_check);
                
                if ($obj_check && $obj_check->cnt > 0) {
                    // Existe, hacer UPDATE
                    $sql_update = "UPDATE " . MAIN_DB_PREFIX . "const SET value = '" . $db->escape((string)$value) . "' WHERE name = '" . $db->escape($name) . "' AND entity = " . (int)$entity;
                    return $db->query($sql_update);
                } else {
                    // No existe, hacer INSERT
                    $sql_insert = "INSERT INTO " . MAIN_DB_PREFIX . "const (name, value, type, note, visible, entity) VALUES ('" . $db->escape($name) . "', '" . $db->escape((string)$value) . "', 'chaine', '', 0, " . (int)$entity . ")";
                    return $db->query($sql_insert);
                }
            };
            
            // Guardar todas las configuraciones
            $saveConst("FILEMANAGER_ALLOW_DELETE", $config['FILEMANAGER_ALLOW_DELETE']);
            $saveConst("FILEMANAGER_ALLOW_UPLOAD", $config['FILEMANAGER_ALLOW_UPLOAD']);
            $saveConst("FILEMANAGER_SHOW_DELETE_BUTTON", $config['FILEMANAGER_SHOW_DELETE_BUTTON']);
            $saveConst("FILEMANAGER_MAX_FILES", $config['FILEMANAGER_MAX_FILES']);
            
            // Guardar FILEMANAGER_ALLOW_PROTECTED_ACTIONS - FORZAR GUARDADO
            $allow_protected_value = $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'];
            $saveConst("FILEMANAGER_ALLOW_PROTECTED_ACTIONS", $allow_protected_value);
            
            if (isset($config['FILEMANAGER_ROOT_PATH'])) {
                $saveConst("FILEMANAGER_ROOT_PATH", $config['FILEMANAGER_ROOT_PATH']);
            }
            
            if (isset($config['FILEMANAGER_ALLOWED_EXTENSIONS']) && is_array($config['FILEMANAGER_ALLOWED_EXTENSIONS'])) {
                $saveConst("FILEMANAGER_ALLOWED_EXTENSIONS", implode(',', $config['FILEMANAGER_ALLOWED_EXTENSIONS']));
            }
            
            // Guardar idioma
            if (isset($config['FILEMANAGER_LANGUAGE'])) {
                $saveConst("FILEMANAGER_LANGUAGE", $config['FILEMANAGER_LANGUAGE']);
            }
        }
        
        if ($config_updated) {
            setEventMessages(fmTrans('SavedSuccessfully'), null, 'mesgs');
            
            // Verificar si cambió FILEMANAGER_ALLOW_PROTECTED_ACTIONS
            $old_value = $old_protected_actions;
            $new_value = isset($_POST['allow_protected_actions']) ? 1 : 0;
            
            // Si el valor cambió, marcar para recargar index.php
            if ($old_value != $new_value) {
                $_SESSION['filemanager_reload_index'] = true;
                $_SESSION['filemanager_config_saved'] = time();
            }
        }
    }
    
}


// Iniciar página
llxHeader('', 'FileManager - ' . $langs->trans('Configuration'));

// Incluir CSS de FileManager
print '<link rel="stylesheet" href="' . dol_buildpath('/custom/filemanager/css/filemanager.css', 1) . '">';

// ========== MODAL DE ADVERTENCIA PARA PRIMERA VEZ ==========
print '<script type="text/javascript">' . "\n";
print '(function() {' . "\n";
print '    // Verificar si ya vio la advertencia (usando localStorage)' . "\n";
print '    var warningKey = "filemanager_warning_accepted";' . "\n";
print '    var hasAcceptedWarning = localStorage.getItem(warningKey);' . "\n";
print '    ' . "\n";
print '    if (!hasAcceptedWarning) {' . "\n";
print '        // Esperar a que SweetAlert esté cargado' . "\n";
print '        var checkSwal = setInterval(function() {' . "\n";
print '            if (typeof Swal !== "undefined") {' . "\n";
print '                clearInterval(checkSwal);' . "\n";
print '                ' . "\n";
print '                // Mostrar modal de advertencia' . "\n";
print '                Swal.fire({' . "\n";
print '                    title: "⚠️ ADVERTENCIA IMPORTANTE",' . "\n";
print '                    html: `' . "\n";
print '                        <div style="text-align: left; font-size: 15px; line-height: 1.8;">' . "\n";
print '                            <p style="color: #dc3545; font-weight: bold; margin-bottom: 15px; font-size: 16px;">' . "\n";
print '                                ⚠️ RIESGOS AL ELIMINAR ARCHIVOS DE DOLIBARR' . "\n";
print '                            </p>' . "\n";
print '                            <p style="margin-bottom: 12px;">' . "\n";
print '                                <strong>El FileManager le permite eliminar archivos y carpetas de Dolibarr.</strong>' . "\n";
print '                            </p>' . "\n";
print '                            <p style="margin-bottom: 12px; color: #dc3545;">' . "\n";
print '                                <strong>⚠️ ADVERTENCIA:</strong> Eliminar archivos incorrectos puede causar:' . "\n";
print '                            </p>' . "\n";
print '                            <ul style="margin-left: 20px; margin-bottom: 15px; color: #6c757d;">' . "\n";
print '                                <li>Pérdida permanente de datos</li>' . "\n";
print '                                <li>Corrupción de la base de datos</li>' . "\n";
print '                                <li>Inestabilidad del sistema</li>' . "\n";
print '                                <li>Pérdida de funcionalidades de Dolibarr</li>' . "\n";
print '                                <li>Necesidad de restaurar desde backup</li>' . "\n";
print '                            </ul>' . "\n";
print '                            <p style="margin-bottom: 12px; color: #495057;">' . "\n";
print '                                <strong>✅ RECOMENDACIONES:</strong>' . "\n";
print '                            </p>' . "\n";
print '                            <ul style="margin-left: 20px; margin-bottom: 15px; color: #6c757d;">' . "\n";
print '                                <li>Realice backups regulares antes de eliminar archivos</li>' . "\n";
print '                                <li>Verifique cuidadosamente qué archivos está eliminando</li>' . "\n";
print '                                <li>Evite eliminar archivos del sistema (carpetas: core, includes, conf, install)</li>' . "\n";
print '                                <li>Use la papelera de reciclaje para poder restaurar archivos</li>' . "\n";
print '                            </ul>' . "\n";
print '                            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 15px; border-radius: 4px;">' . "\n";
print '                                <p style="margin: 0; color: #856404; font-weight: bold;">' . "\n";
print '                                    ⚠️ Usted es responsable de las acciones realizadas con este módulo.' . "\n";
print '                                </p>' . "\n";
print '                            </div>' . "\n";
print '                            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;">' . "\n";
print '                                <label style="display: flex; align-items: center; cursor: pointer; font-size: 14px; color: #495057;">' . "\n";
print '                                    <input type="checkbox" id="dontShowAgain" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">' . "\n";
print '                                    <span>No volver a mostrar este mensaje</span>' . "\n";
print '                                </label>' . "\n";
print '                            </div>' . "\n";
print '                        </div>' . "\n";
print '                    `,' . "\n";
print '                    icon: "warning",' . "\n";
print '                    iconColor: "#dc3545",' . "\n";
print '                    showCancelButton: true,' . "\n";
print '                    confirmButtonText: "Entiendo los riesgos y deseo continuar",' . "\n";
print '                    cancelButtonText: "Cancelar y salir",' . "\n";
print '                    confirmButtonColor: "#dc3545",' . "\n";
print '                    cancelButtonColor: "#6c757d",' . "\n";
print '                    allowOutsideClick: false,' . "\n";
print '                    allowEscapeKey: false,' . "\n";
print '                    reverseButtons: true,' . "\n";
print '                    width: "700px",' . "\n";
print '                    customClass: {' . "\n";
print '                        popup: "swal2-popup-large"' . "\n";
print '                    },' . "\n";
print '                    didOpen: function() {' . "\n";
print '                        // Asegurar que el checkbox esté disponible' . "\n";
print '                        var checkbox = document.getElementById("dontShowAgain");' . "\n";
print '                        if (checkbox) {' . "\n";
print '                            checkbox.checked = false;' . "\n";
print '                        }' . "\n";
print '                    }' . "\n";
print '                }).then((result) => {' . "\n";
print '                    if (result.isConfirmed) {' . "\n";
print '                        // Verificar si marcó el checkbox' . "\n";
print '                        var checkbox = document.getElementById("dontShowAgain");' . "\n";
print '                        var dontShowAgain = checkbox ? checkbox.checked : false;' . "\n";
print '                        ' . "\n";
print '                        if (dontShowAgain) {' . "\n";
print '                            // Usuario aceptó y marcó "no volver a mostrar" - guardar en localStorage' . "\n";
print '                            localStorage.setItem(warningKey, "true");' . "\n";
print '                        }' . "\n";
print '                        // Si no marcó el checkbox, no guardar (mostrará el mensaje cada vez)' . "\n";
print '                    } else {' . "\n";
print '                        // Usuario canceló - redirigir a inicio de Dolibarr' . "\n";
print '                        window.location.href = "../../../index.php";' . "\n";
print '                    }' . "\n";
print '                });' . "\n";
print '            }' . "\n";
print '        }, 100);' . "\n";
print '    }' . "\n";
print '})();' . "\n";
print '</script>' . "\n";

// Título de la página
print load_fiche_titre('<i class="fas fa-cog"></i> ' . $langs->trans('FileManager') . ' - ' . $langs->trans('Configuration'), '', '');


// Obtener lista de usuarios únicos para el filtro (antes de usarla)
$users = array();
if ($db) {
    $sql_users = "SELECT DISTINCT user_name FROM llx_filemanager_logs ORDER BY user_name";
        $resql_users = $db->query($sql_users);
        if ($resql_users) {
            while ($obj = $db->fetch_object($resql_users)) {
                    $users[] = $obj->user_name;
                }
    }
}

// Estilos CSS modernos para el setup
print '<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
@keyframes blink {
    0%, 50%, 100% { opacity: 1; }
    25%, 75% { opacity: 0.3; }
}
.setup-nav {
    display: flex;
    gap: 8px;
    padding: 15px;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-radius: 12px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}
.setup-nav-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    flex: 1;
    justify-content: center;
    min-width: 130px;
}
.setup-nav-btn.inactive {
    background: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.7);
}
.setup-nav-btn.inactive:hover {
    background: rgba(255,255,255,0.2);
    color: white;
}
.setup-nav-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}
.setup-nav-btn.go-fm {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}
.setup-nav-btn.go-fm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(56, 239, 125, 0.4);
}
.setup-nav-btn i {
    font-size: 16px;
}
@media (max-width: 600px) {
    .setup-nav {
        flex-direction: column;
    }
    .setup-nav-btn {
        width: 100%;
    }
}
</style>';

// DEFINIR switchSetupTab ANTES de los botones para que esté disponible
print '<script>';
print '// Función para cambiar de pestaña - debe estar disponible antes de los botones';
print 'if (typeof window.switchSetupTab === "undefined") {';
print '    window.switchSetupTab = function switchSetupTab(tabName) {';
print '        console.log("Cambiando a pestaña:", tabName);';
print '        var url = new URL(window.location);';
print '        url.searchParams.set("tab", tabName);';
print '        window.history.pushState({}, "", url);';
print '        var configTab = document.getElementById("configTab");';
print '        var backupTab = document.getElementById("backupTab");';
print '        var logsTab = document.getElementById("logsTab");';
print '        var aboutTab = document.getElementById("aboutTab");';
print '        var configContent = document.getElementById("configContent");';
print '        var backupContent = document.getElementById("backupContent");';
print '        var logsContent = document.getElementById("logsContent");';
print '        var aboutContent = document.getElementById("aboutContent");';
print '        if (!configTab || !backupTab || !logsTab || !maintenanceTab || !aboutTab) {';
print '            console.error("No se encontraron los elementos de pestañas");';
print '            return false;';
print '        }';
print '        [configTab, backupTab, logsTab, maintenanceTab, aboutTab].forEach(function(tab) {';
print '            if (tab) {';
print '                tab.classList.remove("active");';
print '                tab.classList.add("inactive");';
print '            }';
print '        });';
print '        if (configContent) configContent.style.display = "none";';
print '        if (backupContent) backupContent.style.display = "none";';
print '        if (logsContent) logsContent.style.display = "none";';
print '        if (aboutContent) aboutContent.style.display = "none";';
print '        if (tabName === "config" && configTab && configContent) {';
print '            configTab.classList.remove("inactive");';
print '            configTab.classList.add("active");';
print '            configContent.style.display = "block";';
print '        } else if (tabName === "backup" && backupTab && backupContent) {';
print '            backupTab.classList.remove("inactive");';
print '            backupTab.classList.add("active");';
print '            backupContent.style.display = "block";';
print '        } else if (tabName === "logs" && logsTab && logsContent) {';
print '            logsTab.classList.remove("inactive");';
print '            logsTab.classList.add("active");';
print '            logsContent.style.display = "block";';
print '        } else if (tabName === "about" && aboutTab && aboutContent) {';
print '            aboutTab.classList.remove("inactive");';
print '            aboutTab.classList.add("active");';
print '            aboutContent.style.display = "block";';
print '        }';
print '        return true;';
print '    };';
print '}';
print '</script>';

// Navegación moderna
print '<div class="setup-nav">';
print '<button id="configTab" class="setup-nav-btn active" onclick="switchSetupTab(\'config\')"><i class="fas fa-cog"></i> <span>' . $langs->trans('Configuration') . '</span></button>';
print '<button id="backupTab" class="setup-nav-btn inactive" onclick="switchSetupTab(\'backup\')"><i class="fas fa-shield-alt"></i> <span>' . $langs->trans('Backups') . '</span></button>';
print '<button id="logsTab" class="setup-nav-btn inactive" onclick="switchSetupTab(\'logs\')"><i class="fas fa-history"></i> <span>' . $langs->trans('History') . '</span></button>';
print '<button id="maintenanceTab" class="setup-nav-btn inactive" onclick="switchSetupTab(\'maintenance\')"><i class="fas fa-wrench"></i> <span>' . $langs->trans('Maintenance') . '</span></button>';
print '<button id="aboutTab" class="setup-nav-btn inactive" onclick="switchSetupTab(\'about\')"><i class="fas fa-info-circle"></i> <span>' . $langs->trans('About') . '</span></button>';
print '<button onclick="window.open(\'../index.php\', \'_blank\')" class="setup-nav-btn go-fm"><i class="fas fa-folder-open"></i> <span>' . $langs->trans('FileManager') . '</span></button>';
print '</div>';

// ========== PESTAÑA CONFIGURACIÓN ==========
print '<div id="configContent">';

// Header de la sección
print '<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 25px; color: white;">';
print '<div style="display: flex; align-items: center; gap: 15px;">';
print '<div style="width: 50px; height: 50px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">⚙️</div>';
print '<div>';
print '<h2 style="margin: 0; font-size: 22px; font-weight: 600;">' . fmTrans('Configuration') . '</h2>';
print '<p style="margin: 5px 0 0 0; opacity: 0.85; font-size: 14px;">' . fmTrans('PersonalizeFileManager') . '</p>';
print '</div>';
print '</div>';
print '</div>';

print '<form method="POST" action="">';
print '<input type="hidden" name="token" value="' . $token . '">';

// Grid de configuraciones
print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px;">';

// Card: Configuración General
print '<div style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; overflow: hidden;">';
print '<div style="padding: 15px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">';
print '<h3 style="margin: 0; font-size: 15px; font-weight: 600; color: #111827; display: flex; align-items: center; gap: 8px;"><span>📂</span> ' . fmTrans('General') . '</h3>';
print '</div>';
print '<div style="padding: 20px;">';

// Ruta raíz
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #374151;">' . fmTrans('RootPath') . '</label>';
print '<div style="display: flex; gap: 8px;">';
print '<input type="text" name="root_path" value="' . htmlspecialchars($config['FILEMANAGER_ROOT_PATH']) . '" id="rootPathInput" style="flex: 1; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; font-family: monospace;">';
print '<button type="button" onclick="autoDetectPath()" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; white-space: nowrap;">🔍 Auto</button>';
print '</div>';
print '</div>';

// Selector de idioma
$current_lang = isset($config['FILEMANAGER_LANGUAGE']) ? $config['FILEMANAGER_LANGUAGE'] : 'auto';
$available_langs = array(
    'auto' => '🌐 ' . t('automatic') . ' (Dolibarr)',
    'es' => '🇪🇸 Español',
    'en' => '🇬🇧 English',
    'fr' => '🇫🇷 Français',
    'de' => '🇩🇪 Deutsch'
);
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #374151;">' . t('language') . '</label>';
print '<select name="filemanager_language" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white;">';
foreach ($available_langs as $lang_code => $lang_name) {
    $selected = ($current_lang === $lang_code) ? ' selected' : '';
    print '<option value="' . $lang_code . '"' . $selected . '>' . $lang_name . '</option>';
}
print '</select>';
print '<p style="margin: 6px 0 0 0; font-size: 12px; color: #6b7280;">' . $langs->trans('LanguageForUI') . '</p>';
print '</div>';

// Límite de archivos
print '<div>';
print '<label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #374151;">' . $langs->trans('FileLimit') . '</label>';
print '<input type="number" name="max_files" value="' . intval($config['FILEMANAGER_MAX_FILES']) . '" min="1" max="1000" style="width: 120px; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">';
print '</div>';

print '</div>';
print '</div>';

// Card: Permisos
print '<div style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; overflow: hidden;">';
print '<div style="padding: 15px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">';
print '<h3 style="margin: 0; font-size: 15px; font-weight: 600; color: #111827; display: flex; align-items: center; gap: 8px;"><span>🔒</span> ' . $langs->trans('Permissions') . '</h3>';
print '</div>';
print '<div style="padding: 20px;">';

$permissions = array(
    array('name' => 'allow_delete', 'label' => fmTrans('AllowDelete'), 'value' => $config['FILEMANAGER_ALLOW_DELETE'], 'icon' => '🗑️'),
    array('name' => 'allow_upload', 'label' => fmTrans('AllowUpload'), 'value' => $config['FILEMANAGER_ALLOW_UPLOAD'], 'icon' => '📤'),
    array('name' => 'show_delete_button', 'label' => fmTrans('ShowDeleteButton'), 'value' => $config['FILEMANAGER_SHOW_DELETE_BUTTON'], 'icon' => '👁️'),
);

foreach ($permissions as $perm) {
    $checked = $perm['value'] ? ' checked' : '';
    print '<label style="display: flex; align-items: center; gap: 12px; padding: 12px; margin-bottom: 8px; background: #f9fafb; border-radius: 8px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background=\'#f3f4f6\'" onmouseout="this.style.background=\'#f9fafb\'">';
    print '<input type="checkbox" name="' . $perm['name'] . '" value="1"' . $checked . ' style="width: 18px; height: 18px; accent-color: #10b981;">';
    print '<span style="font-size: 14px; color: #374151;">' . $perm['icon'] . ' ' . $perm['label'] . '</span>';
    print '</label>';
}

// Archivos protegidos (con advertencia)
$protected_checked = $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] ? ' checked' : '';
print '<label style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: #fef2f2; border-radius: 8px; cursor: pointer; border: 1px solid #fecaca;">';
print '<input type="checkbox" name="allow_protected_actions" value="1"' . $protected_checked . ' style="width: 18px; height: 18px; accent-color: #ef4444; margin-top: 2px;">';
print '<div>';
print '<span style="font-size: 14px; color: #991b1b; font-weight: 500;">⚠️ ' . $langs->trans('AllowProtectedActions') . '</span>';
print '<p style="margin: 4px 0 0 0; font-size: 12px; color: #b91c1c;">' . $langs->trans('ProtectedWarning') . '</p>';
print '</div>';
print '</label>';

print '</div>';
print '</div>';

print '</div>'; // Cierre del grid

// Card: Extensiones permitidas (ancho completo)
print '<div style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; overflow: hidden; margin-top: 20px;">';
print '<div style="padding: 15px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">';
print '<h3 style="margin: 0; font-size: 15px; font-weight: 600; color: #111827; display: flex; align-items: center; gap: 8px;"><span>📎</span> ' . $langs->trans('AllowedFileTypes') . '</h3>';
print '<div style="display: flex; gap: 8px;">';
print '<button type="button" onclick="selectAllExtensions()" style="background: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">✅ ' . $langs->trans('SelectAll') . '</button>';
print '<button type="button" onclick="deselectAllExtensions()" style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">❌ ' . $langs->trans('DeselectAll') . '</button>';
print '</div>';
print '</div>';
print '<div style="padding: 20px;">';

// Generar checkboxes para extensiones
$allExtensions = array_unique($config['FILEMANAGER_ALLOWED_EXTENSIONS']);
sort($allExtensions);

print '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; max-height: 280px; overflow-y: auto; padding: 5px;">';

foreach ($allExtensions as $ext) {
    $ext = strtolower(trim($ext));
    if (empty($ext)) continue;
    
    $checked = in_array($ext, $config['FILEMANAGER_ALLOWED_EXTENSIONS']) ? ' checked' : '';
    
    print '<label style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f9fafb; border-radius: 6px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent;" onmouseover="this.style.background=\'#eff6ff\';this.style.borderColor=\'#3b82f6\'" onmouseout="this.style.background=\'#f9fafb\';this.style.borderColor=\'transparent\'">';
    print '<input type="checkbox" name="allowed_extensions[]" value="' . htmlspecialchars($ext) . '"' . $checked . ' style="width: 16px; height: 16px; accent-color: #3b82f6;">';
    print '<span style="font-size: 13px; font-weight: 500; color: #374151;">.' . htmlspecialchars($ext) . '</span>';
        print '</label>';
    }
    
print '</div>';

print '<p style="margin: 15px 0 0 0; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">💡 <strong>' . $langs->trans('SecurityTip') . '</strong> ' . $langs->trans('SecurityTipText') . '</p>';
print '</div>';
print '</div>';

// Botón guardar
print '<div style="margin-top: 25px; text-align: center;">';
print '<button type="submit" name="save_config" style="display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 14px 32px; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: 600; transition: all 0.2s; box-shadow: 0 4px 12px rgba(16,185,129,0.3);" onmouseover="this.style.transform=\'translateY(-2px)\';this.style.boxShadow=\'0 6px 16px rgba(16,185,129,0.4)\'" onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 12px rgba(16,185,129,0.3)\'">';
print '<span style="font-size: 18px;">💾</span> ' . $langs->trans('SaveConfiguration');
print '</button>';
print '</div>';

print '</form>';
print '</div>';

// Contenido de backups (oculto por defecto)
print '<div id="backupContent" style="display: none;">';

// Estado del sistema de backup (siempre disponible con ZipWriter PHP puro)
$zipAvailable = true; // ZipWriter funciona sin dependencias externas

// Cargar backups existentes
// NOTA: Solo mostrar archivos .zip completados (excluir los que tienen prefijo "incomplete_")
$backupDir = DOL_DOCUMENT_ROOT.'/custom/filemanager/backups';
dol_mkdir($backupDir);
$backups = array();
if (is_dir($backupDir)) {
    foreach (glob($backupDir.'/*.zip') as $f) {
        $basename = basename($f);
        // Excluir archivos incompletos (en progreso) y temporales
        if (strpos($basename, 'incomplete_') === 0 || substr($f, -4) === '.tmp') {
            continue;
        }
        $backups[] = array(
            'file' => $basename,
            'size' => filesize($f),
            'time' => filemtime($f)
        );
    }
    usort($backups, function($a,$b){ return $b['time'] <=> $a['time']; });
}


// Botones de acción según especificaciones del TXT
$createUrl = dol_buildpath('/custom/filemanager/scripts/create_backup.php', 1);
$downloadUrl = dol_buildpath('/custom/filemanager/scripts/download_backup.php', 1);
$disabled = $zipAvailable ? '' : ' disabled';

// Estilos CSS para las tarjetas de backup - Diseño sobrio y limpio
print '<style>
/* Grid de backup */
.backup-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

/* Tarjeta de backup base */
.backup-tile {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.backup-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Tipos de backup - Bordes de color distintivos */
.backup-tile.db { border-left: 4px solid #3b82f6; }
.backup-tile.files { border-left: 4px solid #10b981; }

/* Icono */
.backup-tile-icon {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 12px;
}

.backup-tile.db .backup-tile-icon { background: #eff6ff; color: #3b82f6; }
.backup-tile.files .backup-tile-icon { background: #ecfdf5; color: #10b981; }

/* Título */
.backup-tile-title {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 6px 0;
}

/* Descripción */
.backup-tile-desc {
    color: #6b7280;
    font-size: 12px;
    margin: 0 0 12px 0;
    line-height: 1.5;
}

/* Meta información */
.backup-tile-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 11px;
    color: #9ca3af;
}

.backup-tile-meta i {
    margin-right: 4px;
}

/* Badge de tipo */
.backup-tile-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: uppercase;
}

.backup-tile.db .backup-tile-badge { background: #eff6ff; color: #3b82f6; }
.backup-tile.files .backup-tile-badge { background: #ecfdf5; color: #10b981; }

/* Estado automático */
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f9fafb;
    border-radius: 6px;
    margin-top: 10px;
    border: 1px solid #e5e7eb;
}

    width: 8px;
    height: 8px;
    border-radius: 50%;
}

    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

    background: #ef4444; 
}

    font-family: "SF Mono", "Roboto Mono", monospace;
    font-size: 12px;
    font-weight: 600;
    color: #d97706;
    background: #fffbeb;
    padding: 3px 8px;
    border-radius: 4px;
    border: 1px solid #fde68a;
}

/* Responsive */
@media (min-width: 1200px) {
    .backup-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .backup-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .backup-tile { padding: 16px; }
    .backup-tile-icon { width: 36px; height: 36px; font-size: 16px; }
    .backup-tile-title { font-size: 14px; }
}

@media (max-width: 600px) {
    .backup-grid { grid-template-columns: 1fr; }
}
</style>';

// Grid de tarjetas de backup - Diseño limpio
print '<div class="backup-grid">';

// Card 1: Base de Datos
print '<div onclick="analyzeDatabase()" id="btnAnalyze" class="backup-tile db">';
print '<span class="backup-tile-badge">SQL</span>';
print '<div class="backup-tile-icon"><i class="fas fa-database"></i></div>';
print '<h3 class="backup-tile-title">' . $langs->trans('BackupDatabase') . '</h3>';
print '<p class="backup-tile-desc">' . $langs->trans('BackupDatabaseDesc') . '</p>';
print '<div class="backup-tile-meta"><i class="fas fa-clock"></i> 1-5 min <i class="fas fa-file-archive"></i> .zip</div>';
print '</div>';

// Card 2: Archivos Dolibarr
print '<div onclick="analyzeFiles()" id="btnFiles" class="backup-tile files">';
print '<span class="backup-tile-badge">FILES</span>';
print '<div class="backup-tile-icon"><i class="fas fa-folder-open"></i></div>';
print '<h3 class="backup-tile-title">' . $langs->trans('BackupFiles') . '</h3>';
print '<p class="backup-tile-desc">' . $langs->trans('BackupFilesDesc') . '</p>';
print '<div class="backup-tile-meta"><i class="fas fa-clock"></i> 5-15 min <i class="fas fa-hdd"></i> Variable</div>';
print '</div>';

print '</div>'; // Cierre backup-grid
// Panel de estadísticas y preview - OCULTO AL INICIO (se mostrará al hacer clic en el botón)
print '<div id="previewPanel" style="display: none; background: white; padding: 25px; border: 1px solid #e0e0e0; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">';
print '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">';
print '<h4 id="previewTitle" style="margin: 0; color: #2c3e50; font-size: 20px; font-weight: 600;"><i class="fas fa-file-archive"></i> Análisis de Archivos</h4>';
print '</div>';
print '<div id="statsContent">';
// Mostrar valores iniciales (0) que se actualizarán dinámicamente
print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px;">';
print '<div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 10px; text-align: center; border-left: 4px solid #28a745;">';
print '<div style="font-size: 36px; color: #28a745; margin-bottom: 10px;"><i class="fas fa-file"></i></div>';
print '<div style="font-size: 14px; color: #6c757d; margin-bottom: 8px; font-weight: 500;">Archivos</div>';
print '<div id="statFiles" style="font-size: 28px; font-weight: 700; color: #212529;">0</div>';
print '</div>';
print '<div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 10px; text-align: center; border-left: 4px solid #17a2b8;">';
print '<div style="font-size: 36px; color: #17a2b8; margin-bottom: 10px;"><i class="fas fa-folder-open"></i></div>';
print '<div style="font-size: 14px; color: #6c757d; margin-bottom: 8px; font-weight: 500;">Carpetas</div>';
print '<div id="statFolders" style="font-size: 28px; font-weight: 700; color: #212529;">0</div>';
print '</div>';
print '<div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 20px; border-radius: 10px; text-align: center; border-left: 4px solid #ffc107;">';
print '<div style="font-size: 36px; color: #f39c12; margin-bottom: 10px;"><i class="fas fa-compress"></i></div>';
print '<div style="font-size: 14px; color: #856404; margin-bottom: 8px; font-weight: 500;">Tamaño Estimado</div>';
print '<div id="statSize" style="font-size: 28px; font-weight: 700; color: #856404;">0 B</div>';
print '</div>';
print '</div>';
print '<div id="analysisProgress" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">';
print '<div style="color: #495057; font-size: 14px; margin-bottom: 10px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Analizando archivos del sistema...</div>';
// Barra de progreso removida de aquí - se usa la de backupProgress
print '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">';
print '<div style="font-size: 12px; font-weight: 600; color: #495057; margin-bottom: 8px;"><i class="fas fa-folder-open"></i> Ruta actual:</div>';
print '<div id="currentPath" style="font-size: 11px; color: #6c757d; font-family: monospace; background: white; padding: 8px; border-radius: 4px; border: 1px solid #dee2e6; word-break: break-all; max-height: 40px; overflow: hidden;">Esperando inicio...</div>';
print '</div>';
print '</div>';
print '</div>';
print '<div id="analysisLoader" style="display: none !important; visibility: hidden !important;"></div>';
print '<div style="margin-top: 25px; text-align: center; padding-top: 20px; border-top: 1px solid #e0e0e0;">';
print '<button onclick="confirmBackup()" id="confirmBtn" class="butAction" style="display: none; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(40,167,69,0.3); transition: all 0.3s;">✅ Confirmar y Iniciar Backup</button>';
print '<button onclick="cancelAnalysis()" class="butActionCancel" style="margin-left: 10px; background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 14px;">❌ Cancelar</button>';
print '</div>';

// Panel de descarga automática de chunks
print '<div id="chunkDownloaderPanel" style="display: none; background: white; padding: 25px; border: 1px solid #e0e0e0; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #4caf50;">';
print '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #4caf50;">';
print '<h4 style="margin: 0; color: #2c3e50; font-size: 20px; font-weight: 600;"><i class="fas fa-download"></i> Chunks Listos - Descarga Automática</h4>';
print '<button onclick="hideChunkDownloader()" class="butAction" style="background: #6c757d; border: none; padding: 8px 15px; border-radius: 4px;"><i class="fas fa-times"></i></button>';
print '</div>';

print '<div style="background: #e8f5e8; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c8e6c9;">';
print '<h5 style="margin: 0 0 15px 0; color: #2e7d32;"><i class="fas fa-check-circle"></i> ¡Análisis completado! Chunks listos para descargar</h5>';
print '<ul style="margin: 0; padding-left: 20px; color: #388e3c; line-height: 1.6;">';
print '<li>Los archivos han sido analizados y divididos en chunks seguros</li>';
print '<li>Cada chunk es un archivo ZIP válido independiente</li>';
print '<li>Descarga todos los chunks automáticamente desde tu navegador</li>';
print '<li>Respetamos los límites de tu servidor (3 segundos entre descargas)</li>';
print '<li>No sobrecargamos el servidor ni matamos tu sitio web</li>';
print '</ul>';
print '</div>';

print '<div id="chunkDownloaderStatus" style="display: none; background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #2196f3;">';
print '<div style="color: #1565c0; font-weight: 600; margin-bottom: 8px;"><i class="fas fa-spinner fa-spin"></i> <span id="chunkDownloaderStatusText">Iniciando descarga...</span></div>';
print '<div class="progress" style="width: 100%; height: 20px; background: #bbdefb; border-radius: 10px; overflow: hidden; margin: 10px 0;">';
print '<div id="chunkDownloaderProgress" class="progress-bar" style="height: 100%; width: 0%; background: linear-gradient(90deg, #2196f3, #21cbf3); transition: width 0.3s;"></div>';
print '</div>';
print '<div id="chunkDownloaderStats" style="font-size: 12px; color: #424242; margin-top: 8px;">Chunks descargados: 0/0 | Tamaño total: 0 MB</div>';
print '</div>';

print '<div style="text-align: center;">';
print '<button onclick="startChunkDownload()" id="startChunkDownloadBtn" class="butAction" style="background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%); color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(255,107,53,0.3);">';
print '<i class="fas fa-rocket"></i> Iniciar Descarga Automática</button>';
print '<button onclick="hideChunkDownloader()" class="butActionCancel" style="margin-left: 10px;">Cancelar</button>';
print '</div>';

print '<div id="chunkDownloaderLog" style="display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>';

print '</div>';
print '</div>';

// Barra de progreso y log
print '<div id="backupProgress" style="display: none; background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px;">';
print '<h4 style="margin: 0 0 15px 0; color: #495057;"><i class="fas fa-cogs"></i> Generando Backup</h4>';

// Advertencia de no cerrar
print '<div style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 5px solid #ff9800; text-align: center;">';
print '<span style="color: #e65100; font-weight: 700; font-size: 16px;">⚠️ NO CIERRE ESTA VENTANA HASTA QUE TERMINE LA COPIA DE SEGURIDAD ⚠️</span>';
print '</div>';

// Barra de progreso - Fondo blanco, progreso azul
print '<div style="background: #ffffff; border-radius: 8px; height: 35px; margin-bottom: 15px; overflow: hidden; border: 2px solid #dee2e6;">';
print '<div id="progressBar" style="background: #007bff; height: 100%; width: 0%; border-radius: 6px; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">0%</div>';
print '</div>';

// Info de tiempo en una sola línea
print '<div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px;">';
print '<span id="progressTime" style="color: #666;"><i class="fas fa-clock"></i> ⏱️ 0s</span>';
print '<span id="backupRemainingTime" style="color: #d32f2f; font-weight: 600;"><i class="fas fa-hourglass-half"></i> Restante: --</span>';
print '</div>';

// Recursos del servidor en tiempo real
print '<div id="serverResources" style="display: none; background: #f0f0f0; padding: 12px; border-radius: 6px; margin-bottom: 10px; font-size: 12px; border: 1px solid #ddd;">';
print '<div style="display: flex; flex-wrap: wrap; gap: 15px; color: #333; align-items: center;">';
print '<div style="flex: 1; min-width: 200px;"><strong>💾 RAM PHP:</strong> <span id="serverRamUsed">0</span>MB / <span id="serverRamLimit">0</span>MB (<span id="serverRamPercent">0</span>%)<br><small style="color: #666;" id="serverRamDetail">0MB usados de 0MB límite</small></div>';
print '<div style="flex: 1; min-width: 200px;"><strong>📊 RAM Disponible:</strong> <span id="serverRamAvailable">0</span>MB<br><strong>📈 RAM Pico:</strong> <span id="serverRamPeak">0</span>MB</div>';
print '<div style="flex: 1; min-width: 200px;"><strong>⏱️ Tiempo:</strong> <span id="serverChunkTime">0</span>s / <span id="serverMaxTime">0</span>s límite<br><small style="color: #666;" id="serverTimeDetail">0s usados de 0s límite</small></div>';
print '<div style="flex: 1; min-width: 200px;"><strong>📦 ZIP:</strong> <span id="serverZipSize">0</span>MB | <span id="serverZipFiles">0</span> archivos<br><small style="color: #666;" id="serverChunksDetail">0 de 0 chunks</small></div>';
print '<div style="flex: 1; min-width: 200px;"><strong>⚡ Velocidad:</strong> <span id="serverSpeed">0</span> arch/s<br><small style="color: #666;">Promedio: <span id="serverAvgSpeed">0</span> arch/s | Real: <span id="serverAvgSpeedReal">0</span> arch/s</small></div>';
print '</div>';
print '</div>';

// Hidden elements for compatibility
print '<span id="backupEstimatedTime" style="display:none;"></span>';
print '<span id="backupEnvInfo" style="display:none;"></span>';
// Segunda fila eliminada - la información completa ya está en la primera fila (progressTime/backupRemainingTime) y serverResources

// ========== ADVERTENCIA CRÍTICA (ARRIBA DEL LOG) ==========
print '<div id="criticalWarningBanner" style="display: none; background: #000000; color: #ff0000; font-weight: bold; padding: 15px; margin-bottom: 15px; border: 3px solid #ff0000; border-radius: 8px; text-align: center; font-size: 14px; animation: pulse 2s infinite; box-shadow: 0 0 20px rgba(255,0,0,0.5);">';
print '<div style="display: flex; align-items: center; justify-content: center; gap: 10px;">';
print '<span style="font-size: 24px;">⚠️</span>';
print '<div style="text-align: left;">';
print '<div style="font-size: 16px; margin-bottom: 5px;">¡BACKUP EN PROGRESO!</div>';
print '<div style="font-size: 13px; color: #ffcccc;">NO CIERRE ESTA PESTAÑA hasta que el proceso termine completamente</div>';
print '<div style="font-size: 11px; color: #ff6666; margin-top: 5px;">El backup continúa en el servidor aunque cierre la pestaña, pero perderá el seguimiento del progreso</div>';
print '</div>';
print '<span style="font-size: 24px;">⚠️</span>';
print '</div>';
print '</div>';

print '<div id="backupLog" style="background: #1a1a2e; color: #00ff00; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; min-height: 400px; max-height: 600px; height: 400px; overflow-y: auto; white-space: pre-wrap; border: 1px solid #333; line-height: 1.6;"></div>';
print '<div style="margin-top: 15px; text-align: center; display: flex; gap: 10px; justify-content: center;">';
print '<button onclick="copyBackupLog()" id="btnCopyLog" style="background: #17a2b8; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 600;"><i class="fas fa-copy"></i> Copiar Log</button>';
print '<button onclick="stopBackup()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 600;"><i class="fas fa-stop"></i> Detener Backup</button>';
print '</div>';
print '</div>';


// Listado de backups y chunks
print '<br>';
print load_fiche_titre('🗂️ Backups y Chunks Disponibles', '', 'title_setup');

// Filtros para backups y chunks
print '<div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">';
print '<div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">';
print '<div style="display: flex; align-items: center; gap: 10px;">';
print '<label style="font-weight: 600; color: #495057;"><i class="fas fa-filter"></i> Mostrar:</label>';
print '<label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">';
print '<input type="radio" name="backupFilter" value="all" checked onclick="filterBackups(\'all\')"> Todos';
print '</label>';
print '<label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">';
print '<input type="radio" name="backupFilter" value="backups" onclick="filterBackups(\'backups\')"> Backups Completos';
print '</label>';
print '<label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">';
print '<input type="radio" name="backupFilter" value="chunks" onclick="filterBackups(\'chunks\')"> Chunks Disponibles';
print '</label>';
print '</div>';
print '<div id="backupStats" style="color: #6c757d; font-size: 14px;">Cargando...</div>';
print '</div>';
print '</div>';

print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<th>Archivo</th><th>Tipo</th><th>Tamaño</th><th>Fecha</th><th class="right">Acciones</th>';
print '</tr>';

print '<tbody id="backupTableBody">';

if (empty($backups)) {
    print '<tr class="oddeven backup-row"><td colspan="5" class="opacitymedium center">No hay backups disponibles</td></tr>';
} else {
    $var=true;
    foreach ($backups as $b) {
        $var=!$var;
        $href = $downloadUrl.'?filename='.rawurlencode($b['file']).'&token='.$token;
        $deleteUrl = dol_buildpath('/custom/filemanager/scripts/delete_backup.php', 1);
        
        // Detectar tipo de backup por nombre del archivo
        $tipo = '';
        $icono = '';
        $isAutomatic = false;
        if (false && strpos($b['file'], 'automatic_backup_dolibarr_') !== false) {
            $tipo = 'Automática';
            $icono = '<span style="color: #17a2b8;"><i class="fas fa-robot"></i> Automática</span>';
            $isAutomatic = true;
        } elseif (strpos($b['file'], 'db_dolibarr_') !== false) {
            $tipo = 'Base de Datos';
            $icono = '<span style="color: #007bff;"><i class="fas fa-database"></i> Base de Datos</span>';
        } elseif (strpos($b['file'], 'files_dolibarr_') !== false) {
            $tipo = 'Archivos';
            $icono = '<span style="color: #28a745;"><i class="fas fa-folder"></i> Archivos Dolibarr</span>';
        } elseif (strpos($b['file'], 'full_backup_dolibarr_') !== false) {
            $tipo = 'Completo';
            $icono = '<span style="color: #fd7e14;"><i class="fas fa-server"></i> Completo</span>';
        } else {
            $tipo = fmTrans('Unknown');
            $icono = '<span style="color: #6c757d;"><i class="fas fa-question"></i> ' . fmTrans('Unknown') . '</span>';
        }
        
        // Intentar obtener usuario del archivo de info JSON
        $usuario = $isAutomatic ? 'Sistema' : 'Usuario desconocido';
        
        // Extraer el ID de backup del nombre del archivo
        // Formatos soportados:
        // - tipo_YYYYMMDDHHMMSS.zip (ej: db_dolibarr_20251102191406.zip -> 20251102191406)
        // - automatic_backup_dolibarr_YYYYMMDD_HHMMSS.zip (ej: automatic_backup_dolibarr_20251102_211404.zip -> 20251102211404)
        $backupId = '';
        // Primero intentar formato automático (YYYYMMDD_HHMMSS) - DESHABILITADO
        if (false && preg_match('/automatic_backup_dolibarr_(\d{8})_(\d{6})\.zip$/', $b['file'], $matches)) {
            $backupId = $matches[1] . $matches[2]; // Quitar guión bajo: 20251102211404
        } elseif (preg_match('/(\d{14})\.zip$/', $b['file'], $matches)) {
            // Formato estándar: YYYYMMDDHHMMSS
            $backupId = $matches[1];
        } elseif (preg_match('/(\d{14})$/', $b['file'], $matches)) {
            $backupId = $matches[1];
        }
        
        // Buscar el archivo JSON usando el ID del backup
        if (!empty($backupId)) {
            $infoPattern = $backupDir . DIRECTORY_SEPARATOR . 'backup_info_' . $backupId . '.json';
            if (file_exists($infoPattern)) {
                $infoContent = @file_get_contents($infoPattern);
                if ($infoContent !== false) {
                    $infoData = @json_decode($infoContent, true);
                    if (is_array($infoData) && isset($infoData['user_login']) && !empty($infoData['user_login'])) {
                        $usuario = $infoData['user_login'];
                    } elseif (is_array($infoData) && isset($infoData['user_name']) && !empty($infoData['user_name'])) {
                        $usuario = $infoData['user_name'];
                    }
                }
            }
        }
        
        // Si aún no se encontró, intentar el método anterior (compatibilidad)
        if ($usuario === 'Usuario desconocido' || ($usuario === 'Sistema' && !$isAutomatic)) {
            $infoPattern = $backupDir . DIRECTORY_SEPARATOR . 'backup_info_' . preg_replace('/\.zip$/', '', $b['file']) . '.json';
            if (file_exists($infoPattern)) {
                $infoContent = @file_get_contents($infoPattern);
                if ($infoContent !== false) {
                    $infoData = @json_decode($infoContent, true);
                    if (is_array($infoData) && isset($infoData['user_login']) && !empty($infoData['user_login']) && $infoData['user_login'] !== 'unknown') {
                        $usuario = $infoData['user_login'];
                    } elseif (is_array($infoData) && isset($infoData['user_name']) && !empty($infoData['user_name']) && $infoData['user_name'] !== 'unknown') {
                        $usuario = $infoData['user_name'];
                    }
                }
            }
        }
        
        // Si es automático y no se encontró usuario válido, mantener "Sistema"
        if ($isAutomatic && ($usuario === 'Usuario desconocido' || empty($usuario) || $usuario === 'unknown')) {
            $usuario = 'Sistema';
        }
        
        // Buscar archivo de log correspondiente
        $logContent = '';
        $logExists = false;
        if ($backupId) {
            $logFile = $backupDir . '/log_' . $backupId . '.txt';
            if (file_exists($logFile) && is_readable($logFile)) {
                $logExists = true;
                $logContent = file_get_contents($logFile);
                // Contar líneas para mostrar info
                $totalLines = substr_count($logContent, "\n") + 1;
            }
        }
        
        print '<tr class="'.($var?'oddeven':'even').'">';
        print '<td><strong>'.dol_escape_htmltag($b['file']).'</strong></td>';
        print '<td>' . $icono . '</td>';
        // Convertir tamaño automáticamente a la unidad apropiada
        $size_bytes = $b['size'];
        $size_formatted = '';
        if ($size_bytes >= 1024 * 1024 * 1024) {
            // Mayor o igual a 1 GB
            $size_formatted = round($size_bytes / (1024 * 1024 * 1024), 2) . ' GB';
        } elseif ($size_bytes >= 1024 * 1024) {
            // Mayor o igual a 1 MB
            $size_mb = $size_bytes / (1024 * 1024);
            if ($size_mb >= 1024) {
                // Si pasa de 1024 MB, convertir a GB
                $size_formatted = round($size_mb / 1024, 2) . ' GB';
            } else {
                $size_formatted = round($size_mb, 2) . ' MB';
            }
        } elseif ($size_bytes >= 1024) {
            // Mayor o igual a 1 KB
            $size_formatted = round($size_bytes / 1024, 2) . ' KB';
        } else {
            $size_formatted = $size_bytes . ' B';
        }
        print '<td>'.$size_formatted.'</td>';
        print '<td>'.dol_print_date($b['time'],'dayhour').'</td>';
        print '<td class="right">';
        if ($logExists) {
            $logId = 'log_' . $backupId;
            // Guardar el contenido del log en un script para evitar problemas con caracteres especiales en onclick
            print '<script>if(typeof backupLogs === "undefined") backupLogs = {}; backupLogs["'.$logId.'"] = '.json_encode($logContent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).';</script>';
            print '<button class="butAction" onclick="showBackupLog(\''.$logId.'\')" title="Ver logs del backup"><i class="fas fa-file-alt"></i> Ver Logs</button> ';
        }
        print '<a class="butAction" href="'.$href.'" title="' . fmTrans('Download') . '"><i class="fas fa-download"></i> ' . fmTrans('Download') . '</a>';
        print ' <a class="butActionDelete" href="javascript:void(0)" onclick="deleteBackup(\''.dol_escape_js($b['file']).'\', \''.$deleteUrl.'\')" title="' . fmTrans('Delete') . '"><i class="fas fa-trash"></i> ' . fmTrans('Delete') . '</a>';
        print '</td>';
        print '</tr>';
    }
}
    print '</table>';
    print '</div>';
print '</div>'; // Cierra backupContent

// Obtener filtros de la URL
$filter_action = $_GET['action'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// ========== PESTAÑA HISTORIAL/LOGS ==========
print '<div id="logsContent" style="display: none;">';

// Header de la sección
print '<div style="background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 25px; color: white;">';
print '<div style="display: flex; align-items: center; gap: 15px;">';
print '<div style="width: 50px; height: 50px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">📋</div>';
print '<div>';
print '<h2 style="margin: 0; font-size: 22px; font-weight: 600;">' . $langs->trans('History') . '</h2>';
print '<p style="margin: 5px 0 0 0; opacity: 0.85; font-size: 14px;">Registro completo de actividad del FileManager</p>';
print '</div>';
print '</div>';
print '</div>';

// Tarjetas de estadísticas rápidas
print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;">';

// Contar logs para estadísticas
$total_logs = 0;
$today_logs = 0;
$backup_logs = 0;
if ($db) {
    $sql_total = "SELECT COUNT(*) as cnt FROM llx_filemanager_logs";
    $res_total = $db->query($sql_total);
    if ($res_total && $obj = $db->fetch_object($res_total)) $total_logs = $obj->cnt;
    
    $sql_today = "SELECT COUNT(*) as cnt FROM llx_filemanager_logs WHERE DATE(timestamp) = CURDATE()";
    $res_today = $db->query($sql_today);
    if ($res_today && $obj = $db->fetch_object($res_today)) $today_logs = $obj->cnt;
    
    $sql_backup = "SELECT COUNT(*) as cnt FROM llx_filemanager_logs WHERE action LIKE '%backup%'";
    $res_backup = $db->query($sql_backup);
    if ($res_backup && $obj = $db->fetch_object($res_backup)) $backup_logs = $obj->cnt;
}

// Stat cards
$stat_cards = array(
    array('icon' => '📊', 'value' => $total_logs, 'label' => 'Total Registros', 'color' => '#3b82f6'),
    array('icon' => '📅', 'value' => $today_logs, 'label' => 'Hoy', 'color' => '#10b981'),
    array('icon' => '💾', 'value' => $backup_logs, 'label' => 'Backups', 'color' => '#8b5cf6'),
);

foreach ($stat_cards as $stat) {
    print '<div style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; text-align: center;">';
    print '<div style="font-size: 28px; margin-bottom: 8px;">' . $stat['icon'] . '</div>';
    print '<div style="font-size: 28px; font-weight: 700; color: ' . $stat['color'] . ';">' . number_format($stat['value']) . '</div>';
    print '<div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">' . $stat['label'] . '</div>';
    print '</div>';
}

print '</div>';

// Panel de filtros
print '<div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; margin-bottom: 25px;">';
print '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb;">';
print '<span style="font-size: 20px;">🔍</span>';
print '<h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #111827;">Filtros de Búsqueda</h3>';
print '</div>';

print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';

// Filtro por acción
print '<div>';
print '<label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500; color: #374151;">' . fmTrans('ActionType') . '</label>';
print '<select id="filterAction" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: #f9fafb; transition: all 0.2s;" onfocus="this.style.borderColor=\'#3b82f6\';this.style.background=\'white\'" onblur="this.style.borderColor=\'#d1d5db\';this.style.background=\'#f9fafb\'">';
print '<option value="">📋 ' . fmTrans('AllActions') . '</option>';
$actions = array(
    'create_folder' => '📁 ' . t('create_folder'),
    'upload_file' => '📤 ' . t('upload'),
    'move_to_trash' => '🗑️ ' . t('move_to_trash'),
    'bulk_move_to_trash' => '🗑️ ' . t('move_to_trash'),
    'permanent_delete_file' => '❌ ' . t('delete'),
    'permanent_delete_folder' => '❌ ' . t('delete'),
    'bulk_permanent_delete' => '❌ ' . t('delete'),
    'restore_from_trash' => '♻️ ' . t('restore'),
    'empty_trash' => '🧹 ' . t('empty_trash'),
    'clean_trash' => '🧹 ' . t('empty_trash'),
    'create_backup' => '💾 ' . t('backup_started'),
    'backup_completed' => '✅ ' . t('backup_completed')
);
foreach ($actions as $value => $label) {
    $selected = ($filter_action === $value) ? ' selected' : '';
    print '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . $label . '</option>';
}
print '</select>';
print '</div>';

// Filtro por usuario
print '<div>';
print '<label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500; color: #374151;">' . fmTrans('User') . '</label>';
print '<select id="filterUser" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: #f9fafb; transition: all 0.2s;" onfocus="this.style.borderColor=\'#3b82f6\';this.style.background=\'white\'" onblur="this.style.borderColor=\'#d1d5db\';this.style.background=\'#f9fafb\'">';
print '<option value="">👥 ' . fmTrans('AllUsers') . '</option>';
foreach ($users as $user_name) {
    $selected = ($filter_user === $user_name) ? ' selected' : '';
    print '<option value="' . htmlspecialchars($user_name) . '"' . $selected . '>👤 ' . htmlspecialchars($user_name) . '</option>';
}
print '</select>';
print '</div>';

// Filtro por fecha desde
print '<div>';
print '<label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500; color: #374151;">' . fmTrans('From') . '</label>';
print '<input type="date" id="filterDateFrom" value="' . htmlspecialchars($filter_date_from) . '" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: #f9fafb; transition: all 0.2s;" onfocus="this.style.borderColor=\'#3b82f6\';this.style.background=\'white\'" onblur="this.style.borderColor=\'#d1d5db\';this.style.background=\'#f9fafb\'">';
print '</div>';

// Filtro por fecha hasta
print '<div>';
print '<label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500; color: #374151;">' . fmTrans('To') . '</label>';
print '<input type="date" id="filterDateTo" value="' . htmlspecialchars($filter_date_to) . '" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: #f9fafb; transition: all 0.2s;" onfocus="this.style.borderColor=\'#3b82f6\';this.style.background=\'white\'" onblur="this.style.borderColor=\'#d1d5db\';this.style.background=\'#f9fafb\'">';
print '</div>';

print '</div>';

// Botones de filtro
print '<div style="display: flex; gap: 10px; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e5e7eb;">';
print '<button onclick="applyLogFilters()" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; box-shadow: 0 2px 4px rgba(59,130,246,0.3);" onmouseover="this.style.transform=\'translateY(-1px)\';this.style.boxShadow=\'0 4px 8px rgba(59,130,246,0.4)\'" onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 2px 4px rgba(59,130,246,0.3)\'"><i class="fas fa-search"></i> ' . fmTrans('Search') . '</button>';
print '<button onclick="clearLogFilters()" style="display: inline-flex; align-items: center; gap: 8px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.background=\'#e5e7eb\'" onmouseout="this.style.background=\'#f3f4f6\'"><i class="fas fa-times"></i> ' . fmTrans('Clear') . '</button>';
print '<button onclick="refreshLogs()" style="display: inline-flex; align-items: center; gap: 8px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.background=\'#e5e7eb\'" onmouseout="this.style.background=\'#f3f4f6\'"><i class="fas fa-sync-alt"></i> ' . fmTrans('Refresh') . '</button>';
print '<button onclick="deleteAllLogs()" style="display: inline-flex; align-items: center; gap: 8px; background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; margin-left: auto;" onmouseover="this.style.background=\'#dc2626\'" onmouseout="this.style.background=\'#ef4444\'"><i class="fas fa-trash-alt"></i> ' . fmTrans('DeleteAll') . '</button>';
print '</div>';

print '</div>';

// Tabla de logs
print '<div style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; overflow: hidden;">';

// Header de la tabla
print '<div style="padding: 15px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">';
print '<h3 style="margin: 0; font-size: 15px; font-weight: 600; color: #111827; display: flex; align-items: center; gap: 8px;"><span>📜</span> ' . fmTrans('ActivityLog') . '</h3>';
print '<span style="font-size: 12px; color: #6b7280;">' . fmTrans('LatestRecords') . '</span>';
print '</div>';

print '<div style="overflow-x: auto;">';
print '<table style="width: 100%; border-collapse: collapse;">';
print '<thead>';
print '<tr style="background: #f9fafb;">';
print '<th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">' . fmTrans('DateTime') . '</th>';
print '<th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">' . fmTrans('User') . '</th>';
print '<th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">' . fmTrans('Action') . '</th>';
print '<th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">' . fmTrans('FilePath') . '</th>';
print '<th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">' . fmTrans('Details') . '</th>';
print '<th style="padding: 12px 16px; text-align: center; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; width: 100px;">' . fmTrans('Actions') . '</th>';
print '</tr>';
print '</thead>';
print '<tbody>';
    
// Consultar logs de la base de datos con filtros
$logs = array();
if ($db) {
    $sql = "SELECT * FROM llx_filemanager_logs WHERE 1=1";
    
    if (!empty($filter_action)) {
        $filter_action_escaped = $db->escape($filter_action);
        $sql .= " AND action = '" . $filter_action_escaped . "'";
    }
    
    if (!empty($filter_user)) {
        $filter_user_escaped = $db->escape($filter_user);
        $sql .= " AND user_name = '" . $filter_user_escaped . "'";
    }
    
    if (!empty($filter_date_from)) {
        $filter_date_from_escaped = $db->escape($filter_date_from);
        $sql .= " AND DATE(timestamp) >= '" . $filter_date_from_escaped . "'";
    }
    
    if (!empty($filter_date_to)) {
        $filter_date_to_escaped = $db->escape($filter_date_to);
        $sql .= " AND DATE(timestamp) <= '" . $filter_date_to_escaped . "'";
    }
    
    $sql .= " ORDER BY timestamp DESC LIMIT 100";
    
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $logs[] = $obj;
        }
    }
}

// Mapeo de acciones a iconos y colores
$action_styles = array(
    'create_folder' => array('icon' => '📁', 'color' => '#3b82f6', 'bg' => '#eff6ff'),
    'upload_file' => array('icon' => '📤', 'color' => '#10b981', 'bg' => '#ecfdf5'),
    'move_to_trash' => array('icon' => '🗑️', 'color' => '#f59e0b', 'bg' => '#fffbeb'),
    'bulk_move_to_trash' => array('icon' => '🗑️', 'color' => '#f59e0b', 'bg' => '#fffbeb'),
    'permanent_delete_file' => array('icon' => '❌', 'color' => '#ef4444', 'bg' => '#fef2f2'),
    'permanent_delete_folder' => array('icon' => '❌', 'color' => '#ef4444', 'bg' => '#fef2f2'),
    'bulk_permanent_delete' => array('icon' => '❌', 'color' => '#ef4444', 'bg' => '#fef2f2'),
    'restore_from_trash' => array('icon' => '♻️', 'color' => '#10b981', 'bg' => '#ecfdf5'),
    'empty_trash' => array('icon' => '🧹', 'color' => '#6b7280', 'bg' => '#f3f4f6'),
    'clean_trash' => array('icon' => '🧹', 'color' => '#6b7280', 'bg' => '#f3f4f6'),
    'create_backup' => array('icon' => '💾', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'),
    'backup_completed' => array('icon' => '✅', 'color' => '#10b981', 'bg' => '#ecfdf5'),
);
$default_style = array('icon' => '📋', 'color' => '#6b7280', 'bg' => '#f3f4f6');

if (empty($logs)) {
    print '<tr>';
    print '<td colspan="6" style="text-align: center; padding: 60px 20px;">';
    print '<div style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;">📭</div>';
    print '<div style="font-size: 16px; color: #6b7280; margin-bottom: 5px;">' . fmTrans('NoRecordsAvailable') . '</div>';
    print '<div style="font-size: 13px; color: #9ca3af;">' . fmTrans('ActivityRecordsWillAppear') . '</div>';
    print '</td>';
    print '</tr>';
} else {
    $row_num = 0;
    foreach ($logs as $log) {
        $row_num++;
        $row_bg = ($row_num % 2 === 0) ? '#f9fafb' : 'white';
        
        // Formatear timestamp
        $timestamp_display = '';
        if (isset($log->timestamp) && !empty($log->timestamp)) {
            if (is_numeric($log->timestamp)) {
                $timestamp_display = dol_print_date($log->timestamp, 'dayhour');
            } else {
                $timestamp_display = dol_print_date(strtotime($log->timestamp), 'dayhour');
            }
        } else {
            $timestamp_display = 'N/A';
        }
        
        // Obtener estilo de acción
        $action_key = $log->action ?? '';
        $style = isset($action_styles[$action_key]) ? $action_styles[$action_key] : $default_style;
        
        // Formatear nombre de archivo/ruta
        $file_display = $log->file_path ?? '';
        if (strlen($file_display) > 50) {
            $file_display = '...' . substr($file_display, -47);
        }
        
        print '<tr style="background: ' . $row_bg . '; transition: background 0.2s;" onmouseover="this.style.background=\'#f0f9ff\'" onmouseout="this.style.background=\'' . $row_bg . '\'">';
        print '<td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #374151; white-space: nowrap;">';
        print '<span style="display: inline-flex; align-items: center; gap: 6px;"><span style="color: #9ca3af;">🕐</span> ' . $timestamp_display . '</span>';
        print '</td>';
        print '<td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; font-size: 13px;">';
        print '<span style="display: inline-flex; align-items: center; gap: 6px; background: #f3f4f6; padding: 4px 10px; border-radius: 20px; font-weight: 500; color: #374151;">👤 ' . htmlspecialchars($log->user_name ?? fmTrans('Unknown')) . '</span>';
        print '</td>';
        print '<td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; font-size: 13px;">';
        print '<span style="display: inline-flex; align-items: center; gap: 6px; background: ' . $style['bg'] . '; color: ' . $style['color'] . '; padding: 4px 10px; border-radius: 6px; font-weight: 500; font-size: 12px;">' . $style['icon'] . ' ' . htmlspecialchars($action_key) . '</span>';
        print '</td>';
        print '<td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #6b7280; max-width: 250px; overflow: hidden; text-overflow: ellipsis;" title="' . htmlspecialchars($log->file_path ?? '') . '">';
        print '<code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 12px;">' . htmlspecialchars($file_display) . '</code>';
        print '</td>';
        print '<td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">';
        print htmlspecialchars($log->details ?? ($log->ip_address ?? '-'));
        print '</td>';
        print '<td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; text-align: center;">';
        print '<button onclick="deleteLog(' . $log->rowid . ')" style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px;" onmouseover="this.style.background=\'#dc2626\'" onmouseout="this.style.background=\'#ef4444\'" title="' . fmTrans('Delete') . '"><i class="fas fa-trash-alt"></i> ' . fmTrans('Delete') . '</button>';
        print '</td>';
        print '</tr>';
    }
    }
    
print '</tbody>';
    print '</table>';
print '</div>';
print '</div>';

print '</div>'; // Cierra logsContent

// ========== PESTAÑA ACERCA DE - DISEÑO COMPACTO Y DETALLADO ==========
print '<div id="aboutContent" style="display: none;">';

// Información del módulo
$module_version = '1.0.0';
$module_author = 'Antonio Benalcazar';
$module_license = 'GPL v3+';
$module_year = '2024-2025';
$module_updated = 'Nov 2025';

// Calcular tamaño del módulo
$module_path = dol_buildpath('/custom/filemanager', 0);
$module_size = 0;
if (is_dir($module_path)) {
    $dir_iterator = new RecursiveDirectoryIterator($module_path);
    $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $module_size += $file->getSize();
        }
    }
}
$module_size_mb = round($module_size / 1048576, 2);

print '<div style="max-width: 1000px; margin: 0 auto; padding: 20px;">';

// Header compacto
print '<div style="text-align: center; padding: 15px 20px; border-bottom: 2px solid #3b82f6; margin-bottom: 20px; background: linear-gradient(135deg, #f0f9ff, #e0f2fe);">';
print '<h1 style="margin: 0 0 5px 0; font-size: 24px; font-weight: 700; color: #1e40af;">FileManager Pro</h1>';
print '<p style="margin: 0; font-size: 13px; color: #64748b;">Gestor de Archivos y Backups para Dolibarr ERP/CRM</p>';
print '</div>';

// Detección dinámica de versiones
$php_version = phpversion();
$php_major = (int)PHP_MAJOR_VERSION;
$php_minor = (int)PHP_MINOR_VERSION;
$dolibarr_version = defined('DOL_VERSION') ? DOL_VERSION : 'Desconocida';
$dolibarr_major = defined('DOL_VERSION') ? (int)explode('.', DOL_VERSION)[0] : 0;

// Verificar compatibilidad
$php_compatible = version_compare($php_version, '7.0.0', '>=');
$dolibarr_compatible = $dolibarr_major >= 13;

// Iconos de estado
$php_icon = $php_compatible ? '✅' : '⚠️';
$dol_icon = $dolibarr_compatible ? '✅' : '⚠️';

// Grid de 2 columnas
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">';

// ========== COLUMNA 1: INFORMACIÓN DEL MÓDULO ==========
print '<div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px;">';
print '<h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #1e40af; border-bottom: 2px solid #3b82f6; padding-bottom: 6px;">📦 Información del Módulo</h3>';
print '<table style="width: 100%; font-size: 12px;">';
$info = [
    ['Versión', '<strong>v' . $module_version . '</strong>'],
    ['Actualizado', $module_updated],
    ['Tamaño', $module_size_mb . ' MB'],
    ['Autor', '<a href="https://antoniobenalcazar.in" target="_blank" style="color: #2563eb; text-decoration: none;">🇪🇨 ' . $module_author . '</a>'],
    ['Licencia', $module_license],
    ['Idiomas', '🇪🇸 🇬🇧 🇫🇷 🇩🇪'],
    ['Soporte', '<a href="https://www.dolibarr.org/forum" target="_blank" style="color: #2563eb;">Foro Dolibarr</a>'],
];
foreach ($info as $row) {
    print '<tr><td style="padding: 4px 0; color: #64748b; width: 90px;">' . $row[0] . ':</td><td style="padding: 4px 0; color: #1e293b;">' . $row[1] . '</td></tr>';
}
print '</table>';
print '</div>';

// ========== COLUMNA 2: ENTORNO DETECTADO ==========
print '<div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px;">';
print '<h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #1e40af; border-bottom: 2px solid #3b82f6; padding-bottom: 6px;">🖥️ Entorno Detectado</h3>';
print '<table style="width: 100%; font-size: 12px;">';

// PHP
$php_color = $php_compatible ? '#10b981' : '#f59e0b';
print '<tr><td style="padding: 4px 0; color: #64748b; width: 90px;">PHP:</td><td style="padding: 4px 0;"><span style="color: ' . $php_color . '; font-weight: 600;">' . $php_icon . ' ' . $php_version . '</span> <span style="color: #94a3b8; font-size: 11px;">(req: 7.0+)</span></td></tr>';

// Dolibarr
$dol_color = $dolibarr_compatible ? '#10b981' : '#f59e0b';
print '<tr><td style="padding: 4px 0; color: #64748b;">Dolibarr:</td><td style="padding: 4px 0;"><span style="color: ' . $dol_color . '; font-weight: 600;">' . $dol_icon . ' ' . $dolibarr_version . '</span> <span style="color: #94a3b8; font-size: 11px;">(req: 13.0+)</span></td></tr>';

// Base de datos
$db_type = isset($conf->db->type) ? $conf->db->type : 'mysql';
$db_version = '';
if (isset($db) && is_object($db)) {
    $db_version = $db->getVersion();
}
print '<tr><td style="padding: 4px 0; color: #64748b;">Base de Datos:</td><td style="padding: 4px 0;"><span style="color: #10b981; font-weight: 600;">✅ ' . ucfirst($db_type) . '</span> <span style="color: #1e293b; font-size: 11px;">' . $db_version . '</span></td></tr>';

// Sistema Operativo
$os_info = php_uname('s') . ' ' . php_uname('r');
print '<tr><td style="padding: 4px 0; color: #64748b;">Sistema:</td><td style="padding: 4px 0; color: #1e293b;">' . php_uname('s') . '</td></tr>';

// Extensiones PHP
$extensions = ['zip' => extension_loaded('zip'), 'mysqli' => extension_loaded('mysqli'), 'gd' => extension_loaded('gd')];
$ext_text = '';
foreach ($extensions as $ext => $loaded) {
    $ext_text .= '<span style="color: ' . ($loaded ? '#10b981' : '#ef4444') . '; margin-right: 8px;">' . ($loaded ? '✓' : '✗') . ' ' . $ext . '</span>';
}
print '<tr><td style="padding: 4px 0; color: #64748b;">Extensiones:</td><td style="padding: 4px 0; font-size: 11px;">' . $ext_text . '</td></tr>';

print '</table>';
print '</div>';

print '</div>'; // Fin grid 2 columnas

// ========== SECCIÓN: REQUISITOS Y CARACTERÍSTICAS ==========
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">';

// ========== REQUISITOS DEL SISTEMA ==========
print '<div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px;">';
print '<h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #1e40af; border-bottom: 2px solid #3b82f6; padding-bottom: 6px;">⚙️ Requisitos del Sistema</h3>';
print '<div style="font-size: 12px; line-height: 1.8;">';
print '<div style="margin-bottom: 8px;"><strong style="color: #64748b;">Software:</strong></div>';
print '<ul style="margin: 0 0 10px 20px; padding: 0; color: #1e293b;">';
print '<li>PHP 7.0 o superior</li>';
print '<li>Dolibarr 13.0 o superior</li>';
print '<li>MySQL/MariaDB 5.5+</li>';
print '</ul>';
print '<div style="margin-bottom: 8px;"><strong style="color: #64748b;">Extensiones PHP:</strong></div>';
print '<ul style="margin: 0 0 10px 20px; padding: 0; color: #1e293b;">';
print '<li>zip (requerida)</li>';
print '<li>mysqli (requerida)</li>';
print '<li>gd (opcional)</li>';
print '</ul>';
print '<div style="margin-bottom: 8px;"><strong style="color: #64748b;">Permisos:</strong></div>';
print '<ul style="margin: 0 0 10px 20px; padding: 0; color: #1e293b;">';
print '<li>Acceso de administrador</li>';
print '<li>Escritura en documents/</li>';
print '<li>Acceso a cron (para backups auto)</li>';
print '</ul>';
print '</div>';
print '</div>';

// ========== CARACTERÍSTICAS PRINCIPALES ==========
print '<div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px;">';
print '<h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #1e40af; border-bottom: 2px solid #3b82f6; padding-bottom: 6px;">✨ Características Principales</h3>';
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 11px;">';
$features_compact = [
    ['📂', 'Explorador de archivos'],
    ['🔍', 'Búsqueda avanzada'],
    ['💾', 'Backups manuales'],
    ['⏰', 'Backups automáticos'],
    ['📊', 'Progreso en tiempo real'],
    ['📜', 'Logs detallados'],
    ['🔒', 'Protección CSRF'],
    ['👁️', 'Vista previa archivos'],
    ['📤', 'Drag & drop'],
    ['🗑️', 'Papelera recuperable'],
    ['🌐', '4 idiomas'],
    ['⚡', 'Interfaz moderna'],
];
foreach ($features_compact as $f) {
    print '<div style="padding: 6px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; display: flex; align-items: center; gap: 6px;"><span>' . $f[0] . '</span><span style="color: #334155;">' . $f[1] . '</span></div>';
}
print '</div>';
print '</div>';

print '</div>'; // Fin grid

// ========== SECCIÓN: INFORMACIÓN TÉCNICA ==========
print '<div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-bottom: 15px;">';
print '<h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #1e40af; border-bottom: 2px solid #3b82f6; padding-bottom: 6px;">🔧 Información Técnica</h3>';
print '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; font-size: 12px;">';

// Capacidades de Backup
print '<div>';
print '<div style="font-weight: 600; color: #64748b; margin-bottom: 6px;">💾 Capacidades de Backup</div>';
print '<ul style="margin: 0; padding-left: 18px; color: #1e293b; line-height: 1.7;">';
print '<li>Base de Datos (SQL)</li>';
print '<li>Archivos del sistema</li>';
print '<li>Backup completo</li>';
print '<li>Compresión ZIP</li>';
print '<li>Programación flexible</li>';
print '</ul>';
print '</div>';

// Seguridad
print '<div>';
print '<div style="font-weight: 600; color: #64748b; margin-bottom: 6px;">🔒 Seguridad</div>';
print '<ul style="margin: 0; padding-left: 18px; color: #1e293b; line-height: 1.7;">';
print '<li>Validación CSRF</li>';
print '<li>Control de acceso</li>';
print '<li>Sanitización de rutas</li>';
print '<li>Escape de SQL</li>';
print '<li>Logs de auditoría</li>';
print '</ul>';
print '</div>';

// Formatos Soportados
print '<div>';
print '<div style="font-weight: 600; color: #64748b; margin-bottom: 6px;">📄 Formatos Soportados</div>';
print '<ul style="margin: 0; padding-left: 18px; color: #1e293b; line-height: 1.7;">';
print '<li>Imágenes (JPG, PNG, GIF)</li>';
print '<li>Documentos (PDF, DOC)</li>';
print '<li>Código (PHP, JS, CSS)</li>';
print '<li>Audio/Video</li>';
print '<li>Archivos ZIP</li>';
print '</ul>';
print '</div>';

print '</div>';
print '</div>';

// ========== FOOTER: SOPORTE Y CONTACTO ==========
print '<div style="background: linear-gradient(135deg, #1e40af, #3b82f6); border-radius: 8px; padding: 15px; text-align: center; color: white;">';
print '<div style="font-size: 13px; font-weight: 600; margin-bottom: 10px;">📞 Soporte y Contacto</div>';
print '<div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">';
print '<a href="https://www.dolistore.com/" target="_blank" style="display: inline-block; padding: 6px 14px; background: white; color: #1e40af; text-decoration: none; border-radius: 5px; font-size: 12px; font-weight: 500;">🛒 DoliStore</a>';
print '<a href="https://www.dolibarr.org/forum" target="_blank" style="display: inline-block; padding: 6px 14px; background: white; color: #1e40af; text-decoration: none; border-radius: 5px; font-size: 12px; font-weight: 500;">💬 Foro</a>';
print '<a href="https://wiki.dolibarr.org" target="_blank" style="display: inline-block; padding: 6px 14px; background: white; color: #1e40af; text-decoration: none; border-radius: 5px; font-size: 12px; font-weight: 500;">📖 Wiki</a>';
print '<a href="https://antoniobenalcazar.in" target="_blank" style="display: inline-block; padding: 6px 14px; background: white; color: #1e40af; text-decoration: none; border-radius: 5px; font-size: 12px; font-weight: 500;">🇪🇨 Autor</a>';
print '</div>';
print '<div style="font-size: 11px; color: #bfdbfe;">© ' . $module_year . ' ' . $module_author . ' | GPL v3+ License</div>';
print '</div>';

print '</div>'; // max-width container
print '</div>'; // Cierre aboutContent

// ========== PESTAÑA MANTENIMIENTO ==========
print '<div id="maintenanceContent" style="display: none;">';
print '<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">';

// Header de la pestaña
print '<div style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border: 1px solid #cbd5e1; border-radius: 12px; padding: 24px; margin-bottom: 24px;">';
print '<h2 style="margin: 0 0 8px 0; color: #334155; font-size: 24px; font-weight: 700;"><i class="fas fa-wrench" style="color: #64748b;"></i> ' . $langs->trans('Maintenance') . '</h2>';
print '<p style="margin: 0; color: #64748b; font-size: 14px;">Herramientas de mantenimiento y limpieza del módulo FileManager</p>';
print '</div>';

// Sección de Cache
print '<div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px;">';
print '<h3 style="margin: 0 0 16px 0; color: #334155; font-size: 18px; font-weight: 600;"><i class="fas fa-memory" style="color: #3b82f6;"></i> Gestión de Cache</h3>';
print '<p style="margin: 0 0 20px 0; color: #64748b; font-size: 14px; line-height: 1.5;">';
print 'El módulo FileManager utiliza varios tipos de cache para mejorar el rendimiento. Purgar el cache puede resolver problemas de funcionamiento pero puede afectar temporalmente la velocidad de carga.';
print '</p>';

// Información sobre tipos de cache
print '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">';
print '<h4 style="margin: 0 0 12px 0; color: #334155; font-size: 14px; font-weight: 600;"><i class="fas fa-info-circle" style="color: #06b6d4;"></i> Tipos de Cache</h4>';
print '<ul style="margin: 0; padding-left: 20px; color: #64748b; font-size: 13px; line-height: 1.6;">';
print '<li><strong>Cache de configuración:</strong> Almacena la configuración del módulo en memoria</li>';
print '<li><strong>Cache de tamaños de carpeta:</strong> Almacena los tamaños calculados de carpetas (5 minutos)</li>';
print '<li><strong>Cache de archivos optimizados:</strong> Archivos de configuración procesados</li>';
print '</ul>';
print '</div>';

// Botón de purga de cache
print '<div style="text-align: center;">';
print '<button type="button" onclick="purgeFileManagerCache()" id="purgeCacheBtn" style="display: inline-flex; align-items: center; gap: 12px; background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border: none; padding: 16px 32px; border-radius: 10px; cursor: pointer; font-size: 16px; font-weight: 600; transition: all 0.2s; box-shadow: 0 4px 12px rgba(220,38,38,0.3);">';
print '<i class="fas fa-trash-alt"></i>';
print '<span>Purgar Cache del FileManager</span>';
print '</button>';
print '</div>';

print '<div id="purgeCacheStatus" style="margin-top: 16px; text-align: center; display: none;"></div>';

print '</div>'; // Fin sección cache
print '</div>'; // max-width container
print '</div>'; // Cierre maintenanceContent

?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.swal2-container {
    z-index: 99999 !important;
}
.swal2-popup {
    z-index: 99999 !important;
}
</style>

<?php
// Obtener información del usuario ANTES del JavaScript para evitar problemas de sintaxis
$js_user_login = "";
$js_user_id = 0;
if (isset($user) && is_object($user) && !empty($user->login)) {
    $js_user_login = $user->login;
    $js_user_id = !empty($user->id) ? intval($user->id) : 0;
} elseif (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION["dol_login"])) {
    $js_user_login = $_SESSION["dol_login"];
    $js_user_id = isset($_SESSION["dol_userid"]) ? intval($_SESSION["dol_userid"]) : 0;
} elseif (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION["user"])) {
    if (is_object($_SESSION["user"]) && !empty($_SESSION["user"]->login)) {
        $js_user_login = $_SESSION["user"]->login;
        $js_user_id = !empty($_SESSION["user"]->id) ? intval($_SESSION["user"]->id) : 0;
    } elseif (is_array($_SESSION["user"]) && !empty($_SESSION["user"]["login"])) {
        $js_user_login = $_SESSION["user"]["login"];
        $js_user_id = !empty($_SESSION["user"]["id"]) ? intval($_SESSION["user"]["id"]) : 0;
    }
}
// Pre-calcular las URLs de los scripts para evitar problemas de sintaxis
$js_backup_script_database = dol_buildpath("/custom/filemanager/scripts/create_backup_simple.php", 1);
$js_backup_script_files = dol_buildpath("/custom/filemanager/scripts/create_backup_files.php", 1);
$js_check_backup_status_url = dol_buildpath("/custom/filemanager/scripts/check_backup_status.php", 1);
?>
<script>
// Variables de usuario definidas desde PHP (fuera del código embebido)
var jsUserLogin = <?php echo json_encode($js_user_login, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var jsUserId = <?php echo intval($js_user_id); ?>;

// URLs de scripts definidas desde PHP (fuera del código embebido)
var jsBackupScripts = {
    database: <?php echo json_encode($js_backup_script_database, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    files: <?php echo json_encode($js_backup_script_files, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};
var jsCheckBackupStatusUrl = <?php echo json_encode($js_check_backup_status_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

// DEFINIR switchSetupTab INMEDIATAMENTE para que esté disponible cuando se carguen los botones
// Asegurar que esté disponible globalmente ANTES de que se carguen los botones
if (typeof window.switchSetupTab === 'undefined') {
    window.switchSetupTab = function switchSetupTab(tabName) {
    console.log('Cambiando a pestaña:', tabName);
    
    // Actualizar URL con el parámetro de pestaña
    var url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
    
    // Obtener elementos
        var configTab = document.getElementById("configTab");
        var backupTab = document.getElementById("backupTab");
        var logsTab = document.getElementById("logsTab");
        var maintenanceTab = document.getElementById("maintenanceTab");
        var aboutTab = document.getElementById("aboutTab");
        var configContent = document.getElementById("configContent");
        var backupContent = document.getElementById("backupContent");
        var logsContent = document.getElementById("logsContent");
        var maintenanceContent = document.getElementById("maintenanceContent");
        var aboutContent = document.getElementById("aboutContent");
    
    
    // Verificar que existen los elementos
    if (!configTab || !backupTab || !logsTab || !maintenanceTab || !aboutTab) {
        console.error("No se encontraron los elementos de pestañas");
        return false;
    }
    
    // Reset all tabs - usar las nuevas clases del diseño moderno
    [configTab, backupTab, logsTab, aboutTab].forEach(function(tab) {
        if (tab) {
            tab.classList.remove("active");
            tab.classList.add("inactive");
        }
    });
    
    // Hide all content
        if (configContent) configContent.style.display = "none";
        if (backupContent) backupContent.style.display = "none";
        if (logsContent) logsContent.style.display = "none";
        if (maintenanceContent) maintenanceContent.style.display = "none";
        if (aboutContent) aboutContent.style.display = "none";
    
    // Show selected tab and content
    if (tabName === "config" && configTab && configContent) {
        configTab.classList.remove("inactive");
        configTab.classList.add("active");
        configContent.style.display = "block";
    } else if (tabName === "backup" && backupTab && backupContent) {
        backupTab.classList.remove("inactive");
        backupTab.classList.add("active");
        backupContent.style.display = "block";
        } else if (tabName === "logs" && logsTab && logsContent) {
            logsTab.classList.remove("inactive");
            logsTab.classList.add("active");
            logsContent.style.display = "block";
        } else if (tabName === "maintenance" && maintenanceTab && maintenanceContent) {
            maintenanceTab.classList.remove("inactive");
            maintenanceTab.classList.add("active");
            maintenanceContent.style.display = "block";
        } else if (tabName === "about" && aboutTab && aboutContent) {
            aboutTab.classList.remove("inactive");
            aboutTab.classList.add("active");
            aboutContent.style.display = "block";
        }
    
    return true;
    };
}

// Función de traducción de respaldo (por si no está definida)
if (typeof t === 'undefined') {
    var t = function(key, defaultVal) {
        var translations = {
            'files_analysis': 'Análisis de Archivos',
            'analyzing_files_folders': 'Analizando archivos y carpetas...',
            'waiting_backup_start': 'Esperando inicio del backup...',
            'backup_log': 'Log del Backup'
        };
        return translations[key] || defaultVal || key;
    };
}

// Función para formatear tamaños automáticamente (KB, MB, GB)
function formatFileSize(sizeMB) {
    const size = parseFloat(sizeMB);
    if (isNaN(size) || size <= 0) {
        return '0 MB';
    }
    
    // Si es mayor o igual a 1024 MB, convertir a GB
    if (size >= 1024) {
        return (size / 1024).toFixed(2) + ' GB';
    }
    // Si es menor a 1 MB pero mayor a 0, mantener en MB
    else if (size >= 1) {
        return size.toFixed(2) + ' MB';
    }
    // Si es menor a 1 MB, convertir a KB
    else {
        return (size * 1024).toFixed(2) + ' KB';
    }
}

function selectAllExtensions() {
    var checkboxes = document.querySelectorAll('input[name="allowed_extensions[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = true;
    });
}

function deselectAllExtensions() {
    var checkboxes = document.querySelectorAll('input[name="allowed_extensions[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });
}

function autoDetectPath() {
    document.getElementById("rootPathInput").value = "<?php echo htmlspecialchars(DOL_DOCUMENT_ROOT); ?>";
}

// Variables globales para el backup
let currentBackupId = null;
let progressInterval = null;
let startTime = null;
let lastEstimatedTotal = 0;
let estimationSamples = [];
let databaseStats = null;
let immediateProgressInterval = null;
// Variables para el seguimiento del log
let lastLogLength = 0;
let lastLogContent = '';

function startBackup(type) {
    console.log('DEBUG: Iniciando backup tipo:', type);
    
    // VERIFICAR SI HAY UN BACKUP EN EJECUCIÓN ANTES DE INICIAR
    checkBackupStatusBeforeStart(type);
}

// Función para verificar el estado del backup antes de iniciar
function checkBackupStatusBeforeStart(type, callback) {
    // Si no se proporciona callback, usar función por defecto para backup
    if (!callback) {
        callback = function() { proceedWithBackup(type); };
    }
    
    fetch(jsCheckBackupStatusUrl + '?t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.is_running) {
                // Hay un backup en ejecución, mostrar alerta y NO iniciar
                Swal.fire({
                    icon: 'warning',
                    title: '⚠️ Backup en Ejecución',
                    html: 'Ya hay una copia de seguridad en ejecución.<br><br>' +
                          '<strong>Tipo:</strong> ' + (data.lock_type || 'Desconocido') + '<br>' +
                          (data.lock_info ? '<strong>Info:</strong> ' + data.lock_info + '<br>' : '') +
                          '<br>Por favor, espera a que finalice antes de iniciar otra copia de seguridad.',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#007bff'
                });
                // Bloquear todos los botones mientras hay backup en ejecución
                blockAllBackupButtons();
                return; // NO continuar con el inicio del backup
            }
            
            // ========== DETECTAR BACKUP INCOMPLETO Y REANUDAR AUTOMÁTICAMENTE ==========
            if (data.incomplete_backup && type === 'files_only') {
                var incomplete = data.incomplete_backup;
                console.log('🔄 DETECTADO BACKUP INCOMPLETO - Reanudando automáticamente...');
                console.log('   Backup ID:', incomplete.backup_id);
                console.log('   Progreso:', incomplete.percent + '%');
                console.log('   Archivos:', incomplete.files_added + '/' + incomplete.total_files);
                
                // Mostrar notificación breve y reanudar automáticamente
                Swal.fire({
                    icon: 'info',
                    title: '🔄 Reanudando Backup',
                    html: '<div style="text-align: left;">' +
                          '<p>Se detectó un backup incompleto y se reanudará automáticamente:</p>' +
                          '<ul style="margin: 10px 0;">' +
                          '<li><strong>Progreso:</strong> ' + incomplete.percent + '%</li>' +
                          '<li><strong>Archivos:</strong> ' + incomplete.files_added.toLocaleString() + ' / ' + incomplete.total_files.toLocaleString() + '</li>' +
                          '<li><strong>ZIP:</strong> ' + incomplete.zip_size_mb + ' MB</li>' +
                          '</ul></div>',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    allowOutsideClick: false
                }).then(function() {
                    // Iniciar backup con el mismo ID para que reanude
                    window.resumeBackupId = incomplete.backup_id;
                    proceedWithBackup(type, incomplete.backup_id);
                });
                return;
            }
            
            // No hay backup en ejecución ni incompleto, ejecutar callback normal
            callback();
        })
        .catch(error => {
            console.error('Error verificando estado del backup:', error);
            // Si hay error, permitir continuar (mejor intentar que bloquear)
            callback();
        });
}

// Función para bloquear todos los botones de backup
function blockAllBackupButtons() {
    const btnAnalyze = document.getElementById('btnAnalyze');
    const btnFiles = document.getElementById('btnFiles');
    
    if (btnAnalyze) {
        btnAnalyze.style.pointerEvents = 'none';
        btnAnalyze.style.opacity = '0.5';
        btnAnalyze.style.cursor = 'not-allowed';
    }
    if (btnFiles) {
        btnFiles.style.pointerEvents = 'none';
        btnFiles.style.opacity = '0.5';
        btnFiles.style.cursor = 'not-allowed';
    }
}

// Función para verificar periódicamente el estado del backup y bloquear/desbloquear botones
function monitorBackupStatus() {
    // ========== VERIFICACIÓN INICIAL: Detectar si se reabrió la pestaña con backup en progreso ==========
    fetch(jsCheckBackupStatusUrl + '?t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.is_running) {
                console.log('⚠️ BACKUP EN PROGRESO DETECTADO AL REABRIR LA PESTAÑA');
                
                // Bloquear botones
                blockAllBackupButtons();
                
                // Mostrar sección de progreso
                var progressSection = document.getElementById('backupProgressSection');
                if (progressSection) {
                    progressSection.style.display = 'block';
                }
                
                // Mostrar advertencia visual CRÍTICA
                showCriticalWarning();
                
                // Marcar que hay un backup en progreso
                manualBackupInProgress = true;
                
                // Intentar reconectar al progreso
                reconnectToBackup(data);
            }
        })
        .catch(error => {
            console.error('Error en verificación inicial:', error);
        });
    
    // Monitoreo continuo cada 5 segundos
    setInterval(function() {
        fetch(jsCheckBackupStatusUrl + '?t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                if (data.is_running) {
                    // Hay backup en ejecución, bloquear botones
                    blockAllBackupButtons();
                    
                    // Asegurar que la advertencia esté visible
                    showCriticalWarning();
                } else {
                    // No hay backup en ejecución, desbloquear botones si no hay backup manual en progreso
                    if (!manualBackupInProgress) {
                        restoreAllBackupButtons();
                        hideCriticalWarning();
                    }
                }
            })
            .catch(error => {
                console.error('Error monitoreando estado del backup:', error);
            });
    }, 5000); // Verificar cada 5 segundos
}

/**
 * Mostrar advertencia CRÍTICA con fondo negro y letras rojas
 * Para que el usuario NO cierre la pestaña durante el backup
 */
function showCriticalWarning() {
    var warningBanner = document.getElementById('criticalWarningBanner');
    if (!warningBanner) return;
    
    // Mostrar el banner
    warningBanner.style.display = 'block';
    
    // Agregar animación de pulso si no existe
    if (!document.getElementById('pulseAnimation')) {
        var style = document.createElement('style');
        style.id = 'pulseAnimation';
        style.textContent = `
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.7; }
            }
        `;
        document.head.appendChild(style);
    }
    
    // También cambiar el título de la pestaña del navegador
    if (!document.title.includes('⚠️')) {
        document.title = '⚠️ BACKUP EN PROGRESO - ' + document.title;
    }
}

/**
 * Ocultar advertencia CRÍTICA cuando el backup termina
 */
function hideCriticalWarning() {
    var warning = document.getElementById('criticalWarningBanner');
    if (warning) {
        warning.style.display = 'none';
    }
    
    // Restaurar título original
    document.title = document.title.replace('⚠️ BACKUP EN PROGRESO - ', '');
}

/**
 * Reconectar a un backup en progreso cuando se reabre la pestaña
 */
function reconnectToBackup(data) {
    console.log('🔄 Reconectando a backup en progreso...', data);
    
    // Si hay información del backup, intentar obtener el progreso actual
    if (data.backup_id) {
        currentBackupId = data.backup_id;
        
        // Iniciar polling del progreso
        if (!backupCheckInterval) {
            checkBackupProgress();
        }
    }
}

// Iniciar monitoreo cuando se carga la página
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Iniciando monitoreo de backups...');
        monitorBackupStatus();
    });
}

// Función para proceder con el backup (separada para reutilización)
// resumeId es opcional - si se proporciona, se reanudará un backup incompleto
function proceedWithBackup(type, resumeId) {
    if (resumeId) {
        console.log('DEBUG: 🔄 REANUDANDO backup tipo:', type, 'con ID:', resumeId);
        window.resumeBackupId = resumeId;
    } else {
        // Backup proceeding logs removed for performance
    }
    
    // MARCAR QUE HAY UN BACKUP MANUAL EN PROGRESO
    manualBackupInProgress = true;
    console.log('BACKUP MANUAL: Marcando manualBackupInProgress = true');
    
    
    // OCULTAR todos los botones excepto el seleccionado
    // Determinar qué botón mostrar según el tipo
    if (type === 'database_only') {
        // Ocultar todos excepto btnAnalyze (que se convierte en el botón activo)
        document.getElementById('btnFiles').style.display = 'none';
    } else if (type === 'files_only') {
        // Ocultar todos excepto btnFiles
        document.getElementById('btnAnalyze').style.display = 'none';
    }
    
    // Mostrar progreso
    document.getElementById('backupProgress').style.display = 'block';
    
    // Si es reanudación, mostrar mensaje diferente
    if (window.resumeBackupId) {
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.style.width = '30%';
            progressBar.textContent = '🔄';
        }
        const progressTextEl = document.getElementById('progressText');
        if (progressTextEl) {
            progressTextEl.textContent = '🔄 REANUDANDO backup incompleto...';
        }
    } else {
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
        }
        const progressTextEl = document.getElementById('progressText');
        if (progressTextEl) {
            progressTextEl.textContent = 'Iniciando backup...';
        }
    }
    // NO limpiar el log completamente - mantener mensaje inicial
    const backupLogElement = document.getElementById('backupLog');
    if (backupLogElement) {
        // Solo limpiar si no hay contenido del servidor aún
        // Esto permite que el log del análisis se muestre inmediatamente
        if (!backupLogElement.textContent.includes('[') || backupLogElement.textContent.length < 100) {
            backupLogElement.textContent = '🚀 Iniciando backup...\n⏳ Preparando análisis de archivos...\n';
        }
    }
    const progressTimeElement = document.getElementById('progressTime');
    if (progressTimeElement) {
        progressTimeElement.textContent = 'Tiempo: 0s';
    }
    
    // Resetear variables de log
    lastLogLength = 0;
    lastLogContent = '';
    
    startTime = Date.now();
    window.backupStartTime = startTime; // Guardar para el contador continuo
    
    // Iniciar contador continuo de tiempo transcurrido (se actualiza cada segundo)
    if (window.elapsedTimeInterval) {
        clearInterval(window.elapsedTimeInterval);
    }
    window.elapsedTimeInterval = setInterval(function() {
        if (window.backupStartTime && !manualBackupInProgress) {
            // Si el backup terminó, detener el contador
            clearInterval(window.elapsedTimeInterval);
            window.elapsedTimeInterval = null;
            return;
        }
        
        if (window.backupStartTime) {
            const currentElapsed = Math.floor((Date.now() - window.backupStartTime) / 1000);
            const elapsedStr = formatTime(currentElapsed);
            
            // Actualizar todos los elementos de tiempo transcurrido
            const progressTimeElements = document.querySelectorAll('#progressTime');
            progressTimeElements.forEach(function(el) {
                if (el) {
                    // Mantener el formato existente pero actualizar el tiempo transcurrido
                    const currentText = el.textContent;
                    if (currentText.includes('| Restante:')) {
                        // Extraer la parte de "Restante" si existe
                        const remainingMatch = currentText.match(/Restante: (.+)/);
                        const remainingStr = remainingMatch ? remainingMatch[1] : 'calculando...';
                        el.textContent = '⏱️ ' + elapsedStr + ' | Restante: ' + remainingStr;
                    } else {
                        el.textContent = '⏱️ ' + elapsedStr;
                    }
                }
            });
            
            // También actualizar backupEstimatedTime si existe
            const estimatedEl = document.getElementById('backupEstimatedTime');
            if (estimatedEl) {
                estimatedEl.textContent = elapsedStr;
            }
        }
    }, 1000); // Actualizar cada segundo
    
    // Generar ID de backup (o usar el ID de reanudación si existe)
    if (window.resumeBackupId) {
        currentBackupId = window.resumeBackupId;
        // Backup resume logs removed for performance
        window.resumeBackupId = null; // Limpiar para próximos backups
    } else {
        currentBackupId = new Date().toISOString().replace(/[-:T]/g, '').substring(0, 14);
        console.log('DEBUG: Backup ID generado:', currentBackupId);
    }
    
    // Iniciar progreso inmediato
    startImmediateProgress();
    
    // Iniciar monitoreo de progreso inmediatamente
    // NO iniciar monitoreo para backup de archivos (usa updateBar en startChunkedBackup)
    if (type !== 'files_only') {
        startProgressMonitoring(type);
    }
    
    // Ejecutar backup usando fetch (más confiable que iframe)
    console.log('DEBUG: Iniciando backup con fetch...');
    // Usar las variables de usuario ya definidas al inicio del script
    let userLogin = jsUserLogin;
    const userId = jsUserId;
    
    console.log('DEBUG: Usuario detectado - Login:', userLogin, 'ID:', userId);
    
    // Seleccionar el script correcto según el tipo de backup
    let scriptUrl = '';
    if (type === 'database_only') {
        scriptUrl = jsBackupScripts.database;
        console.log('DEBUG: Script seleccionado: create_backup_simple.php (Solo Base de Datos)');
    } else if (type === 'files_only') {
        // USAR SISTEMA DE CHUNKS - Funciona en cualquier hosting
        scriptUrl = jsBackupScripts.files;
        console.log('DEBUG: Script seleccionado: backup_chunk.php (Sistema de Chunks)');
        
        // Detener cualquier monitoreo anterior
        if (progressInterval) { clearInterval(progressInterval); progressInterval = null; }
        if (immediateProgressInterval) { clearInterval(immediateProgressInterval); immediateProgressInterval = null; }
        
        // Inicializar tiempo
        startTime = Date.now();
        window.backupStartTime = startTime; // Guardar para el contador continuo
        currentBackupId = new Date().toISOString().replace(/[-:T]/g, '').substring(0, 14);
        
        // Iniciar contador continuo de tiempo transcurrido (se actualiza cada segundo)
        if (window.elapsedTimeInterval) {
            clearInterval(window.elapsedTimeInterval);
        }
        window.elapsedTimeInterval = setInterval(function() {
            if (window.backupStartTime && !manualBackupInProgress) {
                // Si el backup terminó, detener el contador
                clearInterval(window.elapsedTimeInterval);
                window.elapsedTimeInterval = null;
                return;
            }
            
            if (window.backupStartTime) {
                const currentElapsed = Math.floor((Date.now() - window.backupStartTime) / 1000);
                const elapsedStr = formatTime(currentElapsed);
                
                // Actualizar todos los elementos de tiempo transcurrido
                const progressTimeElements = document.querySelectorAll('#progressTime');
                progressTimeElements.forEach(function(el) {
                    if (el) {
                        // Mantener el formato existente pero actualizar el tiempo transcurrido
                        const currentText = el.textContent;
                        if (currentText.includes('| Restante:')) {
                            // Extraer la parte de "Restante" si existe
                            const remainingMatch = currentText.match(/Restante: (.+)/);
                            const remainingStr = remainingMatch ? remainingMatch[1] : 'calculando...';
                            el.textContent = '⏱️ ' + elapsedStr + ' | Restante: ' + remainingStr;
                        } else {
                            el.textContent = '⏱️ ' + elapsedStr;
                        }
                    }
                });
                
                // También actualizar backupEstimatedTime si existe
                const estimatedEl = document.getElementById('backupEstimatedTime');
                if (estimatedEl) {
                    estimatedEl.textContent = elapsedStr;
                }
            }
        }, 1000); // Actualizar cada segundo
        
        // Mostrar sección de progreso
        document.getElementById('backupProgress').style.display = 'block';
        const pBar = document.getElementById('progressBar');
            pBar.style.width = '0%';
            pBar.textContent = '0%';
            pBar.style.background = '#007bff'; // AZUL
            const progressTextEl = document.getElementById('progressText');
            if (progressTextEl) {
                progressTextEl.textContent = 'Iniciando...';
            }
            const progressTimeEl = document.getElementById('progressTime');
            if (progressTimeEl) {
                progressTimeEl.textContent = '⏱️ 0s | Restante: calculando...';
            }
        
        const estEl = document.getElementById('backupEstimatedTime');
        const remEl = document.getElementById('backupRemainingTime');
        if (estEl) estEl.textContent = '0s';
        if (remEl) remEl.textContent = 'calculando...';
        
        // Mostrar recursos del servidor
        const serverResources = document.getElementById('serverResources');
        if (serverResources) {
            serverResources.style.display = 'block';
        }
        
        startChunkedBackup();
        return;
    } else {
        console.error('ERROR: Tipo de backup no válido:', type);
        alert('Tipo de backup no válido: ' + type);
        // Rehabilitar botones
        document.getElementById('btnAnalyze').style.pointerEvents = 'auto';
        document.getElementById('btnAnalyze').style.opacity = '1';
        document.getElementById('btnFiles').style.pointerEvents = 'auto';
        document.getElementById('btnFiles').style.opacity = '1';
        return;
    }
    
    const backupUrl = scriptUrl + '?action=init&backup_id=' + currentBackupId + '&user_login=' + encodeURIComponent(userLogin) + '&user_id=' + userId + '&automatic=0&t=' + Date.now();
    console.log('DEBUG: URL completa del backup:', backupUrl);
    
    fetch(backupUrl, {
        method: 'GET',
        cache: 'no-cache',
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(response => {
        // HTTP response logs removed for performance
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('DEBUG: Backup iniciado, respuesta:', data);
        // El script ahora corre en background, el fetch solo inicia el proceso
        // El monitoreo de progreso se encargará de actualizar la UI
    })
    .catch(error => {
        console.error('DEBUG: Error iniciando backup:', error);
        // Aún así, continuar monitoreando por si el script ya se ejecutó
        setTimeout(() => {
            console.log('DEBUG: Verificando si el backup comenzó a pesar del error...');
        }, 2000);
    });
}

// Variables para el watchdog de auto-reanudación
var lastProgressUpdate = Date.now();
var lastProgressValue = 0;
var watchdogStuckCount = 0;
var isAutoResuming = false;
var autoResumeAttempts = 0;
var maxAutoResumeAttempts = 10; // Máximo 10 intentos de auto-reanudación

function startProgressMonitoring(type = null) {
    console.log('DEBUG: Iniciando monitoreo de progreso para ID:', currentBackupId);
    
    // Si es backup de archivos, NO usar este monitoreo (usa updateBar en startChunkedBackup)
    if (type === 'files_only') {
        return; // El backup de archivos maneja su propia barra de progreso con updateBar()
    }
    
    // Resetear watchdog
    lastProgressUpdate = Date.now();
    lastProgressValue = 0;
    watchdogStuckCount = 0;
    
    // Detener progreso inmediato
    if (immediateProgressInterval) {
        clearInterval(immediateProgressInterval);
        immediateProgressInterval = null;
    }
    
    progressInterval = setInterval(() => {
        if (!currentBackupId) {
            console.warn('DEBUG: No hay backup ID, deteniendo monitoreo');
            clearInterval(progressInterval);
            progressInterval = null;
            return;
        }
        
        const progressUrl = '<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/get_progress.php'; ?>?backup_id=' + currentBackupId + '&t=' + Date.now();
        
        fetch(progressUrl, {
            method: 'GET',
            cache: 'no-cache',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data !== null && data !== undefined) {
                var currentProgress = data.progress || 0;
                
                // ========== WATCHDOG: Detectar si el proceso murió ==========
                if (currentProgress > lastProgressValue) {
                    // El progreso avanzó - resetear watchdog
                    lastProgressUpdate = Date.now();
                    lastProgressValue = currentProgress;
                    watchdogStuckCount = 0;
                } else if (currentProgress === lastProgressValue && currentProgress > 0 && currentProgress < 100) {
                    // El progreso está estancado
                    var stuckTime = (Date.now() - lastProgressUpdate) / 1000;
                    
                    if (stuckTime > 30) { // Más de 30 segundos sin progreso
                        watchdogStuckCount++;
                        console.warn('⚠️ WATCHDOG: Progreso estancado por ' + Math.round(stuckTime) + 's (conteo: ' + watchdogStuckCount + ')');
                        
                        // Mostrar advertencia en la UI
                        var progressText = document.getElementById('progressText');
                        if (progressText) {
                            progressText.innerHTML = '⚠️ Proceso posiblemente detenido... verificando...';
                        }
                        
                        // Si está estancado por más de 45 segundos, intentar reanudar
                        if (stuckTime > 45 && !isAutoResuming && autoResumeAttempts < maxAutoResumeAttempts) {
                            console.log('🔄 WATCHDOG: Iniciando auto-reanudación...');
                            triggerAutoResume();
                        }
                    }
                }
                
                updateProgress(currentProgress, data.log || '');
                
                if (data.completed) {
                    clearInterval(progressInterval);
                    progressInterval = null;
                    autoResumeAttempts = 0; // Resetear intentos al completar
                    
                    if (data.error) {
                        showError('Error en el backup: ' + (data.error_message || 'Error desconocido'));
                    } else {
                        manualBackupInProgress = false;
                        showSuccess('Backup completado exitosamente');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                }
            }
        })
        .catch(error => {
            console.error('DEBUG: Error obteniendo progreso:', error);
        });
    }, 1000);
}

// Función para auto-reanudar el backup cuando se detecta que murió
function triggerAutoResume() {
    if (isAutoResuming) return;
    isAutoResuming = true;
    autoResumeAttempts++;
    
    console.log('🔄 AUTO-RESUME: Intento ' + autoResumeAttempts + ' de ' + maxAutoResumeAttempts);
    
    // Detener el monitoreo actual
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }
    
    // Mostrar mensaje en la UI
    var progressText = document.getElementById('progressText');
    if (progressText) {
        progressText.innerHTML = '🔄 <strong>REANUDANDO AUTOMÁTICAMENTE</strong> (intento ' + autoResumeAttempts + '/' + maxAutoResumeAttempts + ')...';
    }
    
    // Esperar 2 segundos y luego reanudar
    setTimeout(function() {
        // Llamar a la verificación que detectará el checkpoint y reanudará
        fetch(jsCheckBackupStatusUrl + '?t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                isAutoResuming = false;
                
                if (data.incomplete_backup) {
                    console.log('🔄 AUTO-RESUME: Checkpoint encontrado, reanudando...');
                    
                    // Usar el backup_id del checkpoint
                    window.resumeBackupId = data.incomplete_backup.backup_id;
                    currentBackupId = data.incomplete_backup.backup_id;
                    
                    // Limpiar lock anterior
                    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/cleanup_locks.php", 1); ?>?t=' + Date.now())
                        .then(() => {
                            // Reiniciar el backup
                            var scriptUrl = jsBackupScripts.files;
                            var userLogin = jsUserLogin;
                            var userId = jsUserId;
                            
                            var backupUrl = scriptUrl + 
                                '?backup_type=files_only' +
                                '&backup_id=' + currentBackupId +
                                '&user=' + encodeURIComponent(userLogin) +
                                '&user_id=' + userId +
                                '&t=' + Date.now();
                            
                            console.log('🔄 AUTO-RESUME: Iniciando fetch a:', backupUrl);
                            
                            fetch(backupUrl, { method: 'GET', mode: 'no-cors' })
                                .then(() => {
                                    console.log('🔄 AUTO-RESUME: Backup reiniciado');
                                    // Reiniciar monitoreo
                                    lastProgressUpdate = Date.now();
                                    watchdogStuckCount = 0;
                                    startProgressMonitoring();
                                })
                                .catch(err => {
                                    console.error('🔄 AUTO-RESUME: Error reiniciando:', err);
                                    startProgressMonitoring(); // Reiniciar monitoreo de todas formas
                                });
                        });
                } else {
                    console.log('🔄 AUTO-RESUME: No hay checkpoint, posiblemente el backup terminó o falló');
                    // Reiniciar monitoreo para verificar estado
                    startProgressMonitoring();
                }
            })
            .catch(error => {
                console.error('🔄 AUTO-RESUME: Error verificando estado:', error);
                isAutoResuming = false;
                startProgressMonitoring(); // Reiniciar monitoreo
            });
    }, 2000);
}

// Función para formatear tiempo en formato legible
function formatTime(seconds) {
    if (seconds < 0) seconds = 0;
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    if (hours > 0) {
        return hours + 'h ' + minutes + 'm ' + secs + 's';
    } else if (minutes > 0) {
        return minutes + 'm ' + secs + 's';
    } else {
        return secs + 's';
    }
}

// Función para formatear tiempo estimado
function formatEstimatedTime(seconds) {
    if (seconds < 60) {
        return 'menos de 1 minuto';
    } else if (seconds < 3600) {
        const mins = Math.ceil(seconds / 60);
        return '~' + mins + ' minuto' + (mins > 1 ? 's' : '');
    } else {
        const hours = Math.floor(seconds / 3600);
        const mins = Math.ceil((seconds % 3600) / 60);
        return '~' + hours + 'h ' + mins + 'm';
    }
}

function updateProgress(percent, log) {
    // ========== MOSTRAR ADVERTENCIA CRÍTICA SI EL BACKUP ESTÁ EN PROGRESO ==========
    // Si el progreso es > 0 y < 100, mostrar advertencia
    if (percent > 0 && percent < 100) {
        showCriticalWarning();
    } else if (percent >= 100) {
        // Backup completado, ocultar advertencia
        hideCriticalWarning();
    }
    
    // Actualizar barra de progreso
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    
    if (progressBar) {
        progressBar.style.width = Math.max(0, Math.min(100, percent)) + '%';
        progressBar.textContent = Math.max(0, Math.min(100, percent)) + '%';
    }
    
    if (progressText) {
        progressText.textContent = 'Progreso: ' + Math.max(0, Math.min(100, percent)) + '%';
    }
    
    // Actualizar log solo si hay contenido nuevo
    if (log !== null && log !== undefined) {
        const backupLogElement = document.getElementById('backupLog');
        if (backupLogElement) {
            if (log !== lastLogContent && log.length > lastLogLength) {
                backupLogElement.textContent = log;
                lastLogContent = log;
                lastLogLength = log.length;
                backupLogElement.scrollTop = backupLogElement.scrollHeight;
            } else if (log !== lastLogContent) {
                backupLogElement.textContent = log;
                lastLogContent = log;
                lastLogLength = log.length;
                backupLogElement.scrollTop = backupLogElement.scrollHeight;
            }
        }
    }
    
    // Actualizar tiempo transcurrido y estimaciones
    if (startTime) {
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        const progressTime = document.getElementById('progressTime');
        const remainingEl = document.getElementById('backupRemainingTime');
        const estimatedEl = document.getElementById('backupEstimatedTime');
        const envEl = document.getElementById('backupEnvInfo');
        
        // Mostrar tiempo transcurrido formateado
        if (progressTime) {
            progressTime.textContent = 'Tiempo: ' + formatTime(elapsed);
        }
        
        // Detectar si está en fase de cierre del ZIP (93-99% o log contiene "CERRANDO" o "close()")
        const isClosingPhase = (percent >= 93 && percent < 100) || 
                               (log && (log.includes('CERRANDO') || log.includes('close()') || log.includes('Ejecutando zip')));
        
        // Calcular y mostrar tiempo restante estimado (TOTAL, no por secciones)
        if (percent > 3 && percent < 100) {
            // Calcular estimación actual
            var currentEstimate = (elapsed / percent) * 100;
            
            // Usar promedio móvil para suavizar (últimas 5 muestras)
            estimationSamples.push(currentEstimate);
            if (estimationSamples.length > 5) {
                estimationSamples.shift();
            }
            
            // Calcular promedio
            var avgEstimate = estimationSamples.reduce(function(a, b) { return a + b; }, 0) / estimationSamples.length;
            
            // Si el estimado cambia mucho, usar el mayor (más conservador)
            if (lastEstimatedTotal > 0 && Math.abs(avgEstimate - lastEstimatedTotal) > lastEstimatedTotal * 0.3) {
                avgEstimate = Math.max(avgEstimate, lastEstimatedTotal * 0.95);
            }
            lastEstimatedTotal = avgEstimate;
            
            var remaining = Math.max(0, avgEstimate - elapsed);
            
            if (isClosingPhase) {
                // Fase de cierre - agregar tiempo extra estimado
                remaining = Math.max(remaining, 30); // Mínimo 30 segundos en fase de cierre
                if (remainingEl) {
                    remainingEl.textContent = formatTime(remaining) + ' (finalizando)';
                    remainingEl.style.color = '#1976d2';
                }
            } else {
                if (remainingEl) {
                    remainingEl.textContent = formatTime(remaining);
                    if (remaining < 60) {
                        remainingEl.style.color = '#4caf50';
                    } else if (remaining < 300) {
                        remainingEl.style.color = '#ff9800';
                    } else {
                        remainingEl.style.color = '#d32f2f';
                    }
                }
            }
            
            if (estimatedEl) {
                estimatedEl.textContent = formatEstimatedTime(avgEstimate);
            }
        } else if (percent >= 100) {
            // Reset para próximo backup
            estimationSamples = [];
            lastEstimatedTotal = 0;
            
            if (remainingEl) {
                remainingEl.textContent = '✅ ¡Completado!';
                remainingEl.style.color = '#4caf50';
            }
            if (estimatedEl) {
                estimatedEl.textContent = formatTime(elapsed) + ' (total)';
            }
        }
        
        // Mostrar info del entorno (solo una vez)
        if (envEl && envEl.textContent === 'Detectando...') {
            const hostname = window.location.hostname;
            const isLocal = hostname === 'localhost' || hostname === '127.0.0.1' || hostname.includes('.local');
            envEl.textContent = isLocal ? '🖥️ Local (' + hostname + ')' : '🌐 Producción (' + hostname + ')';
        }
    }
    
    // Habilitar y MOSTRAR todos los botones cuando se complete
    if (percent >= 100) {
        manualBackupInProgress = false;
        restoreAllBackupButtons();
    }
}

// Función para copiar el log del backup al portapapeles
function copyBackupLog() {
    const logElement = document.getElementById('backupLog');
    if (!logElement) {
        alert('No hay log disponible');
        return;
    }
    
    const logText = logElement.textContent || logElement.innerText;
    
    // Intentar copiar al portapapeles
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(logText).then(function() {
            // Mostrar feedback visual
            const btn = document.getElementById('btnCopyLog');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
            btn.style.background = '#28a745';
            setTimeout(function() {
                btn.innerHTML = originalText;
                btn.style.background = '#17a2b8';
            }, 2000);
        }).catch(function(err) {
            // Fallback para navegadores que no soportan clipboard API
            fallbackCopyLog(logText);
        });
    } else {
        // Fallback para navegadores antiguos
        fallbackCopyLog(logText);
    }
}

// Fallback para copiar log en navegadores sin clipboard API
function fallbackCopyLog(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    if (document.body) {
    document.body.appendChild(textArea);
    }
    textArea.select();
    try {
        document.execCommand('copy');
        const btn = document.getElementById('btnCopyLog');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
        btn.style.background = '#28a745';
        setTimeout(function() {
            btn.innerHTML = originalText;
            btn.style.background = '#17a2b8';
        }, 2000);
    } catch (err) {
        alert('No se pudo copiar. Selecciona el log manualmente con Ctrl+A y Ctrl+C');
    }
    document.body.removeChild(textArea);
}

function stopBackup() {
    // Crear archivo de stop para que el backend lo detecte
    if (currentBackupId) {
        const stopUrl = '<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/stop_backup.php'; ?>?backup_id=' + currentBackupId + '&t=' + Date.now();
        fetch(stopUrl, { method: 'GET', cache: 'no-cache' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('🛑 Señal de stop enviada al backend');
                } else {
                    console.warn('⚠️ Error enviando señal de stop:', data.error || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('❌ Error al enviar señal de stop:', error);
            });
    }
    
    // Detener todos los intervalos
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }
    
    if (immediateProgressInterval) {
        clearInterval(immediateProgressInterval);
        immediateProgressInterval = null;
    }
    
    // Detener polling de logs
    if (chunkLogInterval) {
        clearInterval(chunkLogInterval);
        chunkLogInterval = null;
    }
    if (logPollingInterval) {
        clearInterval(logPollingInterval);
        logPollingInterval = null;
    }
    
    // Detener contador de tiempo progresivo si existe
    if (window.timeCountdownInterval) {
        clearInterval(window.timeCountdownInterval);
        window.timeCountdownInterval = null;
    }
    
    // Limpiar archivos del backup si hay un backupId
    if (currentBackupId) {
        const cleanupUrl = '<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/cleanup_backup.php'; ?>?backup_id=' + currentBackupId + '&t=' + Date.now();
        fetch(cleanupUrl, { method: 'GET', cache: 'no-cache' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('✅ Archivos del backup eliminados: ' + data.files_deleted + ' archivos (' + data.total_size_mb + ' MB)');
                } else {
                    console.warn('⚠️ Error limpiando archivos:', data.error || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('❌ Error al limpiar archivos del backup:', error);
            });
    }
    
    // Resetear variable de backup manual
    manualBackupInProgress = false;
    
    // Restaurar visibilidad de todos los botones
    restoreAllBackupButtons();
    
    // Ocultar progreso
    const backupProgress = document.getElementById('backupProgress');
    if (backupProgress) {
        backupProgress.style.display = 'none';
    }
    
    currentBackupId = null;
}

// Función para restaurar todos los botones de backup
function restoreAllBackupButtons() {
        const btnAnalyze = document.getElementById('btnAnalyze');
        const btnFiles = document.getElementById('btnFiles');
        
        if (btnAnalyze) {
        btnAnalyze.style.display = 'inline-flex';
            btnAnalyze.style.pointerEvents = 'auto';
            btnAnalyze.style.opacity = '1';
        }
        if (btnFiles) {
        btnFiles.style.display = 'inline-flex';
            btnFiles.style.pointerEvents = 'auto';
            btnFiles.style.opacity = '1';
    }
}

// Función para mostrar logs del backup
function showBackupLog(logId) {
    // Obtener el contenido del log desde la variable global
    var logContent = (typeof backupLogs !== 'undefined' && backupLogs[logId]) ? backupLogs[logId] : 'No se encontró el log';
    
    // Contar líneas
    var lineCount = (logContent.match(/\n/g) || []).length + 1;
    
    // Escapar HTML correctamente
    var escapedContent = logContent
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    
    // Formatear líneas con colores según contenido
    var formattedLines = escapedContent.split('\n').map(function(line, index) {
        var lineNum = String(index + 1).padStart(4, ' ');
        var color = '#00ff00'; // Verde por defecto
        var bgColor = 'transparent';
        
        // Colorear según tipo de mensaje
        if (line.includes('ERROR') || line.includes('❌')) {
            color = '#ff6b6b';
            bgColor = 'rgba(255,107,107,0.1)';
        } else if (line.includes('✅') || line.includes('COMPLETADO') || line.includes('correctamente')) {
            color = '#51cf66';
            bgColor = 'rgba(81,207,102,0.1)';
        } else if (line.includes('⚠️') || line.includes('WARNING') || line.includes('ADVERTENCIA')) {
            color = '#ffd43b';
            bgColor = 'rgba(255,212,59,0.1)';
        } else if (line.includes('═══') || line.includes('───') || line.includes('╔') || line.includes('╚') || line.includes('┌') || line.includes('└')) {
            color = '#74c0fc';
        } else if (line.includes('PROGRESO:') || line.includes('📊')) {
            color = '#69db7c';
        } else if (line.includes('📦') || line.includes('💾') || line.includes('🖥️')) {
            color = '#91a7ff';
        } else if (line.match(/^\[\d{2}:\d{2}:\d{2}\]/)) {
            // Timestamp en amarillo claro
            line = line.replace(/^(\[\d{2}:\d{2}:\d{2}\])/, '<span style="color:#ffd43b">$1</span>');
        }
        
        return '<div style="background:' + bgColor + ';padding:2px 0;border-bottom:1px solid #333;"><span style="color:#666;margin-right:10px;user-select:none;">' + lineNum + '</span><span style="color:' + color + '">' + line + '</span></div>';
    }).join('');
    
    // Crear modal con SweetAlert2
    Swal.fire({
        title: '<span style="font-size:18px;">📋 Logs de la Copia de Seguridad</span>',
        html: '<div style="margin-bottom:10px;padding:8px 12px;background:#2d3748;border-radius:6px;display:flex;justify-content:space-between;align-items:center;font-size:12px;">' +
              '<span style="color:#a0aec0;">📄 Total: <strong style="color:#fff;">' + lineCount + ' líneas</strong></span>' +
              '<span style="color:#a0aec0;">🔍 ID: <strong style="color:#fff;">' + logId + '</strong></span>' +
              '</div>' +
              '<div id="logContainer" style="text-align:left;height:60vh;max-height:500px;overflow-y:auto;background:linear-gradient(180deg,#1a1a2e 0%,#16213e 100%);padding:10px;border-radius:8px;font-family:Consolas,Monaco,monospace;font-size:11px;line-height:1.6;border:1px solid #333;">' + 
              formattedLines + 
              '</div>' +
              '<textarea id="rawLogContent" style="display:none;">' + logContent + '</textarea>' +
              '<div style="margin-top:10px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">' +
              '<button onclick="document.getElementById(\'logContainer\').scrollTop=0" style="background:#4a5568;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:11px;">⬆️ Inicio</button>' +
              '<button onclick="document.getElementById(\'logContainer\').scrollTop=document.getElementById(\'logContainer\').scrollHeight" style="background:#4a5568;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:11px;">⬇️ Final</button>' +
              '<button id="copyLogsBtn" onclick="copyLogsToClipboard(\'' + logId + '\')" style="background:#10b981;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:11px;">📋 Copiar</button>' +
              '<button onclick="downloadLogsAsTxt(\'' + logId + '\')" style="background:#3b82f6;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:11px;">💾 Descargar TXT</button>' +
              '</div>',
        width: '95%',
        maxWidth: '1000px',
        showCloseButton: true,
        showConfirmButton: true,
        confirmButtonText: 'Cerrar',
        confirmButtonColor: '#3182ce',
        background: '#1a202c',
        color: '#e2e8f0'
    });
}

// Función para copiar logs al portapapeles
function copyLogsToClipboard(logId) {
    var rawContent = document.getElementById('rawLogContent');
    if (!rawContent) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo acceder al contenido del log',
            background: '#1a202c',
            color: '#e2e8f0'
        });
        return;
    }
    
    // Copiar al portapapeles
    rawContent.select();
    rawContent.setSelectionRange(0, 99999); // Para móviles
    
    try {
        document.execCommand('copy');
        
        // Cambiar el texto del botón temporalmente
        var btn = document.getElementById('copyLogsBtn');
        var originalHTML = btn.innerHTML;
        btn.innerHTML = '✅ Copiado!';
        btn.style.background = '#51cf66';
        
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.style.background = '#10b981';
        }, 2000);
        
        // También intentar con la API moderna (si está disponible)
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(rawContent.value);
        }
        
    } catch (err) {
        // Fallback: mostrar el contenido para que el usuario lo copie manualmente
        Swal.fire({
            icon: 'info',
            title: 'Copiar manualmente',
            html: '<textarea readonly style="width:100%;height:300px;font-family:monospace;font-size:11px;background:#1a1a2e;color:#00ff00;padding:10px;border:1px solid #333;border-radius:4px;">' + rawContent.value + '</textarea>',
            confirmButtonText: 'Cerrar',
            background: '#1a202c',
            color: '#e2e8f0'
        });
    }
}

// Función para descargar logs como archivo TXT
function downloadLogsAsTxt(logId) {
    var rawContent = document.getElementById('rawLogContent');
    if (!rawContent) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo acceder al contenido del log',
            background: '#1a202c',
            color: '#e2e8f0'
        });
        return;
    }
    
    // Crear el nombre del archivo con timestamp
    var now = new Date();
    var timestamp = now.getFullYear() + 
                   String(now.getMonth() + 1).padStart(2, '0') + 
                   String(now.getDate()).padStart(2, '0') + '_' +
                   String(now.getHours()).padStart(2, '0') + 
                   String(now.getMinutes()).padStart(2, '0') + 
                   String(now.getSeconds()).padStart(2, '0');
    var fileName = 'backup_log_' + logId + '_' + timestamp + '.txt';
    
    // Crear blob con el contenido
    var blob = new Blob([rawContent.value], { type: 'text/plain;charset=utf-8' });
    
    // Crear enlace de descarga
    var link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.download = fileName;
    
    // Simular click para descargar
    if (document.body) {
    document.body.appendChild(link);
    }
    link.click();
    
    // Limpiar
    document.body.removeChild(link);
    window.URL.revokeObjectURL(link.href);
    
    // Notificación de éxito
    Swal.fire({
        icon: 'success',
        title: '¡Descargado!',
        text: 'El archivo ' + fileName + ' se ha descargado correctamente',
        timer: 2000,
        showConfirmButton: false,
        background: '#1a202c',
        color: '#e2e8f0'
    });
}

// Función para eliminar un log
function deleteLog(logId) {
    Swal.fire({
        title: '¿Eliminar este registro?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '🗑️ Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: '#1a202c',
        color: '#e2e8f0'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loader
            Swal.fire({
                title: 'Eliminando...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                background: '#1a202c',
                color: '#e2e8f0',
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Realizar la petición para eliminar el log
            fetch('<?php echo dol_buildpath('/custom/filemanager/action.php', 1); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete_log&log_id=' + logId + '&FILEMANAGER_TOKEN=<?php echo $_SESSION['newtoken']; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Eliminado!',
                        text: 'El registro ha sido eliminado correctamente',
                        timer: 2000,
                        showConfirmButton: false,
                        background: '#1a202c',
                        color: '#e2e8f0'
                    }).then(() => {
                        // Recargar la página para actualizar la tabla
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudo eliminar el registro',
                        background: '#1a202c',
                        color: '#e2e8f0'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al eliminar el registro: ' + error,
                    background: '#1a202c',
                    color: '#e2e8f0'
                });
            });
        }
    });
}

// Función para eliminar TODOS los logs
function deleteAllLogs() {
    Swal.fire({
        title: '⚠️ ¿Eliminar TODOS los registros?',
        html: '<div style="text-align: left; padding: 15px; background: #fef2f2; border-radius: 8px; border: 1px solid #fca5a5; margin-bottom: 15px;">' +
              '<p style="margin: 0 0 10px 0; color: #991b1b; font-weight: 600;">⚠️ Esta es una acción IRREVERSIBLE</p>' +
              '<p style="margin: 0; color: #7f1d1d; font-size: 14px;">Se eliminarán permanentemente todos los registros de actividad del FileManager.</p>' +
              '</div>' +
              '<p style="color: #6b7280; font-size: 14px;">Para confirmar, escribe: <strong style="color: #ef4444;">ELIMINAR TODO</strong></p>',
        input: 'text',
        inputPlaceholder: 'Escribe: ELIMINAR TODO',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '🗑️ Sí, eliminar todo',
        cancelButtonText: 'Cancelar',
        background: '#1a202c',
        color: '#e2e8f0',
        preConfirm: (value) => {
            if (value !== 'ELIMINAR TODO') {
                Swal.showValidationMessage('Debes escribir exactamente: ELIMINAR TODO');
                return false;
            }
            return true;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Segunda confirmación
            Swal.fire({
                title: '¿Estás completamente seguro?',
                text: 'Esta es tu última oportunidad para cancelar',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar todo ahora',
                cancelButtonText: 'No, cancelar',
                background: '#1a202c',
                color: '#e2e8f0'
            }).then((result2) => {
                if (result2.isConfirmed) {
                    // Mostrar loader
                    Swal.fire({
                        title: 'Eliminando todos los registros...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        background: '#1a202c',
                        color: '#e2e8f0',
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Realizar la petición para eliminar todos los logs
                    fetch('<?php echo dol_buildpath('/custom/filemanager/action.php', 1); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=delete_all_logs&FILEMANAGER_TOKEN=<?php echo $_SESSION['newtoken']; ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Eliminados!',
                                text: 'Todos los registros han sido eliminados correctamente',
                                timer: 2000,
                                showConfirmButton: false,
                                background: '#1a202c',
                                color: '#e2e8f0'
                            }).then(() => {
                                // Recargar la página para actualizar la tabla
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'No se pudieron eliminar los registros',
                                background: '#1a202c',
                                color: '#e2e8f0'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al eliminar los registros: ' + error,
                            background: '#1a202c',
                            color: '#e2e8f0'
                        });
                    });
                }
            });
        }
    });
}

function showError(message) {
    document.getElementById('backupLog').textContent += '\n❌ ERROR: ' + message;
    document.getElementById('backupLog').scrollTop = document.getElementById('backupLog').scrollHeight;
}

function showSuccess(message) {
    document.getElementById('backupLog').textContent += '\n✅ ' + message;
    document.getElementById('backupLog').scrollTop = document.getElementById('backupLog').scrollHeight;
}

let currentBackupType = null;

// Función para mostrar loader animado durante el análisis
function showAnalysisLoader(message = 'Analizando...') {
    const loaderHTML = `
        <div style="text-align: center; padding: 40px 20px;">
            <div style="margin-bottom: 20px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #007bff; animation: spin 1s linear infinite;"></i>
            </div>
            <div style="font-size: 18px; color: #495057; margin-bottom: 20px; font-weight: 500;">${message}</div>
            <div style="width: 100%; max-width: 400px; margin: 0 auto; background: #e9ecef; border-radius: 10px; height: 8px; overflow: hidden;">
                <div id="analysisProgressBar" style="height: 100%; background: linear-gradient(90deg, #007bff, #0056b3, #007bff); background-size: 200% 100%; border-radius: 10px; width: 0%; animation: progressAnimation 1.5s ease-in-out infinite; transition: width 0.3s ease;"></div>
            </div>
            <div style="font-size: 13px; color: #6c757d; margin-top: 15px;">Por favor, espere mientras se analizan los datos...</div>
        </div>
        <style>
            @keyframes progressAnimation {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }
        </style>
    `;
    
    // Solo limpiar statsContent si NO es análisis de archivos
    const previewTitle = document.getElementById('previewTitle');
    if (!previewTitle || !previewTitle.innerHTML.includes('Archivos')) {
        // No es análisis de archivos, usar el loader normal
    document.getElementById('statsContent').innerHTML = '';
    document.getElementById('analysisLoader').innerHTML = loaderHTML;
    document.getElementById('analysisLoader').style.display = 'block';
    } else {
        // Es análisis de archivos, NO mostrar el loader (ya tenemos el panel con estadísticas)
        return;
    }
    
    // Animar la barra de progreso
    let progress = 0;
    const progressBar = document.getElementById('analysisProgressBar');
    const progressInterval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90; // No llegar al 100% hasta que termine
        if (progressBar) {
            progressBar.style.width = progress + '%';
        }
    }, 300);
    
    // Guardar el intervalo para poder detenerlo
    window.currentAnalysisProgressInterval = progressInterval;
}

function stopAnalysisLoader() {
    if (window.currentAnalysisProgressInterval) {
        clearInterval(window.currentAnalysisProgressInterval);
        window.currentAnalysisProgressInterval = null;
    }
    document.getElementById('analysisLoader').style.display = 'none';
    document.getElementById('analysisLoader').innerHTML = '';
}

/**
 * Anima la actualización de estadísticas con efecto de conteo
 * @param {string} elementId - ID del elemento a actualizar
 * @param {number} targetValue - Valor objetivo
 * @param {boolean} isSize - Si es true, formatea como tamaño de archivo
 */
function updateStatsAnimated(elementId, targetValue, isSize = false) {
    const element = document.getElementById(elementId);
    if (!element) {
        // No mostrar warning si el elemento simplemente no existe todavía
        // (puede ser que el panel aún no esté visible)
        return;
    }
    
    const currentText = element.textContent || '0';
    let currentValue = 0;
    
    if (isSize) {
        // Para tamaños, extraer el número y convertir a MB
        const match = currentText.match(/[\d.]+/);
        if (match) {
            currentValue = parseFloat(match[0]);
            // Determinar la unidad y convertir a MB
            const unit = currentText.replace(/[\d.\s]/g, '').trim().toUpperCase();
            if (unit === 'GB') {
                currentValue = currentValue * 1024;
            } else if (unit === 'KB') {
                currentValue = currentValue / 1024;
            } else if (unit === 'B') {
                currentValue = currentValue / (1024 * 1024);
            }
            // Si es MB, ya está en MB
        }
    } else {
        // Para números, extraer el número sin comas
        currentValue = parseInt(currentText.replace(/,/g, '') || '0', 10);
    }
    
    const startValue = currentValue;
    const endValue = targetValue || 0;
    const duration = 800; // 800ms de animación
    const startTime = Date.now();
    
    function animate() {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function (ease-out)
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const current = startValue + (endValue - startValue) * easeOut;
        
        if (isSize) {
            element.textContent = formatFileSize(current);
        } else {
            element.textContent = Math.floor(current).toLocaleString();
        }
        
        if (progress < 1) {
            requestAnimationFrame(animate);
        } else {
            // Asegurar valor final exacto
            if (isSize) {
                element.textContent = formatFileSize(endValue);
            } else {
                element.textContent = Math.floor(endValue).toLocaleString();
            }
        }
    }
    
    animate();
}


function analyzeDatabase() {
    // Verificar si hay backup en ejecución antes de analizar
    checkBackupStatusBeforeStart('database_only', function() {
        // Callback cuando no hay backup en ejecución
        proceedWithAnalysis('database_only');
    });
}

function proceedWithAnalysis(type) {
    currentBackupType = type;
    
    // Ocultar todos los botones excepto el activo
    if (type === 'database_only') {
    document.getElementById('btnAnalyze').style.opacity = '0.7';
    document.getElementById('btnFiles').style.display = 'none';
    document.getElementById('previewPanel').style.display = 'block';
    document.getElementById('previewTitle').innerHTML = '<i class="fas fa-database"></i> Análisis de Base de Datos';
    showAnalysisLoader('Analizando estructura de la base de datos...');
    
    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/analyze_database.php", 1); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'token=<?php echo $token; ?>'
    })
    .then(r => r.json())
    .then(data => {
        stopAnalysisLoader();
        if (data.success) {
            let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px;">';
            
            // Card Tablas
            html += '<div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 10px; text-align: center; border-left: 4px solid #007bff;">';
            html += '<div style="font-size: 36px; color: #007bff; margin-bottom: 10px;"><i class="fas fa-table"></i></div>';
            html += '<div style="font-size: 14px; color: #6c757d; margin-bottom: 8px; font-weight: 500;">Tablas</div>';
            html += '<div style="font-size: 28px; font-weight: 700; color: #212529;">' + data.stats.total_tables + '</div>';
            html += '</div>';
            
            // Card Registros
            html += '<div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 10px; text-align: center; border-left: 4px solid #28a745;">';
            html += '<div style="font-size: 36px; color: #28a745; margin-bottom: 10px;"><i class="fas fa-list"></i></div>';
            html += '<div style="font-size: 14px; color: #6c757d; margin-bottom: 8px; font-weight: 500;">Registros</div>';
            html += '<div style="font-size: 28px; font-weight: 700; color: #212529;">' + data.stats.total_records.toLocaleString() + '</div>';
            html += '</div>';
            
            // Card Tamaño
            html += '<div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 20px; border-radius: 10px; text-align: center; border-left: 4px solid #ffc107;">';
            html += '<div style="font-size: 36px; color: #f39c12; margin-bottom: 10px;"><i class="fas fa-compress"></i></div>';
            html += '<div style="font-size: 14px; color: #856404; margin-bottom: 8px; font-weight: 500;">Tamaño Estimado</div>';
            html += '<div style="font-size: 28px; font-weight: 700; color: #856404;">' + formatFileSize(data.stats.estimated_zip_mb) + '</div>';
            html += '</div>';
            
            html += '</div>';
            
            // Tabla simplificada de tablas más grandes (solo si existen)
            if (data.stats.largest_tables && data.stats.largest_tables.length > 0) {
                html += '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">';
                html += '<div style="font-size: 14px; font-weight: 600; color: #495057; margin-bottom: 12px;"><i class="fas fa-star" style="color: #ffc107;"></i> Principales Tablas</div>';
                html += '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
                for (let i = 0; i < Math.min(5, data.stats.largest_tables.length); i++) {
                    const table = data.stats.largest_tables[i];
                    html += '<div style="background: white; padding: 8px 12px; border-radius: 6px; font-size: 12px; border: 1px solid #dee2e6;">';
                    html += '<span style="font-weight: 600; color: #495057;">' + table.name + '</span>';
                    html += ' <span style="color: #6c757d;">(' + table.rows.toLocaleString() + ')</span>';
                    html += '</div>';
                }
                html += '</div></div>';
            }
            
            document.getElementById('statsContent').innerHTML = html;
            document.getElementById('confirmBtn').style.display = 'inline-block';
        }
    })
    .catch(error => {
        stopAnalysisLoader();
        console.error('Error:', error);
        document.getElementById('statsContent').innerHTML = '<div class="info" style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 8px;">❌ Error al analizar: ' + error.message + '</div>';
    });
    } else if (type === 'files_only') {
    
    // Mostrar panel de preview PRIMERO
    document.getElementById('btnFiles').style.opacity = '0.7';
    document.getElementById('btnAnalyze').style.display = 'none';
    document.getElementById('previewPanel').style.display = 'block';
    document.getElementById('previewTitle').innerHTML = '<i class="fas fa-file-archive"></i> Análisis de Archivos';
    
    // Asegurar que statsContent esté visible (no oculto por el loader)
    const statsContent = document.getElementById('statsContent');
    if (statsContent) {
        statsContent.style.display = 'block';
    }
    
    // Ocultar el loader antiguo
    const analysisLoader = document.getElementById('analysisLoader');
    if (analysisLoader) {
        analysisLoader.style.display = 'none';
        analysisLoader.innerHTML = '';
    }
    
    // Resetear valores a 0 INMEDIATAMENTE
    const statFiles = document.getElementById('statFiles');
    const statFolders = document.getElementById('statFolders');
    const statSize = document.getElementById('statSize');
    if (statFiles) statFiles.textContent = '0';
    if (statFolders) statFolders.textContent = '0';
    if (statSize) statSize.textContent = '0 B';
    
    // Ocultar botón de confirmación hasta que termine
    document.getElementById('confirmBtn').style.display = 'none';
    
    // Mostrar barra de progreso
    const progressDiv = document.getElementById('analysisProgress');
    if (progressDiv) {
        progressDiv.style.display = 'block';
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.style.width = '0%';
        }
        // Mostrar ruta actual y archivos recientes
        const currentPathEl = document.getElementById('currentPath');
        if (currentPathEl) {
            currentPathEl.textContent = 'Limpiando progreso previo...';
            currentPathEl.style.color = '#6c757d';
        }
    }
    
    // LIMPIAR archivos de progreso previos ANTES de iniciar el análisis
    // Esto asegura que siempre empiece desde 0
    const cleanupProgressUrl = 'http://localhost/dolibarr/custom/filemanager/scripts/cleanup_analysis_progress.php';
    
    // HACER TODO SECUENCIAL - esperar a que termine la limpieza antes de continuar
    fetch(cleanupProgressUrl + '?t=' + Date.now(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'token=<?php echo $token; ?>'
    })
    .then(response => {
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (data.errors && data.errors.length > 0) {
                console.warn('⚠️ [DEBUG] Errores en limpieza:', data.errors);
            }
        } else {
            console.warn('⚠️ [DEBUG] Error en limpieza:', data.message);
        }
        
        // AHORA SÍ iniciar el análisis y monitoreo (solo si la limpieza fue exitosa o al menos se intentó)
        
        // Actualizar ruta para indicar que está iniciando
        const currentPathEl = document.getElementById('currentPath');
        if (currentPathEl) {
            currentPathEl.textContent = 'Iniciando análisis del sistema...';
            currentPathEl.style.color = '#007bff';
        }
        
        // Iniciar monitoreo de progreso en tiempo real
        let progressMonitorInterval = null;
        const progressUrl = 'http://localhost/dolibarr/custom/filemanager/scripts/get_analysis_progress.php';
        
        const startProgressMonitoring = () => {
        if (progressMonitorInterval) {
            clearInterval(progressMonitorInterval);
            // Interval cleared - debug logs removed for performance
        }
        
        // Esperar un poco antes de la primera consulta para dar tiempo a que se limpie el progreso previo
        // y se inicie el nuevo análisis
        setTimeout(() => {
            // Primera consulta después de la limpieza
            fetch(progressUrl + '?t=' + Date.now(), {
                method: 'GET',
                cache: 'no-cache'
            })
            .then(r => {
                return r.ok ? r.json() : null;
            })
            .then(data => {
                // Actualizar UI con datos válidos (siempre actualizar cuando hay datos válidos)
                if (data && data.success) {
                    // Datos válidos recibidos - actualizar UI
                    updateProgressUI(data);
                } else {
                    // Waiting for data logs removed for performance
                }
            })
            .catch(error => {
                console.error('Error en primera consulta:', error);
            });
        }, 300); // Esperar 300ms para dar tiempo a la limpieza
        
        // Luego consultar periódicamente cada 100ms para actualización más rápida
        let pollCount = 0;
        progressMonitorInterval = setInterval(() => {
            pollCount++;
            fetch(progressUrl + '?t=' + Date.now(), {
                method: 'GET',
                cache: 'no-cache'
            })
            .then(r => {
                if (!r.ok) {
                    if (pollCount % 10 === 0) {
                        console.warn('⚠️ [DEBUG] Poll #' + pollCount + ' - Respuesta no OK:', r.status);
                    }
                    return null;
                }
                return r.json();
            })
            .then(data => {
                if (data && data.success) {
                    
                    // IGNORAR datos si NO está corriendo Y el timestamp es muy antiguo (más de 2 minutos)
                    const now = Math.floor(Date.now() / 1000);
                    const timeSinceUpdate = now - (data.last_update || 0);
                    const isOldData = !data.running && timeSinceUpdate > 120;
                    
                    if (isOldData && pollCount % 10 === 0) {
                        console.warn('⚠️ [DEBUG] Poll #' + pollCount + ' - Datos antiguos detectados (hace ' + timeSinceUpdate + 's), ignorando...');
                    }
                    
                    // Solo actualizar UI si está corriendo O si los datos son recientes
                    if (data.running || !isOldData) {
                        updateProgressUI(data);
                    } else {
                        // Mantener valores en 0 si son datos antiguos
                        if (pollCount === 1 || pollCount % 10 === 0) {
                            // Keeping values at 0 (old data ignored) - debug logs removed for performance
                        }
                    }
                    
                    if (!data.running && data.stats && data.stats.total_files > 0 && !isOldData) {
                        // Análisis completado (solo si no son datos antiguos)
                        
                        // Asegurar que los valores finales se muestren
                        updateProgressUI(data);
                        
                        // Actualizar ruta actual para indicar que está completado
                        const currentPathEl = document.getElementById('currentPath');
                        if (currentPathEl) {
                            currentPathEl.textContent = '✓ Análisis completado';
                            currentPathEl.style.color = '#28a745';
                        }
                        
                        // Actualizar barra de progreso a 100%
                        const progressBar = document.getElementById('progressBar');
                        if (progressBar) {
                            progressBar.style.width = '100%';
                            progressBar.textContent = '100%';
                        }
                        
                        // MOSTRAR botón de confirmación
                        const confirmBtn = document.getElementById('confirmBtn');
                        if (confirmBtn) {
                            confirmBtn.style.display = 'inline-block';
                            confirmBtn.style.opacity = '0';
                            confirmBtn.style.transform = 'translateY(-10px)';
                            setTimeout(() => {
                                confirmBtn.style.transition = 'all 0.3s ease';
                                confirmBtn.style.opacity = '1';
                                confirmBtn.style.transform = 'translateY(0)';
                            }, 50);
                        }
                        
                        clearInterval(progressMonitorInterval);
                        progressMonitorInterval = null;
                    }
                } else if (pollCount % 10 === 0) {
                    // Debug logs removed for performance
                }
            })
            .catch(error => {
                if (pollCount % 10 === 0) {
                    console.error('Poll error:', error);
                }
            });
        }, 100); // Actualizar cada 100ms (MUY frecuente para ver progreso en tiempo real)
        };
        
        // Función para actualizar UI directamente con datos (sin depender del archivo)
        const updateProgressUIDirectly = (stats) => {
            // Progress UI updated - debug logs removed for performance
            const statFiles = document.getElementById('statFiles');
            const statFolders = document.getElementById('statFolders');
            const statSize = document.getElementById('statSize');
            const currentPathEl = document.getElementById('currentPath');
            const progressBar = document.getElementById('progressBar');
            
            if (statFiles && stats.total_files !== undefined) {
                statFiles.textContent = stats.total_files.toLocaleString();
            }
            if (statFolders && stats.total_folders !== undefined) {
                statFolders.textContent = stats.total_folders.toLocaleString();
            }
            if (statSize && stats.total_size_mb !== undefined) {
                statSize.textContent = formatFileSize(stats.total_size_mb);
            }
            if (currentPathEl) {
                currentPathEl.textContent = '✓ Análisis en progreso...';
                currentPathEl.style.color = '#007bff';
            }
            if (progressBar) {
                progressBar.style.width = '90%';
            }
        };
        
        // Función auxiliar para actualizar la UI con los datos de progreso
    const updateProgressUI = (data) => {
        // Log detallado de actualización
        if (data && data.stats && (data.stats.total_files > 0 || data.stats.total_folders > 0)) {
            console.log('🟢 [DEBUG] updateProgressUI - files:', data.stats.total_files, 'folders:', data.stats.total_folders, 'size:', data.stats.total_size_mb, 'running:', data.running);
        }
        // Actualizar estadísticas SIEMPRE, incluso si son 0
        if (data && data.stats) {
            const statFiles = document.getElementById('statFiles');
            const statFolders = document.getElementById('statFolders');
            const statSize = document.getElementById('statSize');
            
            if (statFiles) {
                const newValue = data.stats.total_files || 0;
                const currentValue = parseInt(statFiles.textContent.replace(/,/g, '') || '0', 10);
                // Actualizar SIEMPRE si cambió
                if (newValue !== currentValue) {
                    statFiles.textContent = newValue.toLocaleString();
                }
            }
            if (statFolders) {
                const newValue = data.stats.total_folders || 0;
                const currentValue = parseInt(statFolders.textContent.replace(/,/g, '') || '0', 10);
                // Actualizar SIEMPRE si cambió
                if (newValue !== currentValue) {
                    statFolders.textContent = newValue.toLocaleString();
                }
            }
            if (statSize) {
                const newValue = data.stats.total_size_mb || 0;
                const currentText = statSize.textContent || '0 B';
                const currentMatch = currentText.match(/[\d.]+/);
                const currentValue = currentMatch ? parseFloat(currentMatch[0]) : 0;
                const unit = currentText.replace(/[\d.\s]/g, '').trim().toUpperCase();
                let currentValueMB = currentValue;
                if (unit === 'GB') currentValueMB = currentValue * 1024;
                else if (unit === 'KB') currentValueMB = currentValue / 1024;
                else if (unit === 'B') currentValueMB = currentValue / (1024 * 1024);
                
                // Actualizar SIEMPRE si cambió significativamente
                if (Math.abs(newValue - currentValueMB) > 0.001) {
                    statSize.textContent = formatFileSize(newValue);
                }
            }
        }
        
        // Actualizar ruta actual SIEMPRE
        const currentPathEl = document.getElementById('currentPath');
        if (currentPathEl) {
            const newPath = data.current_path && data.current_path.trim() !== '' ? data.current_path.trim() : '';
            const currentPathText = currentPathEl.textContent || '';
            
            // Si hay una ruta nueva, actualizarla
            if (newPath !== '' && newPath !== currentPathText) {
                // Truncar ruta si es muy larga para mejor visualización
                const displayPath = newPath.length > 80 ? '...' + newPath.substring(newPath.length - 77) : newPath;
                currentPathEl.textContent = displayPath;
                currentPathEl.style.color = '#28a745';
                currentPathEl.title = newPath; // Tooltip con ruta completa
            } else if (newPath === '' && data.running !== false && currentPathText === 'Iniciando análisis del sistema...') {
                // Si está corriendo pero no hay ruta todavía, mostrar mensaje de escaneo
                currentPathEl.textContent = 'Escaneando sistema de archivos...';
                currentPathEl.style.color = '#007bff';
            } else if (newPath === '' && !data.running && currentPathText !== 'Análisis completado' && data.stats && data.stats.total_files > 0) {
                // Si no está corriendo y no hay ruta pero hay archivos, análisis completado
                currentPathEl.textContent = '✓ Análisis completado';
                currentPathEl.style.color = '#28a745';
                
                // Mostrar botón de confirmación si hay valores
                const confirmBtn = document.getElementById('confirmBtn');
                if (confirmBtn && data.stats.total_files > 0) {
                    confirmBtn.style.display = 'inline-block';
                    confirmBtn.style.opacity = '0';
                    confirmBtn.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        confirmBtn.style.transition = 'all 0.3s ease';
                        confirmBtn.style.opacity = '1';
                        confirmBtn.style.transform = 'translateY(0)';
                    }, 50);
                }
            }
        }
        
        // Actualizar barra de progreso (estimación basada en actividad)
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            const currentWidth = parseFloat(progressBar.style.width) || 0;
            
            // Si el análisis está corriendo, avanzar la barra
            if (data.running !== false) {
                if (data.stats && data.stats.total_files > 0) {
                    // Si hay archivos, avanzar basado en archivos encontrados (estimación)
                    // Asumimos que hay ~100k archivos máximo, así que cada 1000 archivos = 1%
                    const estimatedProgress = Math.min(90, (data.stats.total_files / 1000) * 1);
                    if (estimatedProgress > currentWidth) {
                        progressBar.style.width = estimatedProgress + '%';
                    } else if (currentWidth < 90) {
                        // Avanzar gradualmente incluso si no hay muchos archivos todavía
                        progressBar.style.width = Math.min(currentWidth + 0.3, 90) + '%';
                    }
                } else {
                    // Si no hay archivos todavía pero está corriendo, avanzar gradualmente
                    if (currentWidth < 20) {
                        progressBar.style.width = Math.min(currentWidth + 0.2, 20) + '%';
                    }
                }
            } else if (!data.running && data.stats && data.stats.total_files > 0) {
                // Análisis completado, mostrar 100%
                progressBar.style.width = '100%';
            }
            }
        };
        
        // Iniciar monitoreo DESPUÉS de iniciar el análisis (esperar 500ms para que el análisis cree el archivo)
        // Analysis step logs removed for performance
        fetch('<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/analyze_files.php'; ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'token=<?php echo $token; ?>'
    })
    .then(async r => {
        // Analyze response logs removed for performance
        if (!r.ok) {
            const text = await r.text();
            console.error('Error HTTP en analyze_files.php:', r.status, text.substring(0, 200));
            throw new Error('Error HTTP ' + r.status + ': ' + text.substring(0, 200));
        }
        const jsonData = await r.json();
        // Received data logs removed for performance
        return jsonData;
    })
    .then(data => {
        // Response processing logs removed for performance
        
        // SI el análisis es parcial, ACTUALIZAR el archivo de progreso INMEDIATAMENTE con los datos recibidos
        // Y TAMBIÉN mostrar los datos directamente en el frontend (fallback si falla la escritura)
        if (data.success && data.partial && data.stats) {
            // Partial analysis progress update - debug logs removed for performance
            updateProgressUIDirectly(data.stats);

            // SEGUNDO: Intentar actualizar el archivo (para el monitoreo)
            const updateProgressUrl = '<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/update_analysis_progress.php'; ?>';
            // Progress update URL and data logs removed for performance
            
            fetch(updateProgressUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'token=<?php echo $token; ?>&files=' + (data.stats.total_files || 0) + '&folders=' + (data.stats.total_folders || 0) + '&size=' + (data.stats.total_size_mb || 0)
            })
            .then(response => {
                // Analysis progress response logs removed for performance
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Error HTTP:', response.status, text);
                        throw new Error('HTTP ' + response.status + ': ' + text);
                    });
                }
                return response.json();
            })
            .then(result => {
                console.log('✅ [DEBUG] Progreso actualizado directamente:', result);
                if (result.success) {
                    console.log('✅ [DEBUG] Archivo actualizado - files:', result.files, 'folders:', result.folders, 'size:', result.size);
                } else {
                    console.warn('⚠️ [DEBUG] Error en actualización (pero datos ya mostrados en UI):', result.message);
                }
            })
            .catch(error => {
                console.warn('⚠️ [DEBUG] Error actualizando archivo (pero datos ya mostrados en UI):', error);
            });
            // Analysis completion check - debug logs removed for performance
        } else {
            // Non-partial analysis completion - debug logs removed for performance
        }
        
        // Si el análisis está completo (no parcial), mostrar el botón inmediatamente
        if (data.success && !data.partial) {
            console.log('✅ [DEBUG] Análisis de archivos COMPLETADO (no parcial) - mostrando botón de iniciar backup');
            const currentPathEl = document.getElementById('currentPath');
            if (currentPathEl) {
                currentPathEl.textContent = '✓ Análisis completado';
                currentPathEl.style.color = '#28a745';
            }
            if (data.stats) {
                updateProgressUIDirectly(data.stats);
            }
            // MOSTRAR EL BOTÓN DE INICIAR BACKUP
            document.getElementById('confirmBtn').style.display = 'inline-block';
        }

        // Escuchar eventos de continuación automática
        if (!window.analysisPartialListener) {
            window.analysisPartialListener = (event) => {
                console.log('🔄 [DEBUG] Evento analysisPartial recibido, continuando...');
                const data = event.detail;
                if (data && data.success && data.partial && data.stats) {
                    updateProgressUIDirectly(data.stats);
                    // Continuar automáticamente
                    setTimeout(() => {
        const analyzeFilesUrl = 'http://localhost/dolibarr/custom/filemanager/scripts/analyze_files.php';
        fetch(analyzeFilesUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: 'token=<?php echo $token; ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.partial) {
                                const event = new CustomEvent('analysisPartial', { detail: data });
                                document.dispatchEvent(event);
                            } else if (data.success) {
                                const currentPathEl = document.getElementById('currentPath');
                                if (currentPathEl) {
                                    currentPathEl.textContent = '✓ Análisis completado';
                                    currentPathEl.style.color = '#28a745';
                                }
                                if (data.stats) {
                                    updateProgressUIDirectly(data.stats);
                                }
                                // MOSTRAR EL BOTÓN DE INICIAR BACKUP CUANDO EL ANÁLISIS ESTÁ COMPLETO
                                document.getElementById('confirmBtn').style.display = 'inline-block';
                                console.log('✅ [DEBUG] Análisis de archivos completado - mostrando botón de iniciar backup');

                                // MOSTRAR PANEL DE DESCARGA DE CHUNKS DESPUÉS DEL ANÁLISIS
                                console.log('📦 Mostrando panel de descarga de chunks después del análisis');
                                const chunkPanel = document.getElementById('chunkDownloaderPanel');
                                if (chunkPanel) {
                                    // Cambiar el título para que sea contextual
                                    const titleEl = chunkPanel.querySelector('h4');
                                    if (titleEl) {
                                        titleEl.innerHTML = '<i class="fas fa-download"></i> Chunks Listos - Descarga Automática';
                                    }

                                    // Cambiar el color del panel para que sea verde (éxito)
                                    chunkPanel.style.borderLeftColor = '#4caf50';
                                    const header = chunkPanel.querySelector('div[style*="border-bottom"]');
                                    if (header) {
                                        header.style.borderBottomColor = '#4caf50';
                                    }

                                    // Mostrar el panel con animación
                                    chunkPanel.style.display = 'block';
                                    chunkPanel.style.opacity = '0';
                                    chunkPanel.style.transform = 'translateY(-10px)';
                                    setTimeout(() => {
                                        chunkPanel.style.transition = 'all 0.3s ease';
                                        chunkPanel.style.opacity = '1';
                                        chunkPanel.style.transform = 'translateY(0)';
                                    }, 100);

                                    console.log('✅ Panel de descarga de chunks mostrado');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error en continuación automática:', error);
                        });
                    }, 2000);
                }
            };
            document.addEventListener('analysisPartial', window.analysisPartialListener);
        }
        
        // Iniciar monitoreo DESPUÉS de que el análisis haya comenzado
        // Progress monitoring logs removed for performance
        setTimeout(() => {
            startProgressMonitoring();
        }, 500); // Esperar 500ms para que el análisis cree el archivo de progreso
        
        // Si el análisis es parcial (entorno restringido), continuar automáticamente
        if (data.success && data.partial) {
            
            // Actualizar estadísticas con los datos parciales
            if (data.stats) {
                const statFiles = document.getElementById('statFiles');
                const statFolders = document.getElementById('statFolders');
                const statSize = document.getElementById('statSize');
                if (statFiles && data.stats.total_files > parseInt(statFiles.textContent.replace(/,/g, '') || 0)) {
                    statFiles.textContent = data.stats.total_files.toLocaleString();
                }
                if (statFolders && data.stats.total_folders > parseInt(statFolders.textContent.replace(/,/g, '') || 0)) {
                    statFolders.textContent = data.stats.total_folders.toLocaleString();
                }
                if (statSize && data.stats.total_size_mb > 0) {
                    statSize.textContent = formatFileSize(data.stats.total_size_mb);
                }
            }
            
            // Actualizar ruta actual
            const currentPathEl = document.getElementById('currentPath');
            if (currentPathEl) {
                currentPathEl.textContent = '⏳ Continuando análisis... (' + (data.stats ? data.stats.total_files : 0) + ' archivos procesados)';
                currentPathEl.style.color = '#ffc107';
            }
            
            // Esperar 2 segundos y continuar el análisis automáticamente
            console.log('🔄 [DEBUG] Iniciando continuación automática del análisis en 2 segundos...');
            setTimeout(() => {
                console.log('🔄 [DEBUG] Continuando análisis automáticamente...');
                // Hacer otra llamada al análisis (se continuará desde donde quedó)
                const analyzeFilesUrl = '<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/analyze_files.php'; ?>';
                console.log('🔄 [DEBUG] Llamando a:', analyzeFilesUrl);
                
                fetch(analyzeFilesUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'token=<?php echo $token; ?>'
                })
                .then(async r => {
                    console.log('🔄 [DEBUG] Respuesta de continuación - Status:', r.status, 'OK:', r.ok);
                    if (!r.ok) {
                        const text = await r.text();
                        throw new Error('Error HTTP ' + r.status + ': ' + text.substring(0, 200));
                    }
                    return r.json();
                })
                .then(data => {
                    console.log('🔄 [DEBUG] Datos de continuación recibidos - success:', data.success, 'partial:', data.partial);
                    // Manejar recursivamente (puede ser parcial de nuevo)
                    if (data.success && data.partial && data.stats) {
                        console.log('🔄 [DEBUG] Análisis sigue siendo parcial, continuando...');
                        // Actualizar UI con nuevos datos
                        updateProgressUIDirectly(data.stats);
                        // Actualizar archivo de progreso
                        const updateProgressUrl = '<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/update_analysis_progress.php'; ?>';
                        fetch(updateProgressUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: 'token=<?php echo $token; ?>&files=' + (data.stats.total_files || 0) + '&folders=' + (data.stats.total_folders || 0) + '&size=' + (data.stats.total_size_mb || 0)
                        })
                        .then(response => response.json())
                        .then(result => {
                            console.log('🔄 [DEBUG] Progreso actualizado en continuación:', result);
                        })
                        .catch(error => {
                            console.warn('⚠️ [DEBUG] Error actualizando progreso en continuación:', error);
                        });
                        // Continuar automáticamente de nuevo (recursivo)
                        setTimeout(() => {
                            // Llamar a la misma función de continuación
                            const event = new CustomEvent('analysisPartial', { detail: data });
                            document.dispatchEvent(event);
                        }, 2000);
                    } else if (data.success && !data.partial) {
                        console.log('✅ [DEBUG] Análisis completado en continuación');
                        // Análisis completo
                        const currentPathEl = document.getElementById('currentPath');
                        if (currentPathEl) {
                            currentPathEl.textContent = '✓ Análisis completado';
                            currentPathEl.style.color = '#28a745';
                        }
                        if (data.stats) {
                            updateProgressUIDirectly(data.stats);
                            
                            // Mostrar botón de confirmación
                            const confirmBtn = document.getElementById('confirmBtn');
                            if (confirmBtn && data.stats.total_files > 0) {
                                console.log('✅ [DEBUG] Mostrando botón de confirmación (análisis completado)...');
                                confirmBtn.style.display = 'inline-block';
                                confirmBtn.style.opacity = '0';
                                confirmBtn.style.transform = 'translateY(-10px)';
                                setTimeout(() => {
                                    confirmBtn.style.transition = 'all 0.3s ease';
                                    confirmBtn.style.opacity = '1';
                                    confirmBtn.style.transform = 'translateY(0)';
                                }, 50);
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error en continuación:', error);
                    const currentPathEl = document.getElementById('currentPath');
                    if (currentPathEl) {
                        currentPathEl.textContent = '❌ Error: ' + error.message;
                        currentPathEl.style.color = '#dc3545';
                    }
                });
            }, 2000);
            
            // No limpiar intervalos todavía, seguir monitoreando
            return;
        }
        
        // Análisis completo
        // Limpiar intervalos de progreso
        if (window.analysisProgressInterval) {
            clearInterval(window.analysisProgressInterval);
            window.analysisProgressInterval = null;
        }
        if (progressMonitorInterval) {
            clearInterval(progressMonitorInterval);
            progressMonitorInterval = null;
        }
        
        // Asegurar que el loader esté completamente oculto
        const analysisLoader = document.getElementById('analysisLoader');
        if (analysisLoader) {
            analysisLoader.style.display = 'none';
            analysisLoader.style.visibility = 'hidden';
            analysisLoader.innerHTML = '';
        }
        // Asegurar que statsContent esté visible
        const statsContent = document.getElementById('statsContent');
        if (statsContent) {
            statsContent.style.display = 'block';
            statsContent.style.visibility = 'visible';
        }
        
        if (data.success) {
            // Completar barra de progreso
            const progressBar = document.getElementById('progressBar');
            if (progressBar) {
                progressBar.style.width = '100%';
            }
            
            // Actualizar ruta final
            const currentPathEl = document.getElementById('currentPath');
            if (currentPathEl) {
                currentPathEl.textContent = '✓ Análisis completado';
                currentPathEl.style.color = '#28a745';
            }
            
            // Ocultar barra de progreso después de un momento
            setTimeout(() => {
                const progressDiv = document.getElementById('analysisProgress');
                if (progressDiv) {
                    // Ocultar solo la sección de progreso, mantener la info
                    const progressBarContainer = progressDiv.querySelector('div:first-child');
                    if (progressBarContainer) {
                        progressBarContainer.style.display = 'none';
                    }
                }
            }, 1000);
            
            // Actualizar valores con animación (solo si los elementos existen)
            const statFilesEl = document.getElementById('statFiles');
            const statFoldersEl = document.getElementById('statFolders');
            const statSizeEl = document.getElementById('statSize');
            
            if (statFilesEl) {
                updateStatsAnimated('statFiles', data.stats.total_files);
            }
            if (statFoldersEl) {
                updateStatsAnimated('statFolders', data.stats.total_folders);
            }
            if (statSizeEl) {
                updateStatsAnimated('statSize', data.stats.estimated_zip_mb, true);
            }
            
            // Mostrar botón de confirmación después de la animación
            setTimeout(() => {
            document.getElementById('confirmBtn').style.display = 'inline-block';
                // Animación suave del botón
                const btn = document.getElementById('confirmBtn');
                btn.style.opacity = '0';
                btn.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    btn.style.transition = 'all 0.3s ease';
                    btn.style.opacity = '1';
                    btn.style.transform = 'translateY(0)';
                }, 50);
            }, 1100); // Esperar a que termine la animación
        }
    })
    .catch(error => {
        // Limpiar intervalos
        if (progressMonitorInterval) {
            clearInterval(progressMonitorInterval);
            progressMonitorInterval = null;
        }
        // Mostrar error en la ruta actual
        const currentPathEl = document.getElementById('currentPath');
        if (currentPathEl) {
            currentPathEl.textContent = '❌ Error: ' + error.message;
            currentPathEl.style.color = '#dc3545';
        }
        // Mostrar error
        const statsContent = document.getElementById('statsContent');
        if (statsContent) {
            statsContent.innerHTML = '<div class="info" style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 8px;">❌ Error al analizar: ' + error.message + '</div>';
        }
        });
    })
    .catch(error => {
        console.error('Error en limpieza de progreso:', error);
        // Continuar de todas formas, pero mostrar advertencia
        const currentPathEl = document.getElementById('currentPath');
        if (currentPathEl) {
            currentPathEl.textContent = '⚠️ Advertencia: No se pudo limpiar progreso previo, continuando...';
            currentPathEl.style.color = '#ffc107';
        }
        // Intentar iniciar el análisis de todas formas
        setTimeout(() => {
            const currentPathEl2 = document.getElementById('currentPath');
            if (currentPathEl2) {
                currentPathEl2.textContent = 'Iniciando análisis del sistema...';
                currentPathEl2.style.color = '#007bff';
            }
            // Iniciar análisis directamente
            // Starting analysis after cleanup error - debug logs removed for performance
            // TODO: Aquí deberíamos llamar al análisis, pero por ahora solo logueamos
        }, 1000);
    });
    }
}

function analyzeFiles() {
        // Analysis started - debug logs removed for performance
    // Verificar si hay backup en ejecución antes de analizar
    checkBackupStatusBeforeStart('files_only', function() {
        // Backup status check completed - debug logs removed for performance
        // Callback cuando no hay backup en ejecución
        proceedWithAnalysis('files_only');
    });
}


function confirmBackup() {
    document.getElementById('previewPanel').style.display = 'none';
    startBackup(currentBackupType);
}

function cancelAnalysis() {
    stopAnalysisLoader();
    document.getElementById('previewPanel').style.display = 'none';
    document.getElementById('statsContent').innerHTML = '';
    // Restaurar todos los botones usando la función centralizada
    restoreAllBackupButtons();
}

// ============================================================
// BACKUP POR CHUNKS - Funciona en CUALQUIER hosting
// ============================================================
var chunkLogInterval = null;
var logPollingInterval = null; // Variable global para el polling de logs

async function startChunkedBackup() {
    const chunkUrl = 'http://localhost/dolibarr/custom/filemanager/scripts/backup_chunk.php';
    const logUrl = 'http://localhost/dolibarr/custom/filemanager/scripts/get_log.php';
    
    // Detectar entorno restringido desde el servidor
    const isRestricted = <?php
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = preg_match('/(\d+)([KMGT]?)/i', $memoryLimit, $m) ?
            (int)$m[1] * (strtoupper($m[2] ?? '') === 'M' ? 1024*1024 : (strtoupper($m[2] ?? '') === 'K' ? 1024 : 1)) : 0;
        $maxExec = (int)ini_get('max_execution_time');

        // Considerar recursos ilimitados como NO restringidos
        $unlimitedMemory = ($memoryLimit === '-1' || $memoryBytes >= 512*1024*1024);
        $unlimitedTime = ($maxExec === 0 || $maxExec >= 120);
        $isActuallyRestricted = ($memoryBytes <= 128*1024*1024 && $memoryBytes > 0) && ($maxExec <= 30 && $maxExec > 0);

        echo ($isActuallyRestricted && !$unlimitedMemory && !$unlimitedTime) ? 'true' : 'false';
    ?>;
    
    // SISTEMA ADAPTATIVO - OPTIMIZADO PARA HOSTINGS CON LÍMITE DE CONEXIONES
    const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    
    // Configuración adaptativa según entorno
    let chunkSize, MIN_CHUNK, MAX_CHUNK, TARGET_TIME_MS;
    
    if (isRestricted) {
        // ENTORNO ULTRA-RESTRINGIDO: Chunks MUY pequeños
        chunkSize = 100;
        MIN_CHUNK = 50;
        MAX_CHUNK = 200;
        TARGET_TIME_MS = 15000; // 15s
        console.log('🔒 Modo ULTRA-RESTRINGIDO activado: chunks de 100 archivos');
    } else {
        // Entorno normal: CHUNKS MUY GRANDES para máximo rendimiento
        chunkSize = isLocalhost ? 8000 : 8000;
        MIN_CHUNK = 3000;
        MAX_CHUNK = 15000;
        TARGET_TIME_MS = 30000; // 30s para procesamiento más extenso
    }
    let consecutiveSuccess = 0;
    let consecutiveErrors = 0;
    const MAX_RETRIES = 3;
    
    if (isRestricted) {
        // Modo ULTRA-RESTRINGIDO: Chunks pequeños
    } else {
        // Modo ADAPTATIVO (optimizado velocidad)
    }
    
    // Mostrar área de logs INMEDIATAMENTE
    const logElement = document.getElementById('backupLog');
    if (logElement) {
        logElement.style.display = 'block';
        // Mostrar mensaje inicial mientras se carga el log del servidor
        logElement.textContent = '═══════════════════════════════════════════════════════════\n';
        logElement.textContent += '🚀 INICIANDO BACKUP DE ARCHIVOS\n';
        logElement.textContent += '═══════════════════════════════════════════════════════════\n';
        logElement.textContent += '📂 Iniciando análisis de archivos del sistema...\n';
        logElement.textContent += '⏳ Cargando log detallado del servidor...\n';
        logElement.textContent += '💡 El log del análisis aparecerá aquí en unos segundos\n';
        logElement.textContent += '───────────────────────────────────────────────────────────\n';
        logElement.scrollTop = logElement.scrollHeight;
    }
    
    function addLog(msg) {
        if (logElement) {
            const time = new Date().toLocaleTimeString();
            logElement.textContent += '[' + time + '] ' + msg + '\n';
            logElement.scrollTop = logElement.scrollHeight;
        }
    }
    
    function updateBar(percent, text, elapsed, remaining) {
        // Buscar la barra de progreso correcta (la del backup, no la del análisis)
        const backupProgressDiv = document.getElementById('backupProgress');
        let bar = null;
        if (backupProgressDiv && backupProgressDiv.style.display !== 'none') {
            // Si el div de backup está visible, usar su barra
            bar = backupProgressDiv.querySelector('#progressBar');
        }
        // Si no se encuentra, buscar directamente
        if (!bar) {
            bar = document.getElementById('progressBar');
        }
        
        if (bar) {
            // Asegurar que el porcentaje esté entre 0 y 100
            const safePercent = Math.max(0, Math.min(100, percent));
            
            // Actualizar ancho y texto
            bar.style.width = safePercent + '%';
            if (bar.textContent !== undefined) {
                bar.textContent = Math.round(safePercent) + '%';
            }
            
            // Cambiar color según progreso
            if (safePercent >= 100) {
                bar.style.background = 'linear-gradient(90deg, #28a745, #20c997)';
            } else if (safePercent >= 75) {
                bar.style.background = 'linear-gradient(90deg, #17a2b8, #20c997)';
            } else if (safePercent >= 50) {
                bar.style.background = 'linear-gradient(90deg, #007bff, #17a2b8)';
            } else {
                bar.style.background = 'linear-gradient(90deg, #ffc107, #ff9800)';
            }
            
            bar.style.transition = 'width 0.3s ease';
            bar.style.minWidth = safePercent > 0 ? '40px' : '0'; // Mínimo visible
            
            // Forzar repaint para asegurar que se vea
            bar.offsetHeight; // Trigger reflow
        }
        
        // Actualizar progressText con el texto completo (primera fila)
        const progressText = document.getElementById('progressText');
        if (progressText) {
            progressText.textContent = text;
        }
        
        // Actualizar tiempo - buscar todos los elementos posibles
        const progressTimeElements = document.querySelectorAll('#progressTime');
        const remainingEl = document.getElementById('backupRemainingTime');
        
        if (elapsed !== undefined && elapsed !== null) {
            const elapsedStr = elapsed || '0s';
            const remainingStr = remaining || 'calculando...';
            
            // Actualizar todos los elementos de tiempo encontrados (primera fila)
            progressTimeElements.forEach(function(el) {
                if (el) {
                    el.textContent = '⏱️ ' + elapsedStr + ' | Restante: ~' + remainingStr;
                }
            });
            
            if (remainingEl) {
                remainingEl.textContent = 'Restante: ~' + remainingStr;
            }
        }
    }
    
    // Función para actualizar recursos del servidor en tiempo real
    function updateServerResources(resources) {
        const resourcesDiv = document.getElementById('serverResources');
        if (!resourcesDiv) return;
        
        // Mostrar el div de recursos si está oculto
        if (resourcesDiv.style.display === 'none') {
            resourcesDiv.style.display = 'block';
        }
        
        // Actualizar RAM PHP (DETALLADO)
        if (resources.php_memory) {
            const ramUsed = document.getElementById('serverRamUsed');
            const ramLimit = document.getElementById('serverRamLimit');
            const ramPercent = document.getElementById('serverRamPercent');
            const ramAvailable = document.getElementById('serverRamAvailable');
            const ramPeak = document.getElementById('serverRamPeak');
            const ramDetail = document.getElementById('serverRamDetail');
            
            const used = parseFloat(resources.php_memory.used_mb || 0);
            const limit = parseFloat(resources.php_memory.limit_mb || 0);
            const percent = parseFloat(resources.php_memory.usage_percent || 0);
            
            if (ramUsed) ramUsed.textContent = used.toFixed(1);
            if (ramLimit) ramLimit.textContent = limit.toFixed(1);
            if (ramPercent) {
                ramPercent.textContent = percent.toFixed(1);
                // Cambiar color según uso
                ramPercent.style.color = percent > 90 ? '#d32f2f' : (percent > 70 ? '#ff9800' : '#4caf50');
                ramPercent.style.fontWeight = 'bold';
            }
            if (ramAvailable) ramAvailable.textContent = (resources.php_memory.available_mb || 0).toFixed(1);
            if (ramPeak) ramPeak.textContent = (resources.php_memory.peak_mb || 0).toFixed(1);
            if (ramDetail) {
                ramDetail.textContent = resources.php_memory.usage_detail || `${used.toFixed(1)}MB usados de ${limit.toFixed(1)}MB límite PHP`;
            }
        }
        
        // Actualizar información de tiempo del servidor (NUEVO)
        if (resources.server_info) {
            const chunkTime = document.getElementById('serverChunkTime');
            const maxTime = document.getElementById('serverMaxTime');
            const timeDetail = document.getElementById('serverTimeDetail');
            
            const chunkTimeVal = parseFloat(resources.server_info.max_execution_time_used || 0);
            const maxTimeVal = parseFloat(resources.server_info.max_execution_time || 0);
            
            if (chunkTime) chunkTime.textContent = chunkTimeVal.toFixed(2);
            if (maxTime) maxTime.textContent = maxTimeVal > 0 ? maxTimeVal : '∞';
            if (timeDetail) {
                timeDetail.textContent = resources.server_info.time_limit_detail || 
                    `${chunkTimeVal.toFixed(1)}s usados de ${maxTimeVal > 0 ? maxTimeVal + 's' : 'sin límite'} límite servidor`;
                
                // Cambiar color según uso del tiempo
                if (maxTimeVal > 0) {
                    const timePercent = (chunkTimeVal / maxTimeVal) * 100;
                    timeDetail.style.color = timePercent > 90 ? '#d32f2f' : (timePercent > 70 ? '#ff9800' : '#4caf50');
                }
            }
        }
        
        // Actualizar información ZIP (DETALLADO)
        if (resources.zip_info) {
            const zipSize = document.getElementById('serverZipSize');
            const zipFiles = document.getElementById('serverZipFiles');
            const chunksDetail = document.getElementById('serverChunksDetail');
            
            if (zipSize) zipSize.textContent = (resources.zip_info.size_mb || 0).toFixed(1);
            if (zipFiles) zipFiles.textContent = (resources.zip_info.files_in_zip || 0).toLocaleString();
            if (chunksDetail) {
                chunksDetail.textContent = resources.zip_info.chunks_detail || 
                    `${resources.zip_info.chunks_count || 0} de ~${resources.zip_info.total_chunks_estimated || 0} chunks completados`;
            }
        }
        
        // Actualizar rendimiento (DETALLADO)
        if (resources.performance) {
            const speed = document.getElementById('serverSpeed');
            const avgSpeed = document.getElementById('serverAvgSpeed');
            const avgSpeedReal = document.getElementById('serverAvgSpeedReal');
            
            const speedVal = parseInt(resources.performance.speed_files_sec || 0);
            const avgSpeedVal = parseFloat(resources.performance.avg_speed_files_sec || 0);
            const avgSpeedRealVal = parseInt(resources.performance.avg_speed_real || 0);
            
            if (speed) speed.textContent = speedVal.toLocaleString();
            if (avgSpeed) avgSpeed.textContent = avgSpeedVal.toFixed(1);
            if (avgSpeedReal) avgSpeedReal.textContent = avgSpeedRealVal.toLocaleString();
            
            // Si no existe el elemento de tiempo del chunk, actualizarlo desde performance
            const chunkTime = document.getElementById('serverChunkTime');
            if (chunkTime && (!chunkTime.textContent || chunkTime.textContent === '0')) {
                chunkTime.textContent = (resources.performance.chunk_time_sec || 0).toFixed(2);
            }
        }
    }
    
    // Función mejorada para cargar logs del servidor
    let lastLogLength = 0;
    let logLoadAttempts = 0;
    let serverLogLoaded = false;
    let initialLocalLog = '';
    async function loadServerLogs(backupId) {
        console.log('🔍 [DEBUG] Intentando cargar logs del servidor...');
        console.log('🔍 [DEBUG] backupId:', backupId);
        console.log('🔍 [DEBUG] logUrl:', logUrl);

        if (!backupId) {
            logLoadAttempts++;
            // No backupId - canceling log loading - debug logs removed for performance

            // Si no hay backupId después de varios intentos, mostrar mensaje
            if (logLoadAttempts > 3 && logElement && !serverLogLoaded) {
                const currentText = logElement.textContent;
                if (!currentText.includes('Esperando backupId')) {
                    logElement.textContent += '\n⏳ Esperando inicialización del backup...\n';
                    logElement.textContent += '💡 Si tarda mucho, puede haber un error en el servidor.\n';
                    logElement.textContent += '🔍 Revisa la consola del navegador (F12) para más detalles.\n';
                    logElement.scrollTop = logElement.scrollHeight;
                }
            }
            return;
        }

        // Guardar el log local inicial si es la primera vez
        if (!initialLocalLog && logElement) {
            initialLocalLog = logElement.textContent;
        }

        try {
            const fullUrl = logUrl + '?backup_id=' + backupId + '&t=' + Date.now();
            console.log('🔍 [DEBUG] URL completa:', fullUrl);

            const res = await fetch(fullUrl);
            console.log('🔍 [DEBUG] Código de respuesta HTTP:', res.status);

            const data = await res.json();
            // Log data received - debug logs removed for performance

            if (data.success && data.log) {
                // SIEMPRE reemplazar con el log del servidor cuando esté disponible
                // Esto asegura que veamos TODO lo que está pasando en el análisis
                if (data.log.trim().length > 0) {
                    console.log('✅ [DEBUG] Logs cargados exitosamente, longitud:', data.log.length);

                    // Si el log del servidor tiene contenido, reemplazar TODO
                    // Esto muestra el análisis completo desde el servidor
                    logElement.textContent = data.log;
                    logElement.scrollTop = logElement.scrollHeight;
                    lastLogLength = data.log.length;
                    serverLogLoaded = true;
                } else if (!serverLogLoaded && logElement) {
                    console.log('⚠️ [DEBUG] Respuesta con success pero log vacío');

                    // Si aún no hay log del servidor pero tenemos log local, mantenerlo
                    // Solo agregar un mensaje indicando que se está esperando
                    const currentText = logElement.textContent;
                    if (!currentText.includes('Cargando log del servidor')) {
                        logElement.textContent += '\n⏳ Cargando log detallado del servidor...\n';
                        logElement.scrollTop = logElement.scrollHeight;
                    }
                }
            } else if (data.error) {
                // Server error logged - debug logs removed for performance

                // Si hay error, agregarlo al log solo si no lo hemos mostrado antes
                if (logElement) {
                    const currentText = logElement.textContent;
                    if (!currentText.includes(data.error)) {
                        let errorMessage = '\n⚠️ Error del servidor: ' + data.error + '\n';

                        // Mensajes más específicos para errores comunes
                        if (data.error.includes('Timeout') || data.error.includes('tardó demasiado')) {
                            errorMessage = '\n⏰ TIMEOUT DEL SERVIDOR: El análisis tardó más de lo esperado.\n';
                            errorMessage += '💡 El servidor tiene un límite de 30s para evitar sobrecargas.\n';
                            errorMessage += '🔄 Intenta nuevamente o contacta al administrador si persiste.\n';
                            errorMessage += '📊 Si tienes muchos archivos, considera dividir el backup.\n';
                        } else if (data.error.includes('memoria')) {
                            errorMessage = '\n💾 MEMORIA INSUFICIENTE: El servidor no tiene suficiente memoria.\n';
                            errorMessage += '📞 Contacta al administrador del hosting para aumentar el límite.\n';
                        }

                        logElement.textContent += errorMessage;
                        logElement.scrollTop = logElement.scrollHeight;
                    }
                }
            } else {
                console.log('⚠️ [DEBUG] Respuesta sin success ni error:', data);

                if (!serverLogLoaded && logElement) {
                    // Si no hay log aún, mantener el log local y agregar mensaje
                    const currentText = logElement.textContent;
                    if (!currentText.includes('Esperando log del servidor')) {
                        logElement.textContent += '\n⏳ Esperando log del servidor (el análisis puede tardar unos segundos)...\n';
                        logElement.scrollTop = logElement.scrollHeight;
                    }
                }
            }
        } catch(e) {
            console.error('Error de red/exception:', e);

            // Solo mostrar error si es relevante y no lo hemos mostrado antes
            if (logLoadAttempts % 10 === 0 && logElement) {
                const currentText = logElement.textContent;
                if (!currentText.includes('Error de red cargando logs')) {
                    logElement.textContent += '\n⚠️ Error de red cargando logs (intentando de nuevo...)\n';
                    logElement.scrollTop = logElement.scrollHeight;
                }
            }
            logLoadAttempts++;
        }
    }
    
    // Función para iniciar carga de logs en tiempo real
    function startLogPolling(backupId, fastMode = false) {
        if (logPollingInterval) {
            clearInterval(logPollingInterval);
            logPollingInterval = null;
        }
        if (backupId) {
            // Cargar inmediatamente
            loadServerLogs(backupId);
            // Durante análisis: cada 1 segundo (fastMode)
            // Durante procesamiento: cada 1.5 segundos
            const interval = fastMode ? 1000 : 1500;
            logPollingInterval = setInterval(() => loadServerLogs(backupId), interval);
        }
    }
    
    try {
        // PASO 1: Inicializar
                addLog('═══════════════════════════════════════════════════════════');
        addLog('🚀 INICIANDO BACKUP DE ARCHIVOS');
                addLog('═══════════════════════════════════════════════════════════');
        addLog('📂 Iniciando análisis de archivos del sistema...');
        addLog('🔧 Chunk inicial: ' + chunkSize + ' (modo ADAPTATIVO: ' + (isLocalhost ? 'local' : 'producción') + ')');
                addLog('');
        addLog('⏳ ESPERANDO LOG DEL SERVIDOR...');
        addLog('💡 El log detallado del análisis aparecerá aquí en unos segundos');
        addLog('📋 Esto incluye: progreso, archivos grandes detectados, velocidad, etc.');
        addLog('───────────────────────────────────────────────────────────');
        updateBar(5, '📂 Analizando archivos del sistema...', '0s', 'calculando...');
        
        // Iniciar polling de logs ANTES del init para capturar el análisis
        // Usaremos un backupId temporal o esperaremos a obtenerlo
        let tempBackupId = null;
        
        const initRes = await fetch(chunkUrl + '?action=init&chunk_size=' + chunkSize, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
            
            // Verificar respuesta HTTP
            if (!initRes.ok) {
                throw new Error('Error HTTP ' + initRes.status + ': ' + initRes.statusText);
            }
            
            // Verificar que es JSON válido
            const initText = await initRes.text();
            let initData;
                    
                    // Verificar si es HTML (sesión expirada)
                    if (initText.trim().startsWith('<!DOCTYPE') || initText.trim().startsWith('<html') || 
                        initText.includes('Login') || initText.includes('Conexi') || initText.includes('Login @')) {
                        addLog('❌ ERROR: Sesión expirada');
                        addLog('⚠️ El servidor requiere autenticación.');
                        addLog('💡 Solución: Recarga la página (F5) y vuelve a intentar.');
                        throw new Error('Sesión expirada. Recarga la página para continuar.');
                    }
                    
                    // Verificar si es un error JSON válido
                    if (initText.trim().startsWith('{') && initText.includes('"error"')) {
                        try {
                            const errorData = JSON.parse(initText);
                            if (errorData.error && errorData.error.includes('Sesión expirada')) {
                                addLog('❌ ERROR: ' + errorData.error);
                                addLog('💡 Solución: Recarga la página (F5) y vuelve a intentar.');
                                throw new Error(errorData.error);
                            }
                        } catch (e) {
                            // No es JSON válido, continuar con el procesamiento normal
                        }
                    }
                    
            try {
                initData = JSON.parse(initText);
            } catch (e) {
                        console.error('Respuesta no es JSON:', initText.substring(0, 500));
                        addLog('❌ ERROR: Respuesta del servidor inválida');
                addLog('📋 Longitud respuesta: ' + initText.length + ' bytes');
                if (initText.length === 0) {
                    addLog('⚠️ El servidor no devolvió datos - posible TIMEOUT');
                    throw new Error('Timeout del servidor. Verifica max_execution_time en PHP.');
                } else {
                            addLog('📄 Respuesta: ' + initText.substring(0, 200) + '...');
                            throw new Error('Respuesta inválida del servidor. Recarga la página.');
                }
            }
            
            if (!initData.success) {
                throw new Error(initData.error || 'Error inicializando');
            }
            
        const backupId = initData.backup_id;
        const totalFiles = (initData.stats && initData.stats.total_files) || initData.total_files || initData.files_found || 0;
        const totalFolders = (initData.stats && initData.stats.total_folders) || 0;
        const totalSizeMB = (initData.stats && initData.stats.total_size_mb) || 0;
        const filesFound = initData.files_found || totalFiles;
        const expectedFromAnalysis = initData.expected_from_analysis;
        let processed = initData.processed || 0;

        currentBackupId = backupId;

        // ACTUALIZAR UI INMEDIATAMENTE con los datos del análisis
        console.log('🟢 [INIT] Actualizando UI con datos del análisis:', {
            totalFiles, totalFolders, totalSizeMB
        });

        const statFiles = document.getElementById('statFiles');
        const statFolders = document.getElementById('statFolders');
        const statSize = document.getElementById('statSize');

        if (statFiles) statFiles.textContent = totalFiles.toLocaleString();
        if (statFolders) statFolders.textContent = totalFolders.toLocaleString();
        if (statSize) statSize.textContent = totalSizeMB > 0 ? formatFileSize(totalSizeMB) : '0 B';
        
        // INICIAR CARGA DE LOGS INMEDIATAMENTE EN MODO RÁPIDO
        // Esto capturará el log del análisis que acaba de ocurrir
        addLog('📥 Cargando log del análisis del servidor...');
        startLogPolling(backupId, true); // true = modo rápido (1 segundo)
        chunkLogInterval = logPollingInterval; // Mantener compatibilidad
        
        // Cargar log múltiples veces al inicio para asegurar que se capture
        // El log puede tardar unos segundos en generarse
        loadServerLogs(backupId);
        setTimeout(() => loadServerLogs(backupId), 500);
        setTimeout(() => loadServerLogs(backupId), 1000);
        setTimeout(() => loadServerLogs(backupId), 2000);
        setTimeout(() => loadServerLogs(backupId), 3000);
        
        // Mostrar información del análisis
        if (expectedFromAnalysis) {
            addLog('📊 Análisis previo: ' + expectedFromAnalysis.toLocaleString() + ' archivos esperados');
            addLog('📂 Archivos encontrados: ' + filesFound.toLocaleString());
            if (filesFound !== expectedFromAnalysis) {
                const diff = Math.abs(filesFound - expectedFromAnalysis);
                if (filesFound < expectedFromAnalysis) {
                    addLog('⚠️ Diferencia: -' + diff.toLocaleString() + ' archivos (se usarán los encontrados)');
                                        } else {
                    addLog('ℹ️ Se encontraron ' + diff.toLocaleString() + ' archivos adicionales');
                                        }
                                    }
            addLog('📦 Total para backup: ' + totalFiles.toLocaleString() + ' archivos');
                            } else {
            addLog('✅ ' + totalFiles.toLocaleString() + ' archivos encontrados');
        }
        
        if (isRestricted) {
            addLog('🔒 Modo ULTRA-RESTRINGIDO activado');
            addLog('📦 Iniciando compresión (chunk inicial: ' + chunkSize + ' archivos)');
        } else {
        addLog('📦 Iniciando compresión adaptativa (chunk inicial: ' + chunkSize + ')');
        }
        
        // Mostrar información del entorno si está disponible
        if (initData.env_info) {
            const env = initData.env_info;
            addLog('🔍 Entorno detectado:');
            addLog('   Tipo: ' + (env.server_type || 'unknown'));
            addLog('   Memoria disponible: ' + (env.memory_available_mb || 0) + ' MB');
            addLog('   Capacidad máxima por chunk: ' + (initData.max_mb_per_chunk || 0) + ' MB');
        }
        
        updateBar(10, '📦 Preparando ' + totalFiles.toLocaleString() + ' archivos...', '0s', 'calculando...');
        
        // Cambiar a modo normal de polling (menos frecuente) para procesamiento
        // Dar un momento para que se cargue el log del análisis completo
        setTimeout(() => {
            startLogPolling(backupId, false); // false = modo normal (1.5 segundos)
            chunkLogInterval = logPollingInterval;
        }, 2000);
        
        // PASO 2: Procesar chunks con SISTEMA ADAPTATIVO
        let chunkNum = 0;
        let totalChunksCreated = 0;
        // Chunk processing started - debug logs removed for performance
        
        // Limitar chunks para evitar loop infinito
        const maxChunks = Math.ceil(totalFiles / chunkSize) + 10; // +10 por seguridad
        let chunksProcessed = 0;

        // VERIFICACIÓN DE CONECTIVIDAD ANTES DE EMPEZAR - FIX 2025-12-17 07:30
        // alert('🔥 DEBUG: Verificación de conectividad iniciada - FIX APLICADO');
        // Server connectivity check - debug logs removed for performance
        try {
            const testUrl = chunkUrl + '?action=status&backup_id=' + backupId;
            console.log('🔍 [CHUNKS] URL de prueba:', testUrl);

            const testController = new AbortController();
            const testTimeout = setTimeout(() => testController.abort(), 10000);

            const testRes = await fetch(testUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: testController.signal
            });

            clearTimeout(testTimeout);
            console.log('✅ [CHUNKS] Conectividad OK - Status:', testRes.status);

            if (!testRes.ok) {
                const errorText = await testRes.text();
                console.error('❌ [CHUNKS] Error de conectividad:', testRes.status, errorText.substring(0, 200));
                addLog('❌ ERROR: No se puede conectar con el servidor de backup');
                return;
            }
        } catch (connectError) {
            console.error('❌ [CHUNKS] Error de conectividad:', connectError.message);
            addLog('❌ ERROR: Problema de conexión con el servidor - ' + connectError.message);
            return;
        }


        while (processed < totalFiles && chunksProcessed < maxChunks) {
            chunkNum++;
            chunksProcessed++;
            let retryCount = 0;
            let chunkSuccess = false;


            while (!chunkSuccess && retryCount < MAX_RETRIES) {
                const chunkStartTime = Date.now();
                
                try {
                    const fullUrl = chunkUrl + '?action=process&backup_id=' + backupId + '&chunk_number=' + chunkNum + '&chunk_size=' + chunkSize;
                    // Chunk request details - debug logs removed for performance

                    // SOLUCIÓN RADICAL: TIMEOUT CON SKIP DE CHUNK

                    let processRes;
                    let fetchCompleted = false;

                    // Fetch normal
                    const fetchPromise = fetch(fullUrl, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    // Timeout adaptativo según configuración del entorno
                    const timeoutPromise = new Promise((_, reject) => {
                        setTimeout(() => {
                            if (!fetchCompleted) {
                                console.warn(`⏰ [CHUNKS] Chunk #${chunkNum} - TIMEOUT ${TARGET_TIME_MS/1000}s - SALTANDO CHUNK`);
                                reject(new Error('SKIP_CHUNK'));
                            }
                        }, TARGET_TIME_MS); // Usar tiempo objetivo del entorno
                    });

                    try {
                        // Waiting for chunk response - debug logs removed for performance
                        processRes = await Promise.race([fetchPromise, timeoutPromise]);
                        fetchCompleted = true;
                        console.log(`✅ [CHUNKS] Chunk #${chunkNum} OK - Status: ${processRes.status}`);

                    } catch (error) {
                        if (error.message === 'SKIP_CHUNK') {
                            // REDUCIR CHUNK SIZE GLOBAL en lugar de saltar - lógica mejorada
                            const oldSize = chunkSize;
                            chunkSize = Math.max(MIN_CHUNK, Math.floor(chunkSize * 0.7)); // Reducir 30%
                            consecutiveErrors++;
                            consecutiveSuccess = 0;

                            addLog(`⏰ TIMEOUT en chunk #${chunkNum} - Reduciendo chunk size global: ${oldSize} → ${chunkSize}`);
                            console.warn(`⏰ [CHUNKS] Chunk #${chunkNum} TIMEOUT - Reduciendo chunk size ${oldSize} → ${chunkSize} y continuando`);

                            // NOTA: Los archivos del chunk timeout no se procesarán para evitar complejidad
                            // Pero el backup continúa con chunks más pequeños para evitar futuros timeouts
                            addLog(`⚠️ ATENCIÓN: ${chunkSize} archivos del chunk #${chunkNum} no se procesaron por timeout`);
                            continue;
                        } else {
                            console.error(`❌ [CHUNKS] Error fatal en chunk #${chunkNum}:`, error.message);
                            alert(`❌ Error fatal en chunk #${chunkNum}: ${error.message}`);
                            throw error;
                        }
                    }
                    const chunkTime = Date.now() - chunkStartTime;
                    
                    console.log(`🔍 [CHUNKS] Chunk #${chunkNum} - Respuesta recibida, status:`, processRes.status, `(tiempo: ${chunkTime}ms)`);
                    
                    // Verificar respuesta HTTP
                    if (!processRes.ok) {
                        // Detectar específicamente error 503 (Service Unavailable)
                        if (processRes.status === 503) {
                            console.error(`❌ [CHUNKS] Chunk #${chunkNum} - HTTP 503 (Service Unavailable)`);
                            throw new Error('HTTP 503 - Service Unavailable (Timeout del servidor). El chunk es demasiado grande.');
                        }
                        console.error(`❌ [CHUNKS] Chunk #${chunkNum} - HTTP Error:`, processRes.status);
                        throw new Error('HTTP ' + processRes.status);
                    }
                    
                    const processText = await processRes.text();
                    let processData;
                    
                    // Verificar si es HTML (sesión expirada)
                    if (processText.trim().startsWith('<!DOCTYPE') || processText.trim().startsWith('<html') || 
                        processText.includes('Login') || processText.includes('Conexi')) {
                        console.error(`❌ [CHUNKS] Chunk #${chunkNum} - Sesión expirada`);
                        addLog('❌ ERROR: Sesión expirada durante el procesamiento');
                        throw new Error('Sesión expirada. Recarga la página para continuar.');
                    }
                    
                    try {
                        processData = JSON.parse(processText);
                    } catch (e) {
                        console.error(`❌ [CHUNKS] Chunk #${chunkNum} - Error parseando JSON:`, e);
                        console.error(`❌ [CHUNKS] Respuesta recibida (primeros 500 chars):`, processText.substring(0, 500));
                        addLog('❌ ERROR: Respuesta inválida del servidor');
                        throw new Error('Respuesta inválida del servidor');
                    }
                    
                    // Chunk response data - debug logs removed for performance
                    
                    if (!processData.success) {
                        console.error(`❌ [CHUNKS] Chunk #${chunkNum} - Error del servidor:`, processData.error);
                        console.error(`❌ [CHUNKS] Respuesta completa:`, processData);
                        throw new Error(processData.error || 'Error servidor');
                    }
                    
                    // ÉXITO - Ajustar chunk_size dinámicamente usando información del servidor
                    chunkSuccess = true;
                    consecutiveErrors = 0;
                    consecutiveSuccess++;
                    
                    // Obtener información del tipo de archivo y sugerencia del servidor
                    const fileType = processData.file_type || 'mixed';
                    const suggestedChunkSize = processData.suggested_chunk_size;
                    const chunkAvgSizeKB = processData.chunk_avg_size_kb || 0;
                    const chunkLargeRatio = processData.chunk_large_ratio || 0;
                    const chunkSizeMB = processData.chunk_size_mb || 0;
                    const maxMbPerChunk = processData.max_mb_per_chunk || 0;
                    const serverType = processData.server_type || 'unknown';
                    const memoryAvailable = processData.memory_available_mb || 0;
                    
                    // Verificar uso de memoria del servidor
                    const serverMemory = processData.memory_mb || 0;
                    const serverMemoryPeak = processData.memory_peak_mb || 0;
                    const memoryOk = serverMemoryPeak < 800; // Menos de 800MB está bien
                    
                    // Obtener zipSize ANTES de usarlo (definir aquí para que esté disponible)
                    const zipSize = processData.zip_size_mb || 0;
                    processed = processData.processed;
                    const percent = Math.min(100, processData.percent || Math.round((processed / totalFiles) * 100));
                    
                    // Log detallado del progreso
                    if (processData.is_complete !== undefined) {
                        console.log(`🔍 [CHUNKS] Chunk #${chunkNum} - is_complete:`, processData.is_complete);
                    }
                    if (processData.chunk_zips && Array.isArray(processData.chunk_zips)) {
                        console.log(`🔍 [CHUNKS] Chunk #${chunkNum} - Total chunks ZIP en estado:`, processData.chunk_zips.length);
                        if (processData.chunk_zips.length > 0) {
                            const chunkNumbers = processData.chunk_zips.map(c => c.number).sort((a, b) => a - b);
                            console.log(`🔍 [CHUNKS] Chunk #${chunkNum} - Números de chunks ZIP:`, chunkNumbers);
                        }
                    }
                    
                    // Ajuste simple: usar sugerencia del servidor o ajustar según ZIP
                    if (suggestedChunkSize && suggestedChunkSize !== chunkSize) {
                        const oldSize = chunkSize;
                        chunkSize = Math.max(MIN_CHUNK, Math.min(MAX_CHUNK, suggestedChunkSize));
                        if (oldSize !== chunkSize) {
                            addLog('🔄 Ajuste: chunk ' + oldSize + ' → ' + chunkSize);
                            consecutiveSuccess = 0;
                        }
                    }
                    // Si ZIP es muy grande, reducir chunks
                    else if (zipSize > 500 && chunkSize > MIN_CHUNK * 2) {
                        const oldSize = chunkSize;
                        chunkSize = Math.max(MIN_CHUNK, Math.round(chunkSize * 0.7));
                        addLog('📦 ZIP grande (' + zipSize.toFixed(1) + 'MB) - Reduciendo chunk: ' + oldSize + ' → ' + chunkSize);
                            consecutiveSuccess = 0;
                        }
                    // Si memoria alta, reducir
                    else if (serverMemoryPeak > 700 && chunkSize > MIN_CHUNK * 2) {
                        const oldSize = chunkSize;
                        chunkSize = Math.max(MIN_CHUNK, Math.round(chunkSize * 0.8));
                        addLog('📉 Memoria alta - Reduciendo chunk: ' + oldSize + ' → ' + chunkSize);
                        consecutiveSuccess = 0;
                    }
                    
                    // Calcular tiempo transcurrido - usar del servidor si está disponible
                    let elapsedSec = Math.floor((Date.now() - startTime) / 1000);
                    if (processData.time && processData.time.elapsed_seconds !== null && processData.time.elapsed_seconds !== undefined) {
                        elapsedSec = Math.floor(processData.time.elapsed_seconds);
                    }
                    
                    // Calcular tiempo restante: usar el del servidor si está disponible, sino calcular localmente
                    let etaSec = null;
                    
                    // Promedio móvil más reactivo para tiempo restante más preciso
                    if (!window.etaHistory) {
                        window.etaHistory = [];
                    }
                    const MAX_ETA_HISTORY = 5; // Reducir a 5 para ser más reactivo
                    
                    if (processData.time && processData.time.estimated_remaining_seconds !== null && processData.time.estimated_remaining_seconds !== undefined) {
                        // Usar tiempo del servidor (más preciso)
                        const serverEta = processData.time.estimated_remaining_seconds;
                        
                        // Agregar a historial
                        window.etaHistory.push(serverEta);
                        if (window.etaHistory.length > MAX_ETA_HISTORY) {
                            window.etaHistory.shift(); // Eliminar el más antiguo
                        }
                        
                        // Calcular promedio móvil (más reactivo)
                        const avgEta = window.etaHistory.reduce((a, b) => a + b, 0) / window.etaHistory.length;
                        
                        // Calcular ETA basado en velocidad REAL actual también
                        const speed = elapsedSec > 0 ? Math.round(processed / elapsedSec) : 0;
                        const remainingFiles = totalFiles - processed;
                        const realEta = speed > 0 ? Math.round(remainingFiles / speed) : 0;
                        
                        // Usar el menor entre promedio móvil y cálculo real (más optimista y preciso)
                        if (realEta > 0) {
                            etaSec = Math.min(Math.round(avgEta), realEta);
                        } else {
                            etaSec = Math.round(avgEta);
                        }
                        
                        // SIEMPRE actualizar el contador si hay un cambio significativo (más del 5% ahora)
                        if (window.currentRemainingSeconds === null || 
                            Math.abs(etaSec - window.currentRemainingSeconds) > Math.max(5, window.currentRemainingSeconds * 0.05) ||
                            etaSec < window.currentRemainingSeconds) {
                            window.currentRemainingSeconds = etaSec;
                        }
                        
                        // Iniciar/actualizar contador progresivo
                        if (!window.timeCountdownInterval && etaSec > 0) {
                            window.currentRemainingSeconds = etaSec;
                            window.backupStartTime = startTime;
                            window.timeCountdownInterval = setInterval(function() {
                                if (window.currentRemainingSeconds !== null && window.currentRemainingSeconds > 0) {
                                    window.currentRemainingSeconds--;
                                    // Actualizar tiempo en la primera fila
                                    const remainingEl = document.getElementById('backupRemainingTime');
                                    const progressTime = document.getElementById('progressTime');
                                    if (remainingEl || progressTime) {
                                        const remainingStr = formatTime(window.currentRemainingSeconds);
                                        if (remainingEl) remainingEl.textContent = 'Restante: ~' + remainingStr;
                                        if (progressTime) {
                                            const currentElapsed = Math.floor((Date.now() - (window.backupStartTime || Date.now())) / 1000);
                                            const elapsedStr = formatTime(currentElapsed);
                                            progressTime.textContent = '⏱️ ' + elapsedStr + ' | Restante: ~' + remainingStr;
                                        }
                                    }
                                } else {
                                    // Si llega a 0, recalcular desde el servidor
                                    if (window.currentRemainingSeconds !== null && window.currentRemainingSeconds <= 0) {
                                        window.currentRemainingSeconds = null; // Forzar recálculo
                                    }
                                }
                            }, 1000);
                        } else if (window.timeCountdownInterval && Math.abs(etaSec - window.currentRemainingSeconds) > Math.max(5, window.currentRemainingSeconds * 0.05)) {
                            // Actualizar el contador si hay diferencia significativa
                            window.currentRemainingSeconds = etaSec;
                        }
                    } else {
                        // Fallback: calcular localmente con velocidad real
                        const speed = elapsedSec > 0 ? Math.round(processed / elapsedSec) : 0;
                        const remainingFiles = totalFiles - processed;
                        const localEta = speed > 0 ? Math.round(remainingFiles / speed) : 0;
                        
                        if (localEta > 0) {
                            // Agregar a historial
                            window.etaHistory.push(localEta);
                            if (window.etaHistory.length > MAX_ETA_HISTORY) {
                                window.etaHistory.shift();
                            }
                            
                            // Calcular promedio móvil
                            const avgEta = window.etaHistory.reduce((a, b) => a + b, 0) / window.etaHistory.length;
                            etaSec = Math.round(avgEta);
                            
                            // Actualizar contador
                            if (window.currentRemainingSeconds === null || 
                                Math.abs(etaSec - window.currentRemainingSeconds) > Math.max(5, window.currentRemainingSeconds * 0.05) ||
                                etaSec < window.currentRemainingSeconds) {
                                window.currentRemainingSeconds = etaSec;
                            }
                            
                            // Iniciar contador progresivo de tiempo restante si no existe
                            if (!window.timeCountdownInterval && etaSec > 0) {
                                window.currentRemainingSeconds = etaSec;
                                window.timeCountdownInterval = setInterval(function() {
                                    if (window.currentRemainingSeconds !== null && window.currentRemainingSeconds > 0) {
                                        window.currentRemainingSeconds--;
                                        // Actualizar solo el tiempo restante (el transcurrido se actualiza por el contador continuo)
                                        // Actualizar tiempo en la primera fila
                                        const remainingEl = document.getElementById('backupRemainingTime');
                                        const progressTimeElements = document.querySelectorAll('#progressTime');
                                        if (remainingEl || progressTimeElements.length > 0) {
                                            const remainingStr = formatTime(window.currentRemainingSeconds);
                                            if (remainingEl) remainingEl.textContent = 'Restante: ~' + remainingStr;
                                            
                                            // Actualizar tiempo transcurrido desde el contador continuo y agregar restante
                                            if (window.backupStartTime) {
                                                const currentElapsed = Math.floor((Date.now() - window.backupStartTime) / 1000);
                                                const elapsedStr = formatTime(currentElapsed);
                                                progressTimeElements.forEach(function(el) {
                                                    if (el) {
                                                        el.textContent = '⏱️ ' + elapsedStr + ' | Restante: ~' + remainingStr;
                                                    }
                                                });
                                            }
                                        }
                                    } else {
                                        // Forzar recálculo si llega a 0
                                        if (window.currentRemainingSeconds !== null && window.currentRemainingSeconds <= 0) {
                                            window.currentRemainingSeconds = null;
                                        }
                                    }
                                }, 1000);
                            }
                        } else {
                            // Detener contador si no hay ETA válido
                            if (window.timeCountdownInterval) {
                                clearInterval(window.timeCountdownInterval);
                                window.timeCountdownInterval = null;
                            }
                        }
                    }
                    
                    // Asegurar que elapsedSec sea un número válido
                    elapsedSec = Math.max(0, Math.floor(elapsedSec));
                    const elapsedStr = formatTime(elapsedSec);
                    
                    // Asegurar que etaSec sea válido y formatearlo
                    let etaStr = 'calculando...';
                    if (etaSec !== null && etaSec !== undefined && etaSec > 0) {
                        etaStr = formatTime(Math.floor(etaSec));
                    } else if (processData.eta_str && processData.eta_str !== 'calculando...') {
                        // Fallback al string del servidor
                        etaStr = processData.eta_str;
                    } else if (processData.time && processData.time.estimated_remaining_str) {
                        // Fallback al string formateado del servidor
                        etaStr = processData.time.estimated_remaining_str;
                    }
                    
                    // Calcular velocidad para mostrar
                    const currentSpeed = elapsedSec > 0 ? Math.round(processed / elapsedSec) : 0;
                    
                    // Actualizar barra de progreso con información clara
                    updateBar(percent, processed.toLocaleString() + '/' + totalFiles.toLocaleString() + ' archivos | ZIP: ' + zipSize.toFixed(1) + ' MB | ' + currentSpeed + ' arch/s', elapsedStr, etaStr);
                    
                    // Actualizar recursos del servidor en tiempo real
                    if (processData.server_resources) {
                        updateServerResources(processData.server_resources);
                    }
                    
                    // Log cada 10 chunks o cada 5%
                    if (chunkNum % 10 === 0 || percent % 5 === 0) {
                        const speed = elapsedSec > 0 ? Math.round(processed / elapsedSec) : 0;
                        addLog('📊 ' + percent + '% | ' + processed.toLocaleString() + '/' + totalFiles.toLocaleString() + ' archivos | ZIP: ' + zipSize.toFixed(1) + ' MB | Velocidad: ' + speed + ' arch/s');
                        console.log(`📊 [CHUNKS] Progreso: ${percent}% | ${processed}/${totalFiles} archivos | ZIP: ${zipSize.toFixed(1)} MB | Chunks creados: ${totalChunksCreated}`);
                    }
                    
                    // Delay mínimo para máxima velocidad (casi eliminado)
                    let delay = 10; // Base 10ms (mínimo absoluto)
                    if (zipSize > 5000) delay = 25; // ZIP extremadamente grande: delay mínimo
                    else if (zipSize > 2000) delay = 15; // ZIP grande: delay mínimo
                    // Eliminado delay por memoria alta para máxima velocidad
                    
                        await new Promise(r => setTimeout(r, delay));
                    
                } catch (error) {
                    retryCount++;
                    consecutiveSuccess = 0;
                    consecutiveErrors++;
                    
                    // Detectar específicamente error 503 (Service Unavailable)
                    const is503Error = error.message.includes('503') || error.message.includes('Service Unavailable') ||
                                      (error.response && error.response.status === 503);

                    // Detectar específicamente timeout (AbortError del AbortController)
                    const isTimeoutError = error.name === 'AbortError' || error.message.includes('aborted') ||
                                          error.message.includes('timeout') || error.message.includes('Timeout');
                    
                    // Reducir chunk_size más agresivamente si es 503
                    const oldSize = chunkSize;
                    if (is503Error) {
                        // Error 503: reducir drásticamente (a 1/3 del tamaño actual)
                        chunkSize = Math.max(MIN_CHUNK, Math.round(chunkSize * 0.33));
                        addLog('⚠️ ERROR 503 (Timeout del servidor) en chunk #' + chunkNum);
                        addLog('📉 Reducción agresiva de chunk: ' + oldSize + ' → ' + chunkSize + ' (para evitar timeouts)');
                    } else if (isTimeoutError) {
                        // Timeout: reducir moderadamente (a 2/3 del tamaño actual) - MENOS agresivo que 503
                        chunkSize = Math.max(MIN_CHUNK, Math.round(chunkSize * 0.67));
                        addLog('⏰ TIMEOUT (45s) en chunk #' + chunkNum + ' - reduciendo chunk size');
                        addLog('📉 Reducción moderada de chunk: ' + oldSize + ' → ' + chunkSize + ' (timeout del servidor)');
                    } else {
                        // Otro error: reducir a la mitad
                                        chunkSize = Math.max(MIN_CHUNK, Math.round(chunkSize * 0.5));
                        addLog('⚠️ Error chunk #' + chunkNum + ' (intento ' + retryCount + '/' + MAX_RETRIES + '): ' + error.message);
                        addLog('📉 Reduciendo chunk: ' + oldSize + ' → ' + chunkSize);
                    }
                    
                    if (retryCount < MAX_RETRIES) {
                        // Esperar más tiempo si es 503 para dar tiempo al servidor
                        const waitTime = is503Error ? 5000 : 2000;
                        addLog('🔄 Reintentando en ' + (waitTime/1000) + 's...');
                        await new Promise(r => setTimeout(r, waitTime));
                    } else {
                        throw new Error('Chunk #' + chunkNum + ' falló después de ' + MAX_RETRIES + ' intentos. Último error: ' + error.message);
                    }
                }
            }
        }
        
        // PASO 3: Finalizar
        if (chunkLogInterval) clearInterval(chunkLogInterval);
        
        // Detener contador progresivo si está activo
        if (window.timeCountdownInterval) {
            clearInterval(window.timeCountdownInterval);
            window.timeCountdownInterval = null;
        }
        
        // Detener contador continuo de tiempo transcurrido
        if (window.elapsedTimeInterval) {
            clearInterval(window.elapsedTimeInterval);
            window.elapsedTimeInterval = null;
        }
        
        const finalElapsed = Math.round((Date.now() - startTime) / 1000);
        const finalElapsedStr = finalElapsed < 60 ? finalElapsed + 's' : Math.floor(finalElapsed/60) + 'm ' + Math.round(finalElapsed%60) + 's';

        // El sistema combina todos los chunks en un solo ZIP final
        addLog('🏁 Finalizando backup (combinando chunks en ZIP final)...');
        updateBar(98, '🏁 Finalizando y combinando chunks...', finalElapsedStr, 'calculando...');
        
        // Finalización por chunks (múltiples requests)
        let finalizing = true;
        let finalizedChunks = 0;
        let totalChunks = 0;
        let finalData = null;
        let finalizeRequestCount = 0;
        
        console.log('🔍 [FINALIZACIÓN] Iniciando proceso de finalización...');
        console.log('🔍 [FINALIZACIÓN] Backup ID:', backupId);
        
        while (finalizing) {
            finalizeRequestCount++;
            console.log(`🔍 [FINALIZACIÓN] Request #${finalizeRequestCount} - Solicitando finalización...`);
            
            const finalRes = await fetch(chunkUrl + '?action=finalize&backup_id=' + backupId, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log(`🔍 [FINALIZACIÓN] Request #${finalizeRequestCount} - Respuesta recibida, status:`, finalRes.status);
            
            finalData = await finalRes.json();
            
            console.log(`🔍 [FINALIZACIÓN] Request #${finalizeRequestCount} - Datos recibidos:`, {
                success: finalData.success,
                action: finalData.action,
                finalized_chunks: finalData.finalized_chunks,
                total_chunks: finalData.total_chunks,
                remaining_chunks: finalData.remaining_chunks,
                progress_percent: finalData.progress_percent,
                zip_size_mb: finalData.zip_size_mb,
                current_size_mb: finalData.current_size_mb,
                message: finalData.message,
                error: finalData.error,
                warning: finalData.warning
            });
            
            if (!finalData.success) {
                console.error('❌ [FINALIZACIÓN] Error en request #' + finalizeRequestCount + ':', finalData.error || 'Error desconocido');
                console.error('❌ [FINALIZACIÓN] Datos completos del error:', finalData);
                throw new Error(finalData.error || 'Error finalizando');
            }
            
            // Verificar si se completó
            if (finalData.action === 'finalized') {
                console.log('✅ [FINALIZACIÓN] Backup finalizado completamente');
                console.log('✅ [FINALIZACIÓN] Chunks finalizados:', finalData.finalized_chunks);
                console.log('✅ [FINALIZACIÓN] Total chunks:', finalData.total_chunks);
                console.log('✅ [FINALIZACIÓN] Tamaño ZIP:', finalData.zip_size_mb, 'MB');
                console.log('✅ [FINALIZACIÓN] Total requests realizados:', finalizeRequestCount);
                finalizing = false;
            } else if (finalData.action === 'finalizing') {
                // Continuar procesando
                finalizedChunks = finalData.finalized_chunks || 0;
                totalChunks = finalData.total_chunks || 0;
                const progress = finalData.progress_percent || 0;
                const remaining = finalData.remaining_chunks || 0;
                
                console.log(`📦 [FINALIZACIÓN] Procesando... Chunks: ${finalizedChunks}/${totalChunks} (${progress.toFixed(1)}%)`);
                console.log(`📦 [FINALIZACIÓN] Chunks restantes: ${remaining}`);
                console.log(`📦 [FINALIZACIÓN] Tamaño actual ZIP: ${finalData.current_size_mb || 'N/A'} MB`);
                
                if (finalData.warning) {
                    console.warn('⚠️ [FINALIZACIÓN] Advertencia:', finalData.warning);
                }
                
                addLog(`📦 Procesando chunks: ${finalizedChunks}/${totalChunks} (${progress.toFixed(1)}%)`);
                updateBar(98 + (progress * 0.02), `🏁 Finalizando: ${finalizedChunks}/${totalChunks} chunks...`, finalElapsedStr, 'calculando...');
                
                // Esperar un poco antes del siguiente request
                await new Promise(resolve => setTimeout(resolve, 500));
            } else {
                // Si no viene action, verificar otros indicadores
                console.log('⚠️ [FINALIZACIÓN] No se recibió action="finalized" ni "finalizing"');
                console.log('⚠️ [FINALIZACIÓN] Action recibida:', finalData.action);
                console.log('⚠️ [FINALIZACIÓN] Asumiendo que está completo...');
                
                if (finalData.zip_file) {
                    console.log('✅ [FINALIZACIÓN] ZIP final:', finalData.zip_file);
                    console.log('✅ [FINALIZACIÓN] Tamaño:', finalData.zip_size_mb, 'MB');
                }
                
                // Si no viene action, asumir que está completo
                finalizing = false;
            }
        }
        
        console.log('🏁 [FINALIZACIÓN] Proceso de finalización terminado');
        console.log('🏁 [FINALIZACIÓN] Datos finales:', finalData);
        
        // Verificar que el ZIP tiene el tamaño esperado
        if (finalData.zip_size_mb) {
            console.log('🔍 [FINALIZACIÓN] Verificación final del ZIP:');
            console.log('   → Tamaño del ZIP:', finalData.zip_size_mb, 'MB');
            console.log('   → Archivo:', finalData.zip_file);
            if (finalData.total_chunks) {
                console.log('   → Total chunks esperados:', finalData.total_chunks);
                console.log('   → Chunks finalizados:', finalData.finalized_chunks);
            }
        }
        
        // ÉXITO - Backup completado exitosamente
        const bar = document.getElementById('progressBar');
        bar.style.width = '100%';
        bar.textContent = '100%';
        bar.style.background = 'linear-gradient(90deg, #28a745, #20c997)';

        const elapsed = Math.floor((Date.now() - startTime) / 1000);

        addLog('═══════════════════════════════════════════════════════════');
        addLog('✅ BACKUP COMPLETADO EXITOSAMENTE');
        addLog('   Tiempo: ' + elapsed + ' segundos');

        // El sistema combina todos los chunks en un solo ZIP final
        if (finalData.chunks && Array.isArray(finalData.chunks)) {
            // SISTEMA DE CHUNKS INDIVIDUALES
            console.log('✅ [FINALIZACIÓN] Backup completado - chunks individuales listos para descarga');
            console.log('✅ [FINALIZACIÓN] Chunks disponibles:', finalData.chunks.length, 'Total:', finalData.total_size_mb, 'MB');

            const progressTextEl = document.getElementById('progressText');
            if (progressTextEl) {
                progressTextEl.textContent = '✅ COMPLETADO: ' + finalData.total_chunks + ' chunks listos para descarga (' + finalData.total_size_mb + ' MB total)';
            }

            addLog('   Sistema: Chunks individuales para descarga por partes');
            addLog('   Chunks disponibles: ' + finalData.total_chunks);
            addLog('   Tamaño total: ' + finalData.total_size_mb + ' MB');
            finalData.chunks.forEach(chunk => {
                addLog('   - Chunk #' + chunk.number + ': ' + chunk.file + ' (' + chunk.size_mb + ' MB, ' + chunk.files + ' archivos)');
            });

            // Sección de descarga eliminada según solicitud del usuario

        } else if (finalData.zip_file) {
            // SISTEMA ANTIGUO: ZIP único (compatibilidad)
            console.log('✅ [FINALIZACIÓN] Sistema antiguo detectado (ZIP único)');
            console.log('✅ [FINALIZACIÓN] ZIP final:', finalData.zip_file, '(', finalData.zip_size_mb, 'MB)');

            const progressTextEl = document.getElementById('progressText');
            if (progressTextEl) {
                progressTextEl.textContent = '✅ COMPLETADO: ' + finalData.zip_file + ' (' + finalData.zip_size_mb + ' MB)';
            }

            addLog('   Sistema: Backup único');
            addLog('   Archivo: ' + finalData.zip_file);
            addLog('   Tamaño: ' + finalData.zip_size_mb + ' MB');
        } else {
            // Sistema desconocido
            console.log('⚠️ [FINALIZACIÓN] Sistema desconocido - no se detectaron archivos ZIP');
            const progressTextEl = document.getElementById('progressText');
            if (progressTextEl) {
                progressTextEl.textContent = '✅ COMPLETADO (sin archivos detectados)';
            }
            addLog('   Sistema: Desconocido');
        }

        addLog('═══════════════════════════════════════════════════════════');
        addLog('');
        addLog('📋 Puedes copiar el log con el botón "Copiar Log"');

        // PREGUNTAR SOBRE MANTENER/ELIMINAR CHUNKS
        if (finalData.method === 'separate_chunks' || finalData.method === 'separate_chunks_2byte') {
            addLog('🗂️ Chunks creados exitosamente');
            addLog('❓ Decide qué hacer con los chunks temporales...');

            // Mostrar diálogo preguntando sobre mantener/eliminar chunks
            setTimeout(() => {
                showChunkCleanupDialog(finalData.backup_id, finalData.total_chunks);
            }, 1000);

            // NO recargar automáticamente si hay chunks para decidir
            return;
        }

        addLog('⏱️ La página se recargará en 5 segundos...');

        manualBackupInProgress = false;

        // Detener contador continuo de tiempo transcurrido
        if (window.elapsedTimeInterval) {
            clearInterval(window.elapsedTimeInterval);
            window.elapsedTimeInterval = null;
        }

        setTimeout(() => location.reload(), 5000);
        
    } catch (error) {
        if (chunkLogInterval) clearInterval(chunkLogInterval);
        
        console.error('Error en chunked backup:', error);
        document.getElementById('progressBar').style.background = '#dc3545';
        const progressTextEl = document.getElementById('progressText');
        if (progressTextEl) {
            progressTextEl.textContent = '❌ Error: ' + error.message;
        }
        addLog('❌ ERROR: ' + error.message);
        manualBackupInProgress = false;
        restoreAllBackupButtons();
    }
}

function startImmediateProgress() {
    let progress = 0;
    immediateProgressInterval = setInterval(() => {
        progress += Math.random() * 3; // Incremento aleatorio entre 0-3%
        if (progress > 15) progress = 15; // Máximo 15% hasta que llegue el progreso real
        
        document.getElementById('progressBar').style.width = progress + '%';
        document.getElementById('progressBar').textContent = Math.round(progress) + '%';
        
        // Actualizar tiempo
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        document.getElementById('progressTime').textContent = 'Tiempo: ' + elapsed + 's';
        
        // Agregar mensajes de progreso
        const progressTextEl = document.getElementById('progressText');
        if (progressTextEl) {
            if (progress < 5) {
                progressTextEl.textContent = 'Iniciando backup...';
            } else if (progress < 10) {
                progressTextEl.textContent = 'Preparando proceso...';
            } else {
                progressTextEl.textContent = 'Conectando a base de datos...';
            }
        }
    }, 500); // Actualizar cada 500ms
}

function displayStats(stats) {
    let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
    
    // Estadísticas generales
    html += '<div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">';
    html += '<h5 style="margin: 0 0 10px 0; color: #495057;">📊 Estadísticas Generales</h5>';
    html += '<p style="margin: 5px 0;"><strong>Total de Tablas:</strong> ' + stats.total_tables + '</p>';
    html += '<p style="margin: 5px 0;"><strong>Total de Registros:</strong> ' + stats.total_records.toLocaleString() + '</p>';
    html += '<p style="margin: 5px 0;"><strong>Tamaño Base de Datos:</strong> ' + formatFileSize(stats.estimated_size_mb) + '</p>';
    html += '<p style="margin: 5px 0;"><strong>Tamaño ZIP Estimado:</strong> ' + formatFileSize(stats.estimated_zip_mb) + '</p>';
    html += '</div>';
    
    // Distribución de tablas
    html += '<div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">';
    html += '<h5 style="margin: 0 0 10px 0; color: #495057;">📈 Distribución de Tablas</h5>';
    html += '<p style="margin: 5px 0;"><strong>Todas las Tablas:</strong> ' + stats.total_tables + ' (se exportarán TODOS los datos)</p>';
    html += '<p style="margin: 5px 0;"><strong>Sin Límites:</strong> Todas las tablas exportarán datos completos</p>';
    html += '</div>';
    html += '</div>';
    
    // Top 10 tablas más grandes
    html += '<div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">';
    html += '<h5 style="margin: 0 0 10px 0; color: #495057;">🔝 Top 10 Tablas Más Grandes</h5>';
    html += '<div style="max-height: 200px; overflow-y: auto;">';
    html += '<table style="width: 100%; font-size: 12px;">';
    html += '<tr style="background: #f8f9fa;"><th style="padding: 5px; text-align: left;">Tabla</th><th style="padding: 5px; text-align: right;">Registros</th><th style="padding: 5px; text-align: right;">Tamaño</th><th style="padding: 5px; text-align: center;">Exportar</th></tr>';
    
    for (let i = 0; i < Math.min(10, stats.tables_detail.length); i++) {
        const table = stats.tables_detail[i];
        const willExport = '✅ TODOS los datos';
        const rowColor = i % 2 === 0 ? '#f8f9fa' : 'white';
        
        html += '<tr style="background: ' + rowColor + ';">';
        html += '<td style="padding: 5px;">' + table.name + '</td>';
        html += '<td style="padding: 5px; text-align: right;">' + table.records.toLocaleString() + '</td>';
        html += '<td style="padding: 5px; text-align: right;">' + formatFileSize(table.size_mb) + '</td>';
        html += '<td style="padding: 5px; text-align: center;">' + willExport + '</td>';
        html += '</tr>';
    }
    
    html += '</table>';
    html += '</div>';
    html += '</div>';
    
    document.getElementById('statsContent').innerHTML = html;
}


function deleteBackup(filename, deleteUrl) {
    // Confirmación con SweetAlert2
    Swal.fire({
        title: '¿Eliminar backup?',
        html: '<div style="text-align: left; margin: 20px 0;">' +
              '<p><strong>Archivo:</strong> ' + filename + '</p>' +
              '<p style="color: #dc3545; font-weight: bold;">⚠️ ADVERTENCIA: Esta acción NO se puede revertir</p>' +
              '<p>El backup será eliminado permanentemente del servidor.</p>' +
              '</div>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed) {
            // Mostrar loading
            Swal.fire({
                title: 'Eliminando...',
                text: 'Eliminando backup del servidor',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
            
            // Enviar petición de eliminación
            fetch(deleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'filename=' + encodeURIComponent(filename) + '&token=<?php echo $token; ?>'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Backup eliminado',
                        text: 'El backup ha sido eliminado correctamente'
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudo eliminar el backup'
                    });
                }
            })
            .catch(function(error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor'
                });
            });
        }
    });
}

// Inicializar cuando se carga la página
document.addEventListener("DOMContentLoaded", function() {
    console.log("DOM cargado, inicializando pestañas...");
    
    // Detectar pestaña activa desde la URL
    var urlParams = new URLSearchParams(window.location.search);
    var activeTab = urlParams.get('tab');
    
    if (activeTab && ['config', 'backup', 'logs', 'maintenance', 'simulator', 'about'].includes(activeTab)) {
        console.log("Pestaña activa desde URL:", activeTab);
        switchSetupTab(activeTab);
    } else {
        console.log("Usando pestaña por defecto: config");
        switchSetupTab('config');
    }
    
    console.log("Función switchSetupTab disponible");

});

// Sección de descarga eliminada según solicitud del usuario


// Funciones para filtros de logs
function applyLogFilters() {
    var action = document.getElementById('filterAction').value;
    var user = document.getElementById('filterUser').value;
    var dateFrom = document.getElementById('filterDateFrom').value;
    var dateTo = document.getElementById('filterDateTo').value;
    
    var url = new URL(window.location);
    url.searchParams.set('tab', 'logs');
    
    if (action) url.searchParams.set('action', action);
    else url.searchParams.delete('action');
    
    if (user) url.searchParams.set('user', user);
    else url.searchParams.delete('user');
    
    if (dateFrom) url.searchParams.set('date_from', dateFrom);
    else url.searchParams.delete('date_from');
    
    if (dateTo) url.searchParams.set('date_to', dateTo);
    else url.searchParams.delete('date_to');
    
    window.location.href = url.toString();
}

function clearLogFilters() {
    var url = new URL(window.location);
    url.searchParams.set('tab', 'logs');
    url.searchParams.delete('action');
    url.searchParams.delete('user');
    url.searchParams.delete('date_from');
    url.searchParams.delete('date_to');
    
    window.location.href = url.toString();
}

function refreshLogs() {
    var url = new URL(window.location);
    url.searchParams.set('tab', 'logs');
    url.searchParams.set('_refresh', Date.now());
    window.location.href = url.toString();
}

// Detectar cuando se guarda la configuración y notificar a index.php
window.addEventListener('DOMContentLoaded', function() {
    // Si hay un mensaje de éxito reciente, notificar a index.php para que se recargue
    <?php if (isset($_SESSION['filemanager_reload_index']) && $_SESSION['filemanager_reload_index']): ?>
        // Guardar en localStorage para que index.php lo detecte
        localStorage.setItem('filemanager_reload_required', 'true');
        localStorage.setItem('filemanager_reload_timestamp', Date.now().toString());
        
        // Limpiar la sesión
        <?php unset($_SESSION['filemanager_reload_index']); ?>
        
        // También mostrar mensaje al usuario
        setTimeout(function() {
            if (confirm('La configuración se guardó correctamente. ¿Deseas abrir el FileManager para ver los cambios?')) {
                window.open('../index.php', '_blank');
            }
        }, 500);
    <?php endif; ?>
    
});

// Crear modal de configuración
function createAutoBackupModal() {
    autoBackupModal = document.createElement('div');
    autoBackupModal.id = 'autoBackupModal';
    autoBackupModal.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;';
    
    var modalContent = document.createElement('div');
    modalContent.style.cssText = 'background: white; border-radius: 12px; padding: 30px; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);';
    
    modalContent.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #dee2e6;">
            <h3 style="margin: 0; color: #dc3545; font-size: 24px;"><i class="fas fa-clock"></i> Configuración de Backup Automático</h3>
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 8px 15px; border-radius: 6px; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
                    <div style="color: white; font-size: 10px; margin-bottom: 2px; font-weight: 600; opacity: 0.9;">
                        <i class="fas fa-clock"></i> SERVIDOR
                    </div>
                    <div id="serverTime" style="font-family: 'Courier New', monospace; font-size: 18px; font-weight: bold; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.3); letter-spacing: 1px;">
                        00:00:00
                    </div>
                    <div style="color: rgba(255,255,255,0.8); font-size: 9px; margin-top: 2px;" id="serverDate"></div>
                </div>
                <button onclick="closeAutoBackupModal()" style="background: #dc3545; color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 18px; transition: all 0.3s;" onmouseover="this.style.background='#c82333'; this.style.transform='rotate(90deg)';" onmouseout="this.style.background='#dc3545'; this.style.transform='rotate(0deg)'">&times;</button>
            </div>
        </div>
        
        <!-- Estado actual -->
        <div id="autoBackupCurrentStatus" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #dc3545;">
            <h4 style="margin: 0 0 15px 0; color: #495057;"><i class="fas fa-info-circle"></i> Estado Actual</h4>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <div>
                    <strong>Estado:</strong> <span id="statusEnabled" style="color: #dc3545;">Inactivo</span>
                </div>
                <div>
                    <strong>Frecuencia:</strong> <span id="statusFrequency">-</span>
                </div>
                <div>
                    <strong>Última Copia:</strong> <span id="statusLastBackup">-</span>
                </div>
                <div style="grid-column: 1 / -1;">
                    <strong>Próxima Copia:</strong> <span id="statusNextBackup">-</span>
                    <div id="statusNextBackupTimer" style="margin-top: 12px; padding: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #dee2e6; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                        <span id="statusTimerCountdown" style="display: block; font-family: 'Courier New', monospace; font-size: 24px; font-weight: bold; color: #17a2b8; margin-bottom: 6px; letter-spacing: 2px;">--:--:--</span>
                        <div style="font-size: 12px; color: #6c757d; font-weight: 500;" id="statusTimerLabel">Tiempo restante</div>
                    </div>
                </div>
            </div>
            
            <!-- Barra de progreso y log para backup automático -->
            <div id="autoBackupProgressSection" style="display: none; margin-top: 20px; background: white; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px;">
                <h4 style="margin: 0 0 15px 0; color: #495057;"><i class="fas fa-tasks"></i> Progreso del Backup</h4>
                
                <!-- Barra de progreso -->
                <div style="margin-bottom: 15px;">
                    <div id="autoBackupProgressText" style="font-size: 14px; color: #6c757d; margin-bottom: 8px; font-weight: 500;">Progreso: 0%</div>
                    <div style="background: #e9ecef; border-radius: 10px; height: 30px; overflow: hidden; position: relative; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
                        <div id="autoBackupProgressBar" style="height: 100%; background: linear-gradient(90deg, #007bff, #0056b3, #007bff); background-size: 200% 100%; border-radius: 10px; width: 0%; animation: progressAnimation 1.5s ease-in-out infinite; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">0%</div>
                    </div>
                </div>
                
                <!-- Log del backup -->
                <div style="margin-top: 20px;">
                    <div style="font-size: 14px; color: #6c757d; margin-bottom: 8px; font-weight: 500;"><i class="fas fa-file-alt"></i> Log del Backup</div>
                    <div id="autoBackupLog" style="background: #212529; color: #28a745; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 12px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; line-height: 1.5;">Esperando inicio del backup...</div>
                </div>
            </div>
        </div>
        
        <!-- Configuración -->
        <form id="autoBackupForm" onsubmit="saveAutoBackupConfig(event)">
            <div style="background: white; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #495057;"><i class="fas fa-cog"></i> Configuración</h4>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; cursor: pointer; font-size: 16px; font-weight: 600; color: #495057;">
                        <input type="checkbox" id="autoBackupEnabled" style="width: 20px; height: 20px; margin-right: 10px; cursor: pointer;">
                        Activar Backup Automático
                    </label>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Frecuencia:</label>
                    <select id="autoBackupFrequency" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px;">
                        <option value="daily">Diaria</option>
                        <option value="weekly">Semanal</option>
                        <option value="monthly">Mensual</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Tipo de Backup:</label>
                    <select id="autoBackupType" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px;">
                        <option value="database_only">Solo Base de Datos</option>
                        <option value="files_only">Solo Archivos</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Hora de Ejecución (Reloj del Servidor):</label>
                    <input type="time" id="autoBackupTime" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; cursor: pointer; font-size: 16px; font-weight: 600; color: #495057;">
                        <input type="checkbox" id="autoBackupCronEnabled" style="width: 20px; height: 20px; margin-right: 10px; cursor: pointer;">
                        Activar Cron Job
                    </label>
                    <small style="color: #6c757d; display: block; margin-top: 5px; margin-left: 30px;">Permite ejecutar backups automáticamente sin estar en el FileManager</small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Máximo de Backups a Conservar:</label>
                    <input type="number" id="autoBackupMaxBackups" min="1" max="50" value="5" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px;">
                </div>
            </div>
            
            <!-- Botones de acción - Todos en una sola fila -->
            <div style="margin-top: 30px; padding-top: 25px; border-top: 2px solid #dee2e6;">
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                    <!-- Botones de herramientas y cronjob -->
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                        <button type="button" id="btnVerifyEnv" onclick="verifyAutoBackupEnvironment()" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 8px rgba(23,162,184,0.3); white-space: nowrap;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(23,162,184,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(23,162,184,0.3)';">
                            <i class="fas fa-search" style="margin-right: 6px;"></i> Verificar Entorno
                        </button>
                        <button type="button" id="btnFixEnv" onclick="fixAutoBackupEnvironment(event)" style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); color: #212529; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 8px rgba(255,193,7,0.3); white-space: nowrap;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(255,193,7,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(255,193,7,0.3)';">
                            <i class="fas fa-wrench" style="margin-right: 6px;"></i> Corregir Entorno
                        </button>
                        <button type="button" id="btnInstallCron" onclick="manageCronjob('install', event)" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%); color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 8px rgba(40,167,69,0.3); white-space: nowrap;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(40,167,69,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(40,167,69,0.3)';">
                            <i class="fas fa-play-circle" style="margin-right: 6px;"></i> Instalar Cronjob
                        </button>
                        <button type="button" id="btnUninstallCron" onclick="manageCronjob('uninstall', event)" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 8px rgba(220,53,69,0.3); white-space: nowrap;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(220,53,69,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(220,53,69,0.3)';">
                            <i class="fas fa-stop-circle" style="margin-right: 6px;"></i> Desinstalar Cronjob
                        </button>
                    </div>
                    
                    <!-- Botones principales (derecha) -->
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" id="btnCancel" onclick="closeAutoBackupModal()" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 6px rgba(108,117,125,0.2); white-space: nowrap;" onmouseover="this.style.background='#5a6268'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 10px rgba(108,117,125,0.3)';" onmouseout="this.style.background='#6c757d'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 6px rgba(108,117,125,0.2)';">
                            <i class="fas fa-times" style="margin-right: 6px;"></i> Cancelar
                        </button>
                        <button type="submit" id="btnSaveConfig" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 4px 12px rgba(40,167,69,0.35); white-space: nowrap;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(40,167,69,0.45)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(40,167,69,0.35)';">
                            <i class="fas fa-save" style="margin-right: 6px;"></i> Guardar Configuración
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Área de resultados de los botones -->
            <div id="actionResults" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; display: none;">
                <h5 style="margin: 0 0 10px 0; color: #495057; font-size: 14px; font-weight: 600;">
                    <i class="fas fa-info-circle"></i> <span id="actionResultsTitle">Resultado de la Acción</span>
                </h5>
                <div id="actionResultsContent" style="max-height: 300px; overflow-y: auto;">
                    <!-- El contenido se llenará dinámicamente -->
                </div>
            </div>
        </form>
    `;
    
    if (autoBackupModal && modalContent) {
    autoBackupModal.appendChild(modalContent);
    }
    if (document.body && autoBackupModal) {
    document.body.appendChild(autoBackupModal);
    }
    
    // Actualizar reloj del servidor cada segundo
    updateServerTime();
    var serverTimeInterval = setInterval(updateServerTime, 1000);
    
    // Guardar intervalo para limpiarlo cuando se cierre el modal
    if (autoBackupModal) {
        autoBackupModal._serverTimeInterval = serverTimeInterval;
    }
    
    // Cargar configuración actual
    loadAutoBackupConfig();
}

// Actualizar reloj del servidor (sincronizado con servidor)
function updateServerTime() {
    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/get_server_time.php", 1); ?>?t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                var timeEl = document.getElementById('serverTime');
                var dateEl = document.getElementById('serverDate');
                if (timeEl) {
                    timeEl.textContent = data.time;
                }
                if (dateEl && data.date) {
                    var date = new Date(data.date);
                    var dateStr = date.toLocaleDateString('es-ES', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
                    dateEl.textContent = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);
                }
            }
        })
        .catch(() => {
            // Fallback a hora local
            var now = new Date();
            var timeStr = now.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
            var dateStr = now.toLocaleDateString('es-ES', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
            var timeEl = document.getElementById('serverTime');
            var dateEl = document.getElementById('serverDate');
            if (timeEl) {
                timeEl.textContent = timeStr;
            }
            if (dateEl) {
                dateEl.textContent = dateStr.charAt(0).toUpperCase() + dateStr.slice(1) + ' (hora local)';
            }
        });
}

// Cerrar modal
function closeAutoBackupModal() {
    if (autoBackupModal) {
        // Limpiar intervalo del reloj
        if (autoBackupModal._serverTimeInterval) {
            clearInterval(autoBackupModal._serverTimeInterval);
        }
        autoBackupModal.style.display = 'none';
    }
}

// Cargar configuración actual
function loadAutoBackupConfig() {
    if (autoBackupConfig) {
        document.getElementById('autoBackupEnabled').checked = autoBackupConfig.enabled == 1;
        document.getElementById('autoBackupFrequency').value = autoBackupConfig.frequency || 'daily';
        document.getElementById('autoBackupType').value = autoBackupConfig.backup_type || 'files_only';
        document.getElementById('autoBackupTime').value = autoBackupConfig.schedule_time || '03:00:00';
        document.getElementById('autoBackupCronEnabled').checked = autoBackupConfig.cron_enabled == 1;
        document.getElementById('autoBackupMaxBackups').value = autoBackupConfig.max_backups || 5;
    }
}

// Cargar estado actual
// Cargar estado del backup automático
var isLoadingStatus = false;
function loadAutoBackupStatus() {
    if (isLoadingStatus) return Promise.resolve(); // Evitar múltiples peticiones simultáneas
    isLoadingStatus = true;
    
    return fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/get_auto_backup_status.php", 1); ?>?t=' + Date.now())
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (data.success) {
                // Actualizar configuración global
                autoBackupConfig = data.config;
                
                // Actualizar modal
                if (document.getElementById('statusEnabled')) {
                    document.getElementById('statusEnabled').textContent = data.config.enabled ? 'Activo' : 'Inactivo';
                    document.getElementById('statusEnabled').style.color = data.config.enabled ? '#28a745' : '#dc3545';
                    document.getElementById('statusFrequency').textContent = data.config.frequency == 'daily' ? 'Diaria' : (data.config.frequency == 'weekly' ? 'Semanal' : 'Mensual');
                    // Actualizar modal usando funciones unificadas
                    updateLastBackupDisplay(data.config.last_backup);
                    updateRunningIndicator(data.is_running);
                    
                    // Desactivar campos si está ejecutándose
                    toggleAutoBackupFormFields(!data.is_running);
                    
                    // Si está ejecutándose, mostrar la sección de progreso inmediatamente
                    if (data.is_running) {
                        var progressSection = document.getElementById('autoBackupProgressSection');
                        if (progressSection) {
                            progressSection.style.display = 'block';
                        }
                        
                        // Si tenemos backup_id, iniciar monitoreo
                        if (data.backup_id) {
                            startAutoBackupProgressMonitoring(data.backup_id);
                        } else {
                            // Mostrar mensaje de espera
                            var progressText = document.getElementById('autoBackupProgressText');
                            var progressBar = document.getElementById('autoBackupProgressBar');
                            var backupLog = document.getElementById('autoBackupLog');
                            if (progressText) {
                                progressText.textContent = 'Esperando inicio del backup...';
                            }
                            if (progressBar) {
                                progressBar.style.width = '0%';
                                progressBar.textContent = '0%';
                            }
                            if (backupLog) {
                                backupLog.textContent = 'El backup automático se está iniciando... Esperando progreso...';
                            }
                        }
                    }
                    
                    // Actualizar próxima copia en modal
                    var modalNextEl = document.getElementById('statusNextBackup');
                    if (modalNextEl && data.config.next_backup) {
                        var nextDate = new Date(data.config.next_backup);
                        var formattedDate = nextDate.toLocaleString('es-ES', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        // Solo establecer la fecha base, el temporizador actualizará el texto completo
                        modalNextEl.setAttribute('data-base-date', formattedDate);
                        
                        // Actualizar offset del servidor si tenemos server_time
                        if (data.server_time) {
                            var serverTime = new Date(data.server_time);
                            var clientTime = new Date();
                            serverTimeOffset = serverTime.getTime() - clientTime.getTime();
                            lastServerTimeUpdate = Date.now();
                            console.log('Offset actualizado desde get_auto_backup_status:', serverTimeOffset, 'ms');
                        }
                        
                        // Iniciar el temporizador del modal inmediatamente
                        updateNextBackupTimer();
                    } else if (modalNextEl) {
                        modalNextEl.textContent = 'No programado';
                        var statusTimerEl = document.getElementById('statusTimerCountdown');
                        var statusTimerLabel = document.getElementById('statusTimerLabel');
                        if (statusTimerEl) {
                            statusTimerEl.textContent = '--:--:--';
                            statusTimerEl.style.color = '#6c757d';
                        }
                        if (statusTimerLabel) {
                            statusTimerLabel.textContent = 'No hay backup programado';
                            statusTimerLabel.style.color = '#6c757d';
                        }
                    }
                }
                
                // Actualizar texto de estado
                var statusText = document.getElementById('autoBackupStatusText');
                if (statusText) {
                    statusText.textContent = data.config.enabled ? 'Activo' : 'Inactivo';
                }
                
                // Actualizar última copia
                updateLastBackupDisplay(data.config.last_backup);
                
                // Actualizar próxima copia y temporizador
                var nextEl = document.getElementById('nextAutoBackup');
                if (nextEl && data.config.next_backup) {
                    var nextDate = new Date(data.config.next_backup);
                    var formattedDate = nextDate.toLocaleString('es-ES', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    // Mantener el texto base sin agregar el mensaje aquí - el temporizador lo hará
                    var baseText = formattedDate;
                    nextEl.setAttribute('data-base-text', baseText);
                    nextEl.textContent = baseText; // Mostrar solo la fecha, sin mensajes adicionales
                    
                    // Actualizar offset del servidor si tenemos server_time
                    if (data.server_time) {
                        var serverTime = new Date(data.server_time);
                        var clientTime = new Date();
                        serverTimeOffset = serverTime.getTime() - clientTime.getTime();
                        lastServerTimeUpdate = Date.now();
                    }
                    
                    // Iniciar temporizador si no está ejecutándose
                    if (!data.is_running) {
                        updateNextBackupTimer();
                    }
                } else if (nextEl) {
                    nextEl.textContent = 'No programado';
                    var timerEl = document.getElementById('autoBackupTimer');
                    if (timerEl) {
                        timerEl.style.display = 'none';
                    }
                }
                
                // Actualizar indicadores de ejecución (incluye badge y color del card)
                updateRunningIndicator(data.is_running);
                
                // Actualizar también en el modal
                var statusNextEl = document.getElementById('statusNextBackup');
                if (statusNextEl) {
                    if (data.config.next_backup) {
                        var nextDate = new Date(data.config.next_backup);
                        var formattedDate = nextDate.toLocaleString('es-ES', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        statusNextEl.textContent = formattedDate;
                    } else {
                        statusNextEl.textContent = 'No programado';
                    }
                }
            }
        })
        .catch(err => {
            // Solo loguear errores críticos, ignorar errores de red temporales
            if (!err.message.includes('Failed to fetch') && !err.message.includes('ERR_INSUFFICIENT_RESOURCES') && !err.message.includes('HTTP')) {
                console.error('Error cargando estado:', err);
                // Mostrar error visual solo si es un error crítico
                var statusCard = document.getElementById('autoBackupStatus');
                if (statusCard) {
                    statusCard.style.background = '#f8d7da';
                    statusCard.style.borderLeftColor = '#dc3545';
                }
            }
        })
        .finally(function() {
            isLoadingStatus = false;
        });
}

// Guardar configuración
function saveAutoBackupConfig(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Verificar que SweetAlert esté disponible
    if (typeof Swal === 'undefined') {
        alert('SweetAlert no está cargado. Recargando página...');
        location.reload();
        return false;
    }
    
    // Deshabilitar botón mientras se guarda
    var btnSave = document.getElementById('btnSaveConfig');
    if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    }
    
    // Mostrar loading inmediatamente
    Swal.fire({
        title: 'Guardando configuración...',
        text: 'Por favor espera mientras se guarda la configuración',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
            // Asegurar que el modal esté visible
            var swalContainer = document.querySelector('.swal2-container');
            if (swalContainer) {
                swalContainer.style.zIndex = '10001';
            }
        }
    });
    
    var formData = new FormData();
    formData.append('enabled', document.getElementById('autoBackupEnabled').checked ? 1 : 0);
    formData.append('frequency', document.getElementById('autoBackupFrequency').value);
    formData.append('backup_type', document.getElementById('autoBackupType').value);
    formData.append('schedule_time', document.getElementById('autoBackupTime').value);
    formData.append('cron_enabled', document.getElementById('autoBackupCronEnabled').checked ? 1 : 0);
    formData.append('max_backups', document.getElementById('autoBackupMaxBackups').value);
    formData.append('token', '<?php echo $token; ?>');
    
    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/save_auto_backup_config.php", 1); ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => {
        console.log('Response status:', r.status);
        if (!r.ok) {
            throw new Error('HTTP error! status: ' + r.status);
        }
        return r.text().then(text => {
            console.log('Response text:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Error parsing JSON:', e, text);
                throw new Error('Respuesta inválida del servidor');
            }
        });
    })
    .then(data => {
        console.log('Respuesta del servidor:', data);
        
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="fas fa-save"></i> Guardar Configuración';
        }
        
        // Cerrar el loading de SweetAlert primero
        Swal.close();
        
        // Pequeña pausa para asegurar que el loading se cierre
        setTimeout(function() {
            if (data && data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '✅ ¡Configuración Guardada!',
                    html: '<div style="text-align: center;"><p style="font-size: 18px; margin: 15px 0; font-weight: 600; color: #28a745;">✓ Guardado Exitosamente</p><p style="font-size: 15px; margin: 10px 0; color: #495057;">La configuración de backup automático se ha guardado correctamente.</p><p style="font-size: 13px; color: #6c757d; margin-top: 15px;">La página se recargará automáticamente...</p></div>',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#28a745',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showCloseButton: false,
                    timer: 2500,
                    timerProgressBar: true,
                    backdrop: true,
                    position: 'center',
                    customClass: {
                        popup: 'swal2-popup-large'
                    },
                    didOpen: () => {
                        // Asegurar que el modal esté visible y por encima de todo
                        var swalContainer = document.querySelector('.swal2-container');
                        if (swalContainer) {
                            swalContainer.style.zIndex = '99999';
                            swalContainer.style.position = 'fixed';
                        }
                        var swalPopup = document.querySelector('.swal2-popup');
                        if (swalPopup) {
                            swalPopup.style.zIndex = '99999';
                        }
                        console.log('SweetAlert abierto, z-index establecido');
                    }
                }).then((result) => {
                    console.log('SweetAlert cerrado, actualizando configuración sin recargar...');
                    autoBackupConfig = data.config;
                    // Actualizar la configuración sin recargar la página
                    loadAutoBackupStatus();
                    updateNextBackupTimer();
                    // NO recargar la página - solo actualizar el estado
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '❌ Error al Guardar',
                    html: '<p style="font-size: 15px;">' + (data && data.message ? data.message : 'No se pudo guardar la configuración') + '</p>',
                    confirmButtonText: 'Intentar de Nuevo',
                    confirmButtonColor: '#dc3545',
                    allowOutsideClick: true,
                    backdrop: true,
                    didOpen: () => {
                        var swalContainer = document.querySelector('.swal2-container');
                        if (swalContainer) {
                            swalContainer.style.zIndex = '99999';
                            swalContainer.style.position = 'fixed';
                        }
                    }
                });
            }
        }, 100);
    })
    .catch(err => {
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="fas fa-save"></i> Guardar Configuración';
        }
        console.error('Error guardando configuración:', err);
        Swal.fire({
            icon: 'error',
            title: '❌ Error de Conexión',
            text: 'No se pudo conectar con el servidor: ' + (err.message || 'Error desconocido'),
            confirmButtonText: 'Reintentar',
            confirmButtonColor: '#dc3545'
        });
    });
    
    return false;
}

// Verificar entorno
function verifyAutoBackupEnvironment() {
    // Verificar que SweetAlert esté disponible
    if (typeof Swal === 'undefined') {
        alert('SweetAlert no está cargado. Recargando página...');
        location.reload();
        return;
    }
    
    var btn = document.getElementById('btnVerifyEnv');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
        btn.style.opacity = '0.7';
    }
    
    // Mostrar área de resultados con loading
    var resultsArea = document.getElementById('actionResults');
    var resultsTitle = document.getElementById('actionResultsTitle');
    var resultsContent = document.getElementById('actionResultsContent');
    if (resultsArea && resultsContent) {
        resultsArea.style.display = 'block';
        resultsTitle.textContent = '⏳ Verificando entorno...';
        resultsContent.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #17a2b8;"></i><p style="margin-top: 10px; color: #6c757d;">Por favor espera...</p></div>';
        resultsArea.style.borderLeftColor = '#17a2b8';
        resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    Swal.fire({
        title: 'Verificando entorno...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/verify_auto_backup_env.php", 1); ?>?t=' + Date.now())
        .then(r => {
            if (!r.ok) {
                throw new Error('HTTP error! status: ' + r.status);
            }
            return r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing JSON:', e, text);
                    throw new Error('Respuesta inválida del servidor: ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
            console.log('Datos de verificación recibidos:', data);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search"></i> Verificar Entorno';
                btn.style.opacity = '1';
            }
            
            // Mostrar resultados debajo de los botones
            var resultsArea = document.getElementById('actionResults');
            var resultsTitle = document.getElementById('actionResultsTitle');
            var resultsContent = document.getElementById('actionResultsContent');
            
            if (resultsArea && resultsContent) {
                // Verificar si hay error del servidor primero
                if (data && data.success === false) {
                    resultsTitle.textContent = '❌ Error del Servidor';
                    resultsArea.style.display = 'block';
                    resultsArea.style.borderLeftColor = '#dc3545';
                    resultsArea.style.borderLeftWidth = '4px';
                    resultsArea.style.borderLeftStyle = 'solid';
                    
                    var html = '<div style="text-align: left;">';
                    html += '<div style="padding: 15px; background: #f8d7da; border-left: 3px solid #dc3545; border-radius: 4px; color: #721c24;">';
                    html += '<strong>❌ Error del Servidor</strong>';
                    html += '<p style="margin: 5px 0 0 0;">' + (data.message || 'Error desconocido') + '</p>';
                    html += '</div>';
                    html += '</div>';
                    
                    resultsContent.innerHTML = html;
                    resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }
                
                resultsTitle.textContent = (data && data.all_ok) ? '✅ Entorno Verificado - Todo Correcto' : '⚠️ Entorno Verificado - Problemas Detectados';
                resultsArea.style.display = 'block';
                resultsArea.style.borderLeftColor = (data && data.all_ok) ? '#28a745' : '#ffc107';
                resultsArea.style.borderLeftWidth = '4px';
                resultsArea.style.borderLeftStyle = 'solid';
                
                var html = '<div style="text-align: left;">';
                if (data && data.checks && Array.isArray(data.checks) && data.checks.length > 0) {
                    // Mostrar todas las verificaciones
                    data.checks.forEach(function(check) {
                        var icon = check.status ? '✅' : '❌';
                        var color = check.status ? '#28a745' : '#dc3545';
                        var bgColor = check.status ? '#d4edda' : '#f8d7da';
                        html += '<div style="margin: 8px 0; padding: 10px; background: ' + bgColor + '; border-left: 3px solid ' + color + '; border-radius: 4px;">';
                        html += '<strong style="color: ' + color + '; font-size: 13px;">' + icon + ' ' + check.name + '</strong>';
                        html += '<p style="margin: 5px 0 0 0; color: #495057; font-size: 12px;">' + check.message + '</p>';
                        html += '</div>';
                    });
                } else {
                    html += '<div style="padding: 15px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px; color: #856404;">';
                    html += '<strong>⚠️ No se recibieron verificaciones</strong>';
                    html += '<p style="margin: 5px 0 0 0;">El servidor respondió pero no devolvió datos de verificación válidos.</p>';
                    if (data) {
                        html += '<details style="margin-top: 10px;"><summary style="cursor: pointer; color: #856404; font-weight: 600;">Ver respuesta del servidor</summary>';
                        html += '<pre style="margin-top: 10px; padding: 10px; background: #fff; border-radius: 4px; font-size: 11px; overflow-x: auto; max-height: 300px;">' + JSON.stringify(data, null, 2) + '</pre>';
                        html += '</details>';
                    }
                    html += '</div>';
                }
                html += '</div>';
                
                resultsContent.innerHTML = html;
                
                // Scroll al área de resultados
                resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // También mostrar SweetAlert
            var alertHtml = '<div style="text-align: left; max-height: 400px; overflow-y: auto;">';
            if (data.checks && data.checks.length > 0) {
                data.checks.forEach(function(check) {
                    var icon = check.status ? '✅' : '❌';
                    var color = check.status ? '#28a745' : '#dc3545';
                    alertHtml += '<p style="margin: 8px 0; padding: 8px; background: ' + (check.status ? '#d4edda' : '#f8d7da') + '; border-radius: 4px;"><strong style="color: ' + color + ';">' + icon + ' ' + check.name + ':</strong> ' + check.message + '</p>';
                });
            } else {
                alertHtml += '<p>No se pudieron realizar verificaciones</p>';
            }
            alertHtml += '</div>';
            
            Swal.fire({
                icon: data.all_ok ? 'success' : 'warning',
                title: data.all_ok ? 'Entorno Correcto' : 'Problemas Detectados',
                html: alertHtml,
                confirmButtonText: 'Aceptar',
                width: '600px'
            });
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search"></i> Verificar Entorno';
                btn.style.opacity = '1';
            }
            console.error('Error verificando entorno:', err);
            
            // Mostrar error en el área de resultados también
            var resultsArea = document.getElementById('actionResults');
            var resultsTitle = document.getElementById('actionResultsTitle');
            var resultsContent = document.getElementById('actionResultsContent');
            
            if (resultsArea && resultsContent) {
                resultsTitle.textContent = '❌ Error al Verificar Entorno';
                resultsArea.style.display = 'block';
                resultsArea.style.borderLeftColor = '#dc3545';
                resultsArea.style.borderLeftWidth = '4px';
                resultsArea.style.borderLeftStyle = 'solid';
                
                resultsContent.innerHTML = '<div style="padding: 15px; background: #f8d7da; border-left: 3px solid #dc3545; border-radius: 4px; color: #721c24;">' +
                    '<strong>❌ Error al conectar con el servidor</strong>' +
                    '<p style="margin: 5px 0 0 0;">' + (err.message || 'Error desconocido') + '</p>' +
                    '<p style="margin: 10px 0 0 0; font-size: 12px; color: #856404;">Verifica la consola del navegador para más detalles.</p>' +
                    '</div>';
                resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo verificar el entorno: ' + (err.message || 'Error desconocido')
            });
        });
}

// Corregir entorno
function fixAutoBackupEnvironment(event) {
    // Prevenir envío del formulario si se llama desde un botón dentro de un form
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Verificar que SweetAlert esté disponible
    if (typeof Swal === 'undefined') {
        alert('SweetAlert no está cargado. Recargando página...');
        location.reload();
        return false;
    }
    
    var btn = document.getElementById('btnFixEnv');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Corrigiendo...';
        btn.style.opacity = '0.7';
    }
    
    // Mostrar área de resultados con loading
    var resultsArea = document.getElementById('actionResults');
    var resultsTitle = document.getElementById('actionResultsTitle');
    var resultsContent = document.getElementById('actionResultsContent');
    if (resultsArea && resultsContent) {
        resultsArea.style.display = 'block';
        resultsTitle.textContent = '⏳ Corrigiendo entorno...';
        resultsContent.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #ffc107;"></i><p style="margin-top: 10px; color: #6c757d;">Por favor espera...</p></div>';
        resultsArea.style.borderLeftColor = '#ffc107';
        resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    Swal.fire({
        title: 'Corrigiendo entorno...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    var formData = new FormData();
    formData.append('token', '<?php echo $token; ?>');
    
    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/fix_auto_backup_env.php", 1); ?>?t=' + Date.now(), {
        method: 'POST',
        body: formData
    })
        .then(r => {
            if (!r.ok) {
                throw new Error('HTTP error! status: ' + r.status);
            }
            return r.text().then(text => {
                console.log('Respuesta de fix_auto_backup_env:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing JSON:', e, text);
                    throw new Error('Respuesta inválida del servidor: ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
                console.log('Datos de corrección recibidos:', data);
                
            // Verificar que la respuesta sea de fix_auto_backup_env, no de save_auto_backup_config
            if (data && data.config && !data.fixes) {
                console.error('Error: Se recibió respuesta de save_auto_backup_config en lugar de fix_auto_backup_env');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-tools"></i> Corregir Entorno';
                    btn.style.opacity = '1';
                }
                
                var resultsArea = document.getElementById('actionResults');
                var resultsTitle = document.getElementById('actionResultsTitle');
                var resultsContent = document.getElementById('actionResultsContent');
                
                if (resultsArea && resultsContent) {
                    resultsTitle.textContent = '⚠️ Error: Respuesta Incorrecta';
                    resultsArea.style.display = 'block';
                    resultsArea.style.borderLeftColor = '#ffc107';
                    resultsArea.style.borderLeftWidth = '4px';
                    resultsArea.style.borderLeftStyle = 'solid';
                    
                    resultsContent.innerHTML = '<div style="padding: 15px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px; color: #856404;">' +
                        '<strong>⚠️ Respuesta inesperada del servidor</strong>' +
                        '<p style="margin: 5px 0 0 0;">Se recibió una respuesta de "Guardar Configuración" en lugar de "Corregir Entorno". Esto puede ocurrir si el formulario se envió accidentalmente.</p>' +
                        '<p style="margin: 10px 0 0 0; font-size: 12px;">Por favor, intenta nuevamente haciendo clic solo en "Corregir Entorno".</p>' +
                        '</div>';
                    resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                
                Swal.close();
                Swal.fire({
                    icon: 'warning',
                    title: 'Respuesta Inesperada',
                    text: 'Se recibió una respuesta de "Guardar Configuración". Por favor intenta nuevamente.',
                    timer: 3000
                });
                return;
            }
            
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-tools"></i> Corregir Entorno';
                btn.style.opacity = '1';
            }
            
            // Mostrar resultados debajo de los botones
            var resultsArea = document.getElementById('actionResults');
            var resultsTitle = document.getElementById('actionResultsTitle');
            var resultsContent = document.getElementById('actionResultsContent');
            
            if (resultsArea && resultsContent) {
                // Calcular estadísticas de las correcciones
                var fixes_performed = 0;
                var fixes_failed = 0;
                var fixes_skipped = 0;
                
                if (data && data.fixes && Array.isArray(data.fixes) && data.fixes.length > 0) {
                    data.fixes.forEach(function(fix) {
                        if (fix.success) {
                            if (fix.message.includes('ya existe') || fix.message.includes('ya existía')) {
                                fixes_skipped++;
                            } else {
                                fixes_performed++;
                            }
                        } else {
                            fixes_failed++;
                        }
                    });
                }
                
                // Determinar título y color según resultados
                var title = '';
                var borderColor = '';
                if (fixes_failed > 0) {
                    title = '⚠️ Entorno Corregido - Corrección Parcial';
                    borderColor = '#ffc107';
                } else if (fixes_performed > 0) {
                    title = '✅ Entorno Corregido - Todo Resuelto';
                    borderColor = '#28a745';
                } else if (fixes_skipped > 0 && fixes_failed === 0) {
                    title = '✅ Entorno Verificado - Todo Correcto';
                    borderColor = '#28a745';
                } else {
                    title = 'ℹ️ No se detectaron problemas';
                    borderColor = '#17a2b8';
                }
                
                resultsTitle.textContent = title;
                resultsArea.style.display = 'block';
                resultsArea.style.borderLeftColor = borderColor;
                resultsArea.style.borderLeftWidth = '4px';
                resultsArea.style.borderLeftStyle = 'solid';
                
                var html = '<div style="text-align: left;">';
                
                // Verificar si data tiene success: false (error del servidor)
                if (data && data.success === false) {
                    resultsTitle.textContent = '❌ Error del Servidor';
                    resultsArea.style.borderLeftColor = '#dc3545';
                    
                    html += '<div style="padding: 15px; background: #f8d7da; border-left: 3px solid #dc3545; border-radius: 4px; color: #721c24;">';
                    html += '<strong>❌ Error del Servidor</strong>';
                    html += '<p style="margin: 5px 0 0 0;">' + (data.message || 'Error desconocido') + '</p>';
                    html += '</div>';
                } else if (data && data.fixes && Array.isArray(data.fixes) && data.fixes.length > 0) {
                    // Mostrar resumen si hay correcciones
                    // Resumen de acciones realizadas
                    html += '<div style="padding: 12px; background: #f8f9fa; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid ' + borderColor + ';">';
                    html += '<div style="font-weight: 600; color: #495057; margin-bottom: 8px; font-size: 14px;">📊 Resumen de Verificaciones:</div>';
                    if (fixes_performed > 0) {
                        html += '<div style="color: #28a745; margin: 4px 0; font-size: 13px;">✅ ' + fixes_performed + ' corrección(es) realizada(s)</div>';
                    }
                    if (fixes_skipped > 0) {
                        html += '<div style="color: #17a2b8; margin: 4px 0; font-size: 13px;">ℹ️ ' + fixes_skipped + ' elemento(s) ya correcto(s)</div>';
                    }
                    if (fixes_failed > 0) {
                        html += '<div style="color: #dc3545; margin: 4px 0; font-size: 13px;">❌ ' + fixes_failed + ' corrección(es) fallida(s)</div>';
                    }
                    html += '</div>';
                    
                    // Detalles de cada corrección
                    html += '<div style="font-weight: 600; color: #495057; margin-bottom: 10px; font-size: 14px;">📋 Detalles de Verificaciones:</div>';
                    data.fixes.forEach(function(fix) {
                        var icon = fix.success ? '✅' : '❌';
                        var color = fix.success ? '#28a745' : '#dc3545';
                        var bgColor = fix.success ? '#d4edda' : '#f8d7da';
                        
                        // Si ya existe, usar color azul
                        if (fix.success && (fix.message.includes('ya existe') || fix.message.includes('ya existía'))) {
                            color = '#17a2b8';
                            bgColor = '#d1ecf1';
                            icon = 'ℹ️';
                        }
                        
                        html += '<div style="margin: 8px 0; padding: 10px; background: ' + bgColor + '; border-left: 3px solid ' + color + '; border-radius: 4px;">';
                        html += '<strong style="color: ' + color + '; font-size: 13px;">' + icon + ' ' + fix.name + '</strong>';
                        html += '<p style="margin: 5px 0 0 0; color: #495057; font-size: 12px;">' + fix.message + '</p>';
                        html += '</div>';
                    });
                } else if (data && data.success !== false && data.message === 'Configuración guardada correctamente') {
                    // Detectar respuesta incorrecta de save_auto_backup_config
                    html += '<div style="padding: 15px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px; color: #856404;">';
                    html += '<strong>⚠️ Respuesta Incorrecta del Servidor</strong>';
                    html += '<p style="margin: 5px 0 0 0;">Se recibió una respuesta de "Guardar Configuración" en lugar de "Corregir Entorno".</p>';
                    html += '<p style="margin: 10px 0 0 0; font-size: 12px; color: #721c24;">Esto puede ocurrir si el formulario se envió accidentalmente. Por favor, haz clic solo en "Corregir Entorno" sin enviar el formulario.</p>';
                    html += '</div>';
                } else {
                    // Si no hay fixes pero hay success, puede ser una respuesta válida vacía
                    if (data && data.success !== false) {
                        html += '<div style="padding: 15px; background: #d1ecf1; border-left: 3px solid #17a2b8; border-radius: 4px; color: #0c5460;">';
                        html += '<strong>ℹ️ Verificación completada</strong>';
                        html += '<p style="margin: 5px 0 0 0;">No se recibieron datos de corrección del servidor.</p>';
                        html += '<p style="margin: 5px 0 0 0; font-size: 12px;">El script de corrección no devolvió información. Esto puede indicar un problema con el script del servidor.</p>';
                        if (data.message && data.message !== 'Configuración guardada correctamente') {
                            html += '<p style="margin: 5px 0 0 0;"><strong>Mensaje del servidor:</strong> ' + data.message + '</p>';
                        }
                        if (data) {
                            html += '<details style="margin-top: 10px;"><summary style="cursor: pointer; color: #0c5460; font-weight: 600;">Ver respuesta completa (para depuración)</summary>';
                            html += '<pre style="margin-top: 10px; padding: 10px; background: #fff; border-radius: 4px; font-size: 11px; overflow-x: auto; max-height: 300px;">' + JSON.stringify(data, null, 2) + '</pre>';
                            html += '</details>';
                        }
                        html += '</div>';
                    } else {
                        html += '<div style="padding: 15px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px; color: #856404;">';
                        html += '<strong>⚠️ No se recibieron datos de corrección</strong>';
                        html += '<p style="margin: 5px 0 0 0;">El servidor respondió pero no devolvió datos de corrección válidos.</p>';
                        if (data && data.message && data.message !== 'Configuración guardada correctamente') {
                            html += '<p style="margin: 5px 0 0 0; color: #721c24;"><strong>Mensaje:</strong> ' + data.message + '</p>';
                        }
                        if (data) {
                            html += '<details style="margin-top: 10px;"><summary style="cursor: pointer; color: #856404; font-weight: 600;">Ver respuesta del servidor (para depuración)</summary>';
                            html += '<pre style="margin-top: 10px; padding: 10px; background: #fff; border-radius: 4px; font-size: 11px; overflow-x: auto; max-height: 300px;">' + JSON.stringify(data, null, 2) + '</pre>';
                            html += '</details>';
                        }
                        html += '</div>';
                    }
                }
                html += '</div>';
                
                resultsContent.innerHTML = html;
                
                // Scroll al área de resultados
                resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // También mostrar SweetAlert con resumen mejorado
            Swal.close();
            
            var fixes_performed = 0;
            var fixes_failed = 0;
            var fixes_skipped = 0;
            
            if (data && data.fixes && data.fixes.length > 0) {
                data.fixes.forEach(function(fix) {
                    if (fix.success) {
                        if (fix.message.includes('ya existe') || fix.message.includes('ya existía')) {
                            fixes_skipped++;
                        } else {
                            fixes_performed++;
                        }
                    } else {
                        fixes_failed++;
                    }
                });
            }
            
            var alertTitle = '';
            var alertIcon = 'success';
            var alertText = '';
            
            if (fixes_failed > 0) {
                alertTitle = 'Corrección Parcial';
                alertIcon = 'warning';
                alertText = fixes_performed + ' corrección(es) realizada(s), pero ' + fixes_failed + ' fallaron. Revisa los detalles en el área de resultados.';
            } else if (fixes_performed > 0) {
                alertTitle = 'Entorno Corregido';
                alertIcon = 'success';
                alertText = fixes_performed + ' corrección(es) realizada(s) correctamente.';
            } else if (fixes_skipped > 0) {
                alertTitle = 'Entorno Verificado';
                alertIcon = 'info';
                alertText = 'Todo está correctamente configurado (' + fixes_skipped + ' elementos verificados).';
            } else {
                alertTitle = 'Sin Cambios';
                alertIcon = 'info';
                alertText = 'No se recibieron datos del servidor.';
            }
            
            Swal.fire({
                icon: alertIcon,
                title: alertTitle,
                text: alertText,
                timer: 3000,
                timerProgressBar: true
            });
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-tools"></i> Corregir Entorno';
                btn.style.opacity = '1';
            }
            console.error('Error corrigiendo entorno:', err);
            
            // Mostrar error en el área de resultados
            var resultsArea = document.getElementById('actionResults');
            var resultsTitle = document.getElementById('actionResultsTitle');
            var resultsContent = document.getElementById('actionResultsContent');
            
            if (resultsArea && resultsContent) {
                resultsTitle.textContent = '❌ Error al Corregir Entorno';
                resultsArea.style.display = 'block';
                resultsArea.style.borderLeftColor = '#dc3545';
                resultsArea.style.borderLeftWidth = '4px';
                resultsArea.style.borderLeftStyle = 'solid';
                
                resultsContent.innerHTML = '<div style="padding: 15px; background: #f8d7da; border-left: 3px solid #dc3545; border-radius: 4px; color: #721c24;">' +
                    '<strong>❌ Error al conectar con el servidor</strong>' +
                    '<p style="margin: 5px 0 0 0;">' + (err.message || 'Error desconocido') + '</p>' +
                    '<p style="margin: 10px 0 0 0; font-size: 12px; color: #856404;">Verifica la consola del navegador (F12) para más detalles.</p>' +
                    '</div>';
                resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo corregir el entorno: ' + (err.message || 'Error desconocido')
            });
        });
}

// Gestionar cronjob (instalar/desinstalar)
function manageCronjob(action, event) {
    // Prevenir envío del formulario si se llama desde un botón dentro de un form
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Verificar que SweetAlert esté disponible
    if (typeof Swal === 'undefined') {
        alert('SweetAlert no está cargado. Recargando página...');
        location.reload();
        return false;
    }
    
    var btnId = action === 'install' ? 'btnInstallCron' : 'btnUninstallCron';
    var btn = document.getElementById(btnId);
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (action === 'install' ? 'Instalando...' : 'Desinstalando...');
        btn.style.opacity = '0.7';
    }
    
    // Mostrar área de resultados con loading
    var resultsArea = document.getElementById('actionResults');
    var resultsTitle = document.getElementById('actionResultsTitle');
    var resultsContent = document.getElementById('actionResultsContent');
    if (resultsArea && resultsContent) {
        resultsArea.style.display = 'block';
        resultsTitle.textContent = '⏳ ' + (action === 'install' ? 'Instalando cronjob...' : 'Desinstalando cronjob...');
        resultsContent.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #17a2b8;"></i><p style="margin-top: 10px; color: #6c757d;">Por favor espera...</p></div>';
        resultsArea.style.borderLeftColor = '#17a2b8';
        resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    Swal.fire({
        title: action === 'install' ? 'Instalando cronjob...' : 'Desinstalando cronjob...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    var formData = new FormData();
    formData.append('token', '<?php echo $token; ?>');
    formData.append('action', action);
    
    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/manage_cronjob.php", 1); ?>?t=' + Date.now(), {
        method: 'POST',
        body: formData
    })
        .then(r => {
            if (!r.ok) {
                throw new Error('HTTP error! status: ' + r.status);
            }
            return r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing JSON:', e, text);
                    throw new Error('Respuesta inválida del servidor: ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
            console.log('Datos de gestión de cronjob recibidos:', data);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = action === 'install' ? 
                    '<i class="fas fa-play-circle"></i> Instalar Cronjob' : 
                    '<i class="fas fa-stop-circle"></i> Desinstalar Cronjob';
                btn.style.opacity = '1';
            }
            
            // Mostrar resultados debajo de los botones
            var resultsArea = document.getElementById('actionResults');
            var resultsTitle = document.getElementById('actionResultsTitle');
            var resultsContent = document.getElementById('actionResultsContent');
            
            if (resultsArea && resultsContent) {
                resultsTitle.textContent = data.success ? 
                    (action === 'install' ? '✅ Cronjob Instalado' : '✅ Cronjob Desinstalado') : 
                    '❌ Error al ' + (action === 'install' ? 'Instalar' : 'Desinstalar') + ' Cronjob';
                resultsArea.style.display = 'block';
                resultsArea.style.borderLeftColor = data.success ? '#28a745' : '#dc3545';
                resultsArea.style.borderLeftWidth = '4px';
                resultsArea.style.borderLeftStyle = 'solid';
                
                var html = '<div style="text-align: left;">';
                if (data.success) {
                    html += '<div style="padding: 15px; background: #d4edda; border-left: 3px solid #28a745; border-radius: 4px; color: #155724;">';
                    html += '<strong>✅ ' + (data.already_installed || data.already_uninstalled ? 'Estado: ' : 'Acción completada: ') + '</strong>';
                    html += '<p style="margin: 5px 0 0 0;">' + data.message + '</p>';
                    if (data.cron_line) {
                        html += '<p style="margin: 10px 0 0 0; font-size: 12px; font-family: monospace; background: #f8f9fa; padding: 8px; border-radius: 4px;">' + data.cron_line + '</p>';
                    }
                    html += '</div>';
                } else {
                    html += '<div style="padding: 15px; background: #f8d7da; border-left: 3px solid #dc3545; border-radius: 4px; color: #721c24;">';
                    html += '<strong>❌ Error</strong>';
                    html += '<p style="margin: 5px 0 0 0;">' + data.message + '</p>';
                    if (data.debug) {
                        html += '<details style="margin-top: 10px;"><summary style="cursor: pointer; color: #856404; font-weight: 600;">Detalles técnicos (para depuración)</summary>';
                        html += '<pre style="margin-top: 10px; padding: 10px; background: #fff; border-radius: 4px; font-size: 11px; overflow-x: auto; max-height: 400px; overflow-y: auto;">' + JSON.stringify(data.debug, null, 2) + '</pre>';
                        if (data.debug && data.debug.cron_line) {
                            html += '<div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-radius: 4px; border-left: 3px solid #ffc107;">';
                            html += '<strong style="color: #856404;">📋 Instrucción Manual:</strong>';
                            if (data.debug.current_user) {
                                html += '<p style="margin: 8px 0 0 0; color: #856404; font-size: 12px;">Usuario del servidor web: <strong>' + data.debug.current_user + '</strong></p>';
                            }
                            html += '<p style="margin: 8px 0 0 0; color: #856404; font-size: 12px;">Si el usuario del servidor web no tiene permisos para ejecutar crontab automáticamente, puedes instalar el cronjob manualmente ejecutando en el servidor:</p>';
                            html += '<code style="display: block; margin: 8px 0; padding: 10px; background: #f8f9fa; border-radius: 4px; font-family: monospace; font-size: 12px; color: #212529;">crontab -e</code>';
                            html += '<p style="margin: 8px 0 0 0; color: #856404; font-size: 12px;">Y agregar la siguiente línea:</p>';
                            html += '<code style="display: block; margin: 8px 0; padding: 10px; background: #f8f9fa; border-radius: 4px; font-family: monospace; font-size: 12px; color: #212529; word-break: break-all; white-space: pre-wrap;">' + data.debug.cron_line + '</code>';
                            if (data.debug.suggestion) {
                                html += '<p style="margin: 10px 0 0 0; padding: 8px; background: #e7f3ff; border-radius: 4px; color: #004085; font-size: 11px;"><strong>💡 Sugerencia:</strong> ' + data.debug.suggestion + '</p>';
                            }
                            html += '</div>';
                        }
                        html += '</details>';
                    }
                    html += '</div>';
                }
                html += '</div>';
                
                resultsContent.innerHTML = html;
                resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            Swal.close();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: action === 'install' ? 'Cronjob Instalado' : 'Cronjob Desinstalado',
                    text: data.message,
                    timer: 3000,
                    timerProgressBar: true
                });
                
                // Si se instaló correctamente, recargar la página para actualizar el estado
                if (!data.already_installed && !data.already_uninstalled) {
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    html: data.message + (data.debug ? '<br><small style="color: #856404;">Ver detalles en el área de resultados</small>' : ''),
                    confirmButtonText: 'Aceptar'
                });
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = action === 'install' ? 
                    '<i class="fas fa-play-circle"></i> Instalar Cronjob' : 
                    '<i class="fas fa-stop-circle"></i> Desinstalar Cronjob';
                btn.style.opacity = '1';
            }
            console.error('Error gestionando cronjob:', err);
            
            // Mostrar error en el área de resultados
            var resultsArea = document.getElementById('actionResults');
            var resultsTitle = document.getElementById('actionResultsTitle');
            var resultsContent = document.getElementById('actionResultsContent');
            
            if (resultsArea && resultsContent) {
                resultsTitle.textContent = '❌ Error al ' + (action === 'install' ? 'Instalar' : 'Desinstalar') + ' Cronjob';
                resultsArea.style.display = 'block';
                resultsArea.style.borderLeftColor = '#dc3545';
                resultsArea.style.borderLeftWidth = '4px';
                resultsArea.style.borderLeftStyle = 'solid';
                
                resultsContent.innerHTML = '<div style="padding: 15px; background: #f8d7da; border-left: 3px solid #dc3545; border-radius: 4px; color: #721c24;">' +
                    '<strong>❌ Error al conectar con el servidor</strong>' +
                    '<p style="margin: 5px 0 0 0;">' + (err.message || 'Error desconocido') + '</p>' +
                    '<p style="margin: 10px 0 0 0; font-size: 12px; color: #856404;">Verifica la consola del navegador (F12) para más detalles.</p>' +
                    '</div>';
                resultsArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo ' + (action === 'install' ? 'instalar' : 'desinstalar') + ' el cronjob: ' + (err.message || 'Error desconocido')
            });
        });
}

// Actualizar temporizador de próxima copia (actualiza dinámicamente)
var nextBackupTimerTimeout = null;
var serverTimeOffset = 0; // Diferencia entre hora del servidor y hora del cliente
var lastServerTimeUpdate = 0;

function updateServerTimeOffset() {
    // Obtener hora del servidor para calcular el offset
    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/get_server_time.php", 1); ?>?t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            if (data.success && data.timestamp) {
                var serverTimestamp = data.timestamp * 1000; // Convertir a milisegundos
                var clientTimestamp = Date.now();
                serverTimeOffset = serverTimestamp - clientTimestamp;
                lastServerTimeUpdate = Date.now();
                console.log('Offset de hora del servidor calculado:', serverTimeOffset, 'ms');
            }
        })
        .catch(err => {
            console.warn('No se pudo obtener hora del servidor, usando hora local:', err);
            serverTimeOffset = 0;
        });
}

// Actualizar offset cada 5 minutos
setInterval(updateServerTimeOffset, 300000);
updateServerTimeOffset(); // Inicializar inmediatamente

function updateNextBackupTimer() {
    // Limpiar timeout anterior si existe
    if (nextBackupTimerTimeout) {
        clearTimeout(nextBackupTimerTimeout);
        nextBackupTimerTimeout = null;
    }
    
    // Verificar si el backup está ejecutándose primero (sin esperar respuesta para no bloquear)
    checkAutoBackupRunningStatus();
    
    if (autoBackupConfig && autoBackupConfig.next_backup) {
        // Parsear la fecha del servidor correctamente
        var nextDate = new Date(autoBackupConfig.next_backup);
        // Usar hora del servidor ajustada por el offset calculado
        var now = new Date(Date.now() + serverTimeOffset);
        var diff = nextDate.getTime() - now.getTime();
        
        // Debug: verificar el cálculo siempre cuando está dentro de 10 minutos
        if (Math.abs(diff) < 600000) {
            console.log('Temporizador DEBUG:', {
                'nextDate (ISO)': nextDate.toISOString(),
                'nextDate (local)': nextDate.toLocaleString('es-ES'),
                'now cliente': new Date().toISOString(),
                'now servidor (ajustado)': now.toISOString(),
                'serverTimeOffset (ms)': serverTimeOffset,
                'diff (ms)': diff,
                'diff (minutos)': Math.round(diff/60000),
                'diff > 0': diff > 0,
                'diff <= 0 && diff > -60000': (diff <= 0 && diff > -60000),
                'diff <= -60000': diff <= -60000
            });
        }
        
        var timerEl = document.getElementById('autoBackupTimer');
        var nextEl = document.getElementById('nextAutoBackup');
        var statusNextEl = document.getElementById('statusNextBackup');
        
        // Mostrar temporizador solo si aún no llegó la hora o está dentro de 5 minutos después
        // Si ya pasó más de 5 minutos, mostrar "¡Debería ejecutarse ahora!"
        // Cambiar la condición: diff > -300000 significa que NO ha pasado más de 5 minutos
        if (diff > -300000) { // Falta tiempo (diff positivo) o pasó menos de 5 minutos (diff negativo pero > -5min)
            // Calcular tiempo restante (puede ser negativo si ya pasó)
            var absDiff = Math.abs(diff);
            var isPast = diff < 0;
            var days = Math.floor(absDiff / (1000 * 60 * 60 * 24));
            var hours = Math.floor((absDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((absDiff % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((absDiff % (1000 * 60)) / 1000);
            
            // Formatear tiempo de forma legible
            var timeStr = '';
            if (days > 0) {
                timeStr = days + 'd ' + hours + 'h ' + minutes + 'm';
            } else if (hours > 0) {
                timeStr = hours + 'h ' + minutes + 'm ' + seconds + 's';
            } else if (minutes > 0) {
                timeStr = minutes + 'm ' + seconds + 's';
            } else {
                timeStr = seconds + 's';
            }
            
            // Si ya pasó la hora pero menos de 5 minutos, mostrar temporizador con prefijo
            if (isPast && diff > -300000) {
                timeStr = '-' + timeStr; // Indicar que ya pasó
            }
            
            // Actualizar elementos del temporizador
            var timerLabelEl = document.getElementById('autoBackupTimerLabel');
            
            if (timerEl) {
                // No actualizar si ya está mostrando "Ejecutándose..." (eso lo maneja checkAutoBackupRunningStatus)
                if (timerEl.textContent.includes('Ejecutándose')) {
                    // Mantener el texto de ejecutándose, solo actualizar la próxima verificación
                    if (timerLabelEl) {
                        timerLabelEl.textContent = 'Backup en ejecución';
                        timerLabelEl.style.display = 'block';
                        timerLabelEl.style.color = '#ff9800';
                    }
                    nextBackupTimerTimeout = setTimeout(updateNextBackupTimer, updateInterval);
                    return; // Salir temprano para no sobrescribir el estado de ejecución
                }
                
                if (diff <= -60000) { // Si pasó más de 1 minuto
                    // Solo mostrar "¡Debería ejecutarse ahora!" si ya pasó más de 1 minuto
                    timerEl.textContent = '¡Debería ejecutarse ahora!';
                    timerEl.style.display = 'inline-block';
                    timerEl.style.fontSize = '12px';
                    timerEl.style.fontFamily = "'Arial', sans-serif";
                    timerEl.style.fontWeight = '700';
                    timerEl.style.color = '#fff';
                    timerEl.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
                    timerEl.style.borderColor = '#dc3545';
                    timerEl.style.padding = '4px 10px';
                    timerEl.style.borderRadius = '5px';
                    timerEl.style.boxShadow = '0 2px 3px rgba(220,53,69,0.25)';
                    timerEl.style.letterSpacing = '0.3px';
                    if (timerLabelEl) {
                        timerLabelEl.textContent = '¡Debería ejecutarse ahora!';
                        timerLabelEl.style.display = 'block';
                        timerLabelEl.style.color = '#dc3545';
                    }
                    
                    // Programar verificación después de 3 segundos para cambiar a "Ejecutándose" si realmente está corriendo
                    setTimeout(function() {
                        checkAutoBackupRunningStatus().then(function() {
                            // Si no está ejecutándose aún, continuar mostrando el mensaje
                            // Si está ejecutándose, el mensaje cambiará automáticamente
                        });
                    }, 3000);
                } else if (diff <= 0 && diff > -60000) {
                    // Acaba de pasar (menos de 1 minuto)
                    timerEl.textContent = 'Debería iniciarse';
                    timerEl.style.display = 'inline-block';
                    timerEl.style.fontSize = '12px';
                    timerEl.style.fontFamily = "'Arial', sans-serif";
                    timerEl.style.fontWeight = '700';
                    timerEl.style.color = '#fff';
                    timerEl.style.background = 'linear-gradient(135deg, #ff9800 0%, #f57c00 100%)';
                    timerEl.style.borderColor = '#ff9800';
                    timerEl.style.padding = '4px 10px';
                    timerEl.style.borderRadius = '5px';
                    timerEl.style.boxShadow = '0 2px 3px rgba(255,152,0,0.25)';
                    timerEl.style.letterSpacing = '0.3px';
                    if (timerLabelEl) {
                        timerLabelEl.textContent = 'Debería iniciarse pronto';
                        timerLabelEl.style.display = 'block';
                        timerLabelEl.style.color = '#ff9800';
                    }
                } else if (diff > 0) {
                    // Mostrar temporizador normalmente cuando falta tiempo
                    timerEl.textContent = timeStr; // Sin paréntesis para que se vea mejor
                    timerEl.style.display = 'inline-block';
                    timerEl.style.fontSize = '13px';
                    timerEl.style.fontFamily = "'Courier New', monospace";
                    timerEl.style.fontWeight = '700';
                    timerEl.style.padding = '4px 10px';
                    timerEl.style.borderRadius = '5px';
                    timerEl.style.border = '1.5px solid';
                    timerEl.style.boxShadow = '0 2px 3px rgba(0,0,0,0.15)';
                    timerEl.style.letterSpacing = '0.5px';
                    
                    // Cambiar color según tiempo restante
                    if (diff < 60000) { // Menos de 1 minuto
                        timerEl.style.color = '#dc3545';
                        timerEl.style.background = 'linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%)';
                        timerEl.style.borderColor = '#dc3545';
                    } else if (diff < 3600000) { // Menos de 1 hora
                        timerEl.style.color = '#ff9800';
                        timerEl.style.background = 'linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%)';
                        timerEl.style.borderColor = '#ff9800';
                    } else {
                        timerEl.style.color = '#17a2b8';
                        timerEl.style.background = 'linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%)';
                        timerEl.style.borderColor = '#17a2b8';
                    }
                    
                    // Mostrar etiqueta "Tiempo restante próxima copia automática"
                    if (timerLabelEl) {
                        timerLabelEl.textContent = 'Tiempo restante próxima copia automática';
                        timerLabelEl.style.display = 'block';
                        timerLabelEl.style.color = '#6c757d';
                    }
                }
            } else {
                // Ocultar etiqueta si no hay temporizador
                if (timerLabelEl) {
                    timerLabelEl.style.display = 'none';
                }
            }
            
            if (nextEl) {
                var baseText = nextEl.getAttribute('data-base-text');
                if (!baseText) {
                    // Extraer el texto base si no existe el atributo
                    baseText = nextEl.textContent.split(' (')[0].replace('¡Debería ejecutarse ahora!', '').trim();
                    nextEl.setAttribute('data-base-text', baseText);
                }
                
                // Solo mostrar la fecha base sin mensajes adicionales - el temporizador se mostrará aparte
                if (diff > 0) {
                    // Aún falta tiempo - mostrar solo la fecha
                    nextEl.textContent = baseText;
                } else if (diff <= 0 && diff > -60000) {
                    // Acaba de pasar (menos de 1 minuto)
                    nextEl.textContent = baseText;
                } else {
                    // Pasó más de 1 minuto - el temporizador mostrará el mensaje
                    nextEl.textContent = baseText;
                }
            }
            
            // Actualizar también en el modal
            if (statusNextEl && autoBackupConfig.next_backup) {
                // Obtener fecha base del atributo o calcularla
                var baseDateStr = statusNextEl.getAttribute('data-base-date');
                if (!baseDateStr) {
                    var nextDate = new Date(autoBackupConfig.next_backup);
                    baseDateStr = nextDate.toLocaleString('es-ES', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    statusNextEl.setAttribute('data-base-date', baseDateStr);
                }
                
                // No actualizar si ya está mostrando "Ejecutándose..." 
                if (!statusNextEl.innerHTML.includes('Ejecutándose')) {
                    if (diff > 0) {
                        // AÚN FALTA TIEMPO - Mostrar fecha con temporizador
                        var color = diff < 60000 ? '#dc3545' : (diff < 3600000 ? '#ffc107' : '#17a2b8');
                        statusNextEl.innerHTML = baseDateStr + ' <span style="color: ' + color + '; font-size: 11px;">(' + timeStr + ')</span>';
                    } else if (diff <= 0 && diff > -60000) {
                        // Acaba de pasar (menos de 1 minuto)
                        statusNextEl.innerHTML = baseDateStr + ' <span style="color: #ff9800; font-size: 11px;">(Debería iniciarse)</span>';
                    } else if (diff <= -60000) {
                        // Pasó más de 1 minuto
                        statusNextEl.innerHTML = '<span style="color: #dc3545; font-weight: bold;">¡Debería ejecutarse ahora!</span>';
                    }
                }
            }
            
            // Actualizar contador visual en el modal
            var statusTimerEl = document.getElementById('statusTimerCountdown');
            var statusTimerLabel = document.getElementById('statusTimerLabel');
            var statusTimerContainer = document.getElementById('statusNextBackupTimer');
            
            // Restaurar estilos del contenedor cuando no está ejecutándose
            if (statusTimerContainer) {
                statusTimerContainer.style.background = 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)';
                statusTimerContainer.style.borderColor = '#dee2e6';
                statusTimerContainer.style.borderWidth = '2px';
            }
            
            // Solo mostrar "debería ejecutarse ahora" si realmente pasó más de 1 minuto
            // Si diff es positivo, significa que falta tiempo (no ha llegado aún)
            if (statusTimerEl) {
                // Limpiar animación si existe
                statusTimerEl.style.animation = 'none';
                
                if (diff > 0) {
                    // FALTA TIEMPO - Mostrar contador normal con tiempo restante
                    var totalSeconds = Math.floor(diff / 1000);
                    var hours = Math.floor(totalSeconds / 3600);
                    var minutes = Math.floor((totalSeconds % 3600) / 60);
                    var secs = totalSeconds % 60;
                    
                    var formattedTime = String(hours).padStart(2, '0') + ':' + 
                                      String(minutes).padStart(2, '0') + ':' + 
                                      String(secs).padStart(2, '0');
                    
                    statusTimerEl.textContent = formattedTime;
                    statusTimerEl.style.display = 'block';
                    statusTimerEl.style.fontSize = '24px';
                    statusTimerEl.style.fontFamily = "'Courier New', monospace";
                    statusTimerEl.style.fontWeight = 'bold';
                    statusTimerEl.style.padding = '0';
                    statusTimerEl.style.marginBottom = '6px';
                    statusTimerEl.style.background = 'transparent';
                    statusTimerEl.style.border = 'none';
                    statusTimerEl.style.boxShadow = 'none';
                    statusTimerEl.style.letterSpacing = '2px';
                    
                    // Cambiar color según tiempo restante
                    if (diff < 60000) { // Menos de 1 minuto
                        statusTimerEl.style.color = '#dc3545';
                    } else if (diff < 3600000) { // Menos de 1 hora
                        statusTimerEl.style.color = '#ffc107';
                    } else {
                        statusTimerEl.style.color = '#17a2b8';
                    }
                    
                    if (statusTimerLabel) {
                        statusTimerLabel.textContent = 'Tiempo restante';
                        statusTimerLabel.style.color = '#6c757d';
                        statusTimerLabel.style.fontWeight = '500';
                        statusTimerLabel.style.fontSize = '12px';
                    }
                } else if (diff <= 0 && diff > -60000) {
                    // Acaba de pasar (menos de 1 minuto después)
                    var totalSeconds = Math.floor(Math.abs(diff) / 1000);
                    var secs = totalSeconds % 60;
                    statusTimerEl.textContent = '00:00:' + String(secs).padStart(2, '0');
                    statusTimerEl.style.color = '#ff9800';
                    statusTimerEl.style.display = 'block';
                    if (statusTimerLabel) {
                        statusTimerLabel.textContent = 'Debería iniciarse';
                        statusTimerLabel.style.color = '#ff9800';
                    }
                } else if (diff <= -60000 && diff > -300000) {
                    // Pasó entre 1 minuto y 5 minutos
                    statusTimerEl.textContent = '00:00:00';
                    statusTimerEl.style.color = '#dc3545';
                    statusTimerEl.style.display = 'block';
                    if (statusTimerLabel) {
                        statusTimerLabel.textContent = '¡Debería ejecutarse ahora!';
                        statusTimerLabel.style.color = '#dc3545';
                    }
                } else {
                    // Pasó más de 5 minutos
                    statusTimerEl.textContent = '--:--:--';
                    statusTimerEl.style.color = '#dc3545';
                    statusTimerEl.style.display = 'block';
                    if (statusTimerLabel) {
                        statusTimerLabel.textContent = '¡Debería ejecutarse ahora!';
                        statusTimerLabel.style.color = '#dc3545';
                    }
                }
            }
            
            // Programar próxima actualización dinámicamente (más frecuente cuando está cerca o acaba de pasar)
            var updateInterval = 1000; // Por defecto cada segundo
            if (Math.abs(diff) < 60000) {
                updateInterval = 500; // Si está dentro de 1 minuto (antes o después), actualizar cada 0.5 segundos
            }
            nextBackupTimerTimeout = setTimeout(updateNextBackupTimer, updateInterval);
        } else {
            // Ya pasó más de 5 minutos - mostrar mensaje de que debería ejecutarse
            if (timerEl) {
                timerEl.textContent = '(¡Debería ejecutarse ahora!)';
                timerEl.style.color = '#dc3545';
                timerEl.style.fontWeight = 'bold';
            }
            
            if (statusNextEl) {
                statusNextEl.innerHTML = '<span style="color: #dc3545; font-weight: bold;">¡Debería ejecutarse ahora!</span>';
            }
            
            // Cuando ya pasó mucho tiempo, verificar frecuentemente si se ejecutó
            nextBackupTimerTimeout = setTimeout(updateNextBackupTimer, 3000); // Verificar cada 3 segundos
        }
    } else {
        // No hay próxima copia programada
        var timerEl = document.getElementById('autoBackupTimer');
        if (timerEl) {
            timerEl.style.display = 'none';
        }
    }
}

// Monitoreo de progreso para backup automático
var autoBackupProgressInterval = null;
var autoBackupCurrentId = null;
var autoBackupLastLogLength = 0;
var autoBackupLastLogContent = '';

function startAutoBackupProgressMonitoring(backupId) {
    // Si ya estamos monitoreando el mismo backup, no hacer nada
    if (autoBackupProgressInterval && autoBackupCurrentId === backupId) {
        return;
    }
    
    // Detener monitoreo anterior si existe
    if (autoBackupProgressInterval) {
        clearInterval(autoBackupProgressInterval);
        autoBackupProgressInterval = null;
    }
    
    // Mostrar sección de progreso
    var progressSection = document.getElementById('autoBackupProgressSection');
    if (progressSection) {
        progressSection.style.display = 'block';
    }
    
    autoBackupCurrentId = backupId;
    autoBackupLastLogLength = 0;
    autoBackupLastLogContent = '';
    
    // Iniciar monitoreo cada segundo
    autoBackupProgressInterval = setInterval(function() {
        if (!autoBackupCurrentId) {
            clearInterval(autoBackupProgressInterval);
            autoBackupProgressInterval = null;
            return;
        }
        
        const progressUrl = '<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/get_progress.php'; ?>?backup_id=' + autoBackupCurrentId + '&t=' + Date.now();
        
        fetch(progressUrl, {
            method: 'GET',
            cache: 'no-cache',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data !== null && data !== undefined) {
                updateAutoBackupProgress(data.progress || 0, data.log || '');
                
                // Si está completado, verificar que el backup terminó
                if (data.completed) {
                    clearInterval(autoBackupProgressInterval);
                    autoBackupProgressInterval = null;
                    
                    // Esperar un momento y verificar estado de nuevo
                    setTimeout(function() {
                        checkAutoBackupRunningStatus();
                        // Ocultar sección de progreso después de un tiempo
                        setTimeout(function() {
                            var progressSection = document.getElementById('autoBackupProgressSection');
                            if (progressSection) {
                                progressSection.style.display = 'none';
                            }
                        }, 5000);
                    }, 2000);
                } else if (data.error) {
                    // Si hay error, mostrar pero seguir monitoreando por si se recupera
                    console.error('Error en backup automático:', data.error_message);
                }
            }
        })
        .catch(error => {
            console.error('Error obteniendo progreso del backup automático:', error);
            // Continuar monitoreando incluso si hay errores temporales
        });
    }, 1000);
}

function updateAutoBackupProgress(percent, log) {
    // Actualizar barra de progreso
    const progressBar = document.getElementById('autoBackupProgressBar');
    const progressText = document.getElementById('autoBackupProgressText');
    
    if (progressBar) {
        const safePercent = Math.max(0, Math.min(100, percent));
        progressBar.style.width = safePercent + '%';
        progressBar.textContent = safePercent + '%';
    }
    
    if (progressText) {
        const safePercent = Math.max(0, Math.min(100, percent));
        progressText.textContent = 'Progreso: ' + safePercent + '%';
    }
    
    // Actualizar log solo si hay contenido nuevo
    if (log !== null && log !== undefined && log.length > 0) {
        const backupLogElement = document.getElementById('autoBackupLog');
        if (backupLogElement) {
            // Solo actualizar si el contenido cambió o es más largo
            if (log !== autoBackupLastLogContent && log.length > autoBackupLastLogLength) {
                backupLogElement.textContent = log;
                autoBackupLastLogContent = log;
                autoBackupLastLogLength = log.length;
                
                // Auto-scroll al final
                backupLogElement.scrollTop = backupLogElement.scrollHeight;
            } else if (log !== autoBackupLastLogContent) {
                // Si el contenido cambió pero es más corto, actualizar igual
                backupLogElement.textContent = log;
                autoBackupLastLogContent = log;
                autoBackupLastLogLength = log.length;
                backupLogElement.scrollTop = backupLogElement.scrollHeight;
            }
        }
    }
}

// Variable global para rastrear si hay un backup manual en curso
var manualBackupInProgress = false;

// Verificar estado de ejecución del backup automático
var isRunningStatusCheck = false;
function checkAutoBackupRunningStatus() {
    if (isRunningStatusCheck) return Promise.resolve(); // Evitar múltiples peticiones simultáneas
    
    // SI HAY BACKUP MANUAL EN PROGRESO, NO VERIFICAR EL AUTOMÁTICO
    if (manualBackupInProgress) {
        console.log("Auto Backup UI: Hay backup manual en progreso - NO verificando estado automático");
        // Asegurar que el backup automático NO se muestre como ejecutándose
        updateRunningIndicator(false);
        toggleAutoBackupFormFields(true); // Habilitar campos (el backup automático no está bloqueado por manual)
        return Promise.resolve();
    }
    
    isRunningStatusCheck = true;
    
    return fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/get_auto_backup_status.php", 1); ?>?t=' + Date.now())
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (data.success && data.is_running !== undefined) {
                // Actualizar configuración global
                if (data.config) {
                    autoBackupConfig = data.config;
                }
                
                // SI HAY BACKUP MANUAL EN PROGRESO, FORZAR is_running = false
                if (manualBackupInProgress) {
                    data.is_running = false;
                }
                
                // Actualizar indicador visual de ejecución
                updateRunningIndicator(data.is_running);
                
                // Desactivar/habilitar campos del formulario según el estado de ejecución
                toggleAutoBackupFormFields(!data.is_running);
                
                // Si está ejecutándose, actualizar el texto del temporizador y verificar frecuentemente
                if (data.is_running) {
                    updateLastBackupDisplay(data.config ? data.config.last_backup : null);
                    
                    // SIEMPRE mostrar la sección de progreso cuando está ejecutándose
                    var progressSection = document.getElementById('autoBackupProgressSection');
                    if (progressSection) {
                        progressSection.style.display = 'block';
                    }
                    
                    // Si tenemos backup_id, iniciar monitoreo de progreso
                    if (data.backup_id) {
                        startAutoBackupProgressMonitoring(data.backup_id);
                    } else {
                        // Si no hay backup_id aún, mostrar mensaje de espera
                        var progressText = document.getElementById('autoBackupProgressText');
                        var progressBar = document.getElementById('autoBackupProgressBar');
                        var backupLog = document.getElementById('autoBackupLog');
                        if (progressText) {
                            progressText.textContent = 'Esperando inicio del backup...';
                        }
                        if (progressBar) {
                            progressBar.style.width = '0%';
                            progressBar.textContent = '0%';
                        }
                        if (backupLog) {
                            backupLog.textContent = 'El backup automático se está iniciando... Esperando progreso...';
                        }
                    }
                    
                    // Actualizar el texto del temporizador a "Ejecutándose"
                    var timerEl = document.getElementById('autoBackupTimer');
                    var statusNextEl = document.getElementById('statusNextBackup');
                    
                    if (timerEl) {
                        timerEl.textContent = 'Ejecutándose...';
                        timerEl.style.display = 'inline-block';
                        timerEl.style.fontSize = '12px';
                        timerEl.style.fontFamily = "'Arial', sans-serif";
                        timerEl.style.fontWeight = '700';
                        timerEl.style.color = '#212529';
                        timerEl.style.background = 'linear-gradient(135deg, #ffc107 0%, #ffb300 100%)';
                        timerEl.style.borderColor = '#ffc107';
                        timerEl.style.padding = '4px 10px';
                        timerEl.style.borderRadius = '5px';
                        timerEl.style.boxShadow = '0 2px 3px rgba(255,193,7,0.25)';
                        timerEl.style.letterSpacing = '0.3px';
                        timerEl.style.animation = 'pulse 2s infinite';
                        var timerLabelEl = document.getElementById('autoBackupTimerLabel');
                        if (timerLabelEl) {
                            timerLabelEl.textContent = 'Backup en ejecución';
                            timerLabelEl.style.display = 'block';
                            timerLabelEl.style.color = '#ff9800';
                        }
                    }
                    
                    if (statusNextEl) {
                        statusNextEl.innerHTML = '<span style="color: #ffc107; font-weight: bold;"><i class="fas fa-spinner fa-spin"></i> Ejecutándose...</span>';
                    }
                    
                    // Actualizar contador del modal con animación llamativa
                    var statusTimerEl = document.getElementById('statusTimerCountdown');
                    var statusTimerLabel = document.getElementById('statusTimerLabel');
                    var statusTimerContainer = document.getElementById('statusNextBackupTimer');
                    if (statusTimerEl) {
                        statusTimerEl.textContent = '';
                        statusTimerEl.style.color = '#212529';
                        statusTimerEl.style.background = 'linear-gradient(135deg, #ffc107 0%, #ffb300 100%)';
                        statusTimerEl.style.borderColor = '#ffc107';
                        statusTimerEl.style.padding = '12px 20px';
                        statusTimerEl.style.borderRadius = '8px';
                        statusTimerEl.style.boxShadow = '0 4px 12px rgba(255,193,7,0.5)';
                        statusTimerEl.style.fontWeight = '700';
                        statusTimerEl.style.fontSize = '28px';
                        statusTimerEl.style.letterSpacing = '2px';
                        statusTimerEl.style.animation = 'pulse 1.5s infinite, glow 2s infinite';
                        statusTimerEl.style.display = 'inline-block';
                        statusTimerEl.style.marginBottom = '8px';
                        statusTimerEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    }
                    if (statusTimerLabel) {
                        statusTimerLabel.textContent = 'Backup en ejecución';
                        statusTimerLabel.style.color = '#ff9800';
                        statusTimerLabel.style.fontWeight = '700';
                        statusTimerLabel.style.fontSize = '13px';
                        statusTimerLabel.style.marginTop = '4px';
                    }
                    if (statusTimerContainer) {
                        statusTimerContainer.style.background = 'linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%)';
                        statusTimerContainer.style.borderColor = '#ffc107';
                        statusTimerContainer.style.borderWidth = '2px';
                    }
                    
                    // Programar siguiente verificación muy pronto (1.5 segundos) cuando está ejecutándose
                    setTimeout(function() {
                        if (!isRunningStatusCheck) {
                            checkAutoBackupRunningStatus();
                        }
                    }, 1500);
                } else {
                    // Si no está ejecutándose, ocultar la sección de progreso y limpiar ID
                    var progressSection = document.getElementById('autoBackupProgressSection');
                    if (progressSection) {
                        progressSection.style.display = 'none';
                    }
                    // Detener monitoreo de progreso si existe
                    if (typeof autoBackupProgressInterval !== 'undefined' && autoBackupProgressInterval) {
                        clearInterval(autoBackupProgressInterval);
                        autoBackupProgressInterval = null;
                    }
                    autoBackupCurrentId = null;
                    autoBackupLastLogLength = 0;
                    autoBackupLastLogContent = '';
                    // Limpiar intervalo de indicador si existe
                    if (typeof runningIndicatorInterval !== 'undefined' && runningIndicatorInterval) {
                        clearInterval(runningIndicatorInterval);
                        runningIndicatorInterval = null;
                    }
                    
                    // Si no está ejecutándose, verificar si hay un backup completado recientemente
                    checkAutoBackupCompletion();
                }
            }
        })
        .catch(err => {
            console.error('Error verificando estado de ejecución:', err);
        })
        .finally(function() {
            isRunningStatusCheck = false;
        });
}

// Verificar si se completó un backup automático recientemente
var isCompletionCheck = false;
var lastCompletionCheckTime = 0;
var lastCompletedBackupTime = null; // Para evitar mostrar el mismo backup múltiples veces
function checkAutoBackupCompletion() {
    // Evitar múltiples peticiones simultáneas
    if (isCompletionCheck) return;
    
    // Throttling: solo verificar cada 5 segundos máximo
    var now = Date.now();
    if (now - lastCompletionCheckTime < 5000) {
        return;
    }
    
    isCompletionCheck = true;
    lastCompletionCheckTime = now;
    
    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/check_auto_backup_completion.php", 1); ?>?t=' + Date.now())
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (data.success && data.has_completed) {
                // Evitar mostrar el mismo backup completado múltiples veces
                if (lastCompletedBackupTime === data.completed_time) {
                    return; // Ya se mostró este backup
                }
                
                lastCompletedBackupTime = data.completed_time;
                showAutoBackupSuccessMessage(data.message, data.backup_file, data.completed_time);
                
                // Actualizar el texto del temporizador a "Ejecutado correctamente"
                var timerEl = document.getElementById('autoBackupTimer');
                var statusNextEl = document.getElementById('statusNextBackup');
                
                if (timerEl) {
                    timerEl.textContent = 'Ejecutado correctamente';
                    timerEl.style.display = 'inline-block';
                    timerEl.style.fontSize = '12px';
                    timerEl.style.fontFamily = "'Arial', sans-serif";
                    timerEl.style.fontWeight = '700';
                    timerEl.style.color = '#fff';
                    timerEl.style.background = 'linear-gradient(135deg, #28a745 0%, #218838 100%)';
                    timerEl.style.borderColor = '#28a745';
                    timerEl.style.padding = '4px 10px';
                    timerEl.style.borderRadius = '5px';
                    timerEl.style.boxShadow = '0 2px 3px rgba(40,167,69,0.25)';
                    timerEl.style.letterSpacing = '0.3px';
                    var timerLabelEl = document.getElementById('autoBackupTimerLabel');
                    if (timerLabelEl) {
                        timerLabelEl.textContent = 'Backup completado exitosamente';
                        timerLabelEl.style.display = 'block';
                        timerLabelEl.style.color = '#28a745';
                    }
                    
                    // Volver al temporizador normal después de 10 segundos
                    setTimeout(function() {
                        updateNextBackupTimer();
                        // Resetear la bandera después de 10 segundos para permitir detectar nuevos backups
                        setTimeout(function() {
                            lastCompletedBackupTime = null;
                        }, 5000);
                    }, 10000);
                }
                
                if (statusNextEl) {
                    statusNextEl.innerHTML = '<span style="color: #28a745; font-weight: bold;"><i class="fas fa-check-circle"></i> Ejecutado correctamente</span>';
                }
                
                // Actualizar contador del modal cuando se completa
                var statusTimerEl = document.getElementById('statusTimerCountdown');
                var statusTimerLabel = document.getElementById('statusTimerLabel');
                var statusTimerContainer = document.getElementById('statusNextBackupTimer');
                if (statusTimerEl) {
                    statusTimerEl.textContent = 'COMPLETADO';
                    statusTimerEl.style.color = '#fff';
                    statusTimerEl.style.background = 'linear-gradient(135deg, #28a745 0%, #218838 100%)';
                    statusTimerEl.style.borderColor = '#28a745';
                    statusTimerEl.style.padding = '12px 20px';
                    statusTimerEl.style.borderRadius = '8px';
                    statusTimerEl.style.boxShadow = '0 4px 12px rgba(40,167,69,0.4)';
                    statusTimerEl.style.fontWeight = '700';
                    statusTimerEl.style.fontSize = '22px';
                    statusTimerEl.style.letterSpacing = '1px';
                    statusTimerEl.style.animation = 'none';
                    statusTimerEl.style.display = 'inline-block';
                    statusTimerEl.style.marginBottom = '8px';
                }
                if (statusTimerLabel) {
                    statusTimerLabel.textContent = 'Backup completado exitosamente';
                    statusTimerLabel.style.color = '#28a745';
                    statusTimerLabel.style.fontWeight = '700';
                    statusTimerLabel.style.fontSize = '13px';
                }
                if (statusTimerContainer) {
                    statusTimerContainer.style.background = 'linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%)';
                    statusTimerContainer.style.borderColor = '#28a745';
                    statusTimerContainer.style.borderWidth = '2px';
                }
                
                // Cargar estado actualizado para mostrar última copia (sin crear nueva petición fetch)
                if (autoBackupConfig) {
                    autoBackupConfig.last_backup = data.completed_time;
                    updateLastBackupDisplay(data.completed_time);
                }
            }
        })
        .catch(err => {
            // Solo loguear errores críticos, ignorar errores de red temporales
            if (!err.message.includes('Failed to fetch') && !err.message.includes('ERR_INSUFFICIENT_RESOURCES')) {
                console.error('Error verificando completado de backup:', err);
            }
        })
        .finally(function() {
            isCompletionCheck = false;
        });
}

// Mostrar mensaje de éxito de backup automático
var autoBackupSuccessShown = false;
var autoBackupSuccessTime = null;

function showAutoBackupSuccessMessage(message, backupFile, completedTime) {
    // Evitar mostrar el mensaje múltiples veces para la misma ejecución
    if (autoBackupSuccessTime === completedTime && autoBackupSuccessShown) {
        return;
    }
    
    autoBackupSuccessTime = completedTime;
    
    // Actualizar el card de estado con mensaje de éxito
    var statusCard = document.getElementById('autoBackupStatus');
    if (statusCard) {
        // Eliminar mensaje anterior si existe
        var existingMsg = document.getElementById('autoBackupSuccessMessage');
        if (existingMsg) {
            existingMsg.remove();
        }
        
        // Crear mensaje de éxito
        var successMsg = document.createElement('div');
        successMsg.id = 'autoBackupSuccessMessage';
        successMsg.style.cssText = 'position: absolute; top: 5px; left: 5px; right: 5px; background: #28a745; color: white; padding: 8px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.2); animation: slideIn 0.3s ease-out;';
        successMsg.innerHTML = '<i class="fas fa-check-circle"></i> ' + message + (backupFile ? ' (' + backupFile + ')' : '');
        
        statusCard.insertBefore(successMsg, statusCard.firstChild);
        
        // Hacer que el mensaje desaparezca después de 10 segundos
        setTimeout(function() {
            if (successMsg && successMsg.parentNode) {
                successMsg.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(function() {
                    if (successMsg && successMsg.parentNode) {
                        successMsg.remove();
                    }
                    autoBackupSuccessShown = false;
                }, 300);
            }
        }, 10000);
        
        autoBackupSuccessShown = true;
        
        // También actualizar el texto de "Última Copia"
        var lastEl = document.getElementById('lastAutoBackup');
        if (lastEl && completedTime) {
            var completedDate = new Date(completedTime);
            var formattedDate = completedDate.toLocaleString('es-ES', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
            lastEl.textContent = formattedDate;
        }
    }
}

// Desactivar/habilitar campos del formulario según si está ejecutándose
function toggleAutoBackupFormFields(enabled) {
    var fields = [
        'autoBackupEnabled',
        'autoBackupFrequency',
        'autoBackupType',
        'autoBackupTime',
        'autoBackupCronEnabled',
        'autoBackupMaxBackups',
        'btnSaveConfig',
        'btnVerifyEnv',
        'btnFixEnv',
        'btnInstallCron',
        'btnUninstallCron'
    ];
    
    fields.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if (field) {
            field.disabled = !enabled;
            if (field.tagName === 'INPUT' || field.tagName === 'SELECT') {
                field.style.opacity = enabled ? '1' : '0.6';
                field.style.cursor = enabled ? 'default' : 'not-allowed';
            }
            if (field.tagName === 'BUTTON') {
                field.style.opacity = enabled ? '1' : '0.6';
                field.style.cursor = enabled ? 'pointer' : 'not-allowed';
            }
        }
    });
    
    // Mostrar mensaje informativo cuando está desactivado
    var formContainer = document.getElementById('autoBackupForm');
    if (formContainer) {
        var existingMessage = formContainer.querySelector('.form-disabled-message');
        if (!enabled) {
            if (!existingMessage) {
                var disabledMsg = document.createElement('div');
                disabledMsg.className = 'form-disabled-message';
                disabledMsg.style.cssText = 'background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 12px; margin-bottom: 15px; color: #856404; font-size: 14px; font-weight: 500;';
                disabledMsg.innerHTML = '<i class="fas fa-info-circle" style="margin-right: 8px;"></i> Los campos de configuración están desactivados mientras el backup está ejecutándose.';
                formContainer.insertBefore(disabledMsg, formContainer.firstChild);
            }
        } else {
            if (existingMessage) {
                existingMessage.remove();
            }
        }
    }
}

// Actualizar indicador visual de ejecución
var runningIndicatorInterval = null;
function updateRunningIndicator(isRunning) {
    // Actualizar card principal con color de fondo y mostrar indicador superior derecho
    var statusCard = document.getElementById('autoBackupStatus');
    if (statusCard) {
        // Eliminar cualquier badge existente del medio
        var existingBadge = statusCard.querySelector('#autoBackupRunningBadge');
        if (existingBadge) {
            existingBadge.remove();
        }
        
        if (isRunning) {
            statusCard.style.background = '#fff3cd';
            statusCard.style.borderLeftColor = '#ffc107';
            statusCard.style.borderLeftWidth = '4px';
            
            // Verificar si ya existe el indicador
            var existingIndicator = document.getElementById('autoBackupRunningIndicator');
            if (!existingIndicator) {
                // Agregar indicador en la esquina superior derecha (el de la derecha)
                var runningIndicator = document.createElement('div');
                runningIndicator.id = 'autoBackupRunningIndicator';
                runningIndicator.style.cssText = 'position: absolute; top: 5px; right: 5px; background: #ffc107; color: #212529; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; animation: pulse 1.5s infinite; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.2);';
                runningIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                if (statusCard && runningIndicator) {
                statusCard.appendChild(runningIndicator);
                }
            }
            
            // Actualizar el indicador periódicamente para que se vea dinámico
            if (runningIndicatorInterval) {
                clearInterval(runningIndicatorInterval);
            }
            runningIndicatorInterval = setInterval(function() {
                var indicator = document.getElementById('autoBackupRunningIndicator');
                if (indicator && isRunning) {
                    // Hacer que el indicador parpadee ligeramente para indicar actividad
                    indicator.style.opacity = indicator.style.opacity === '0.7' ? '1' : '0.7';
                } else {
                    clearInterval(runningIndicatorInterval);
                    runningIndicatorInterval = null;
                }
            }, 1000);
        } else {
            // Limpiar intervalo si existe
            if (runningIndicatorInterval) {
                clearInterval(runningIndicatorInterval);
                runningIndicatorInterval = null;
            }
            
            // Eliminar indicador si existe
            var runningIndicator = document.getElementById('autoBackupRunningIndicator');
            if (runningIndicator) {
                runningIndicator.remove();
            }
            
            if (autoBackupConfig && autoBackupConfig.enabled) {
                statusCard.style.background = '#d4edda';
                statusCard.style.borderLeftColor = '#28a745';
                statusCard.style.borderLeftWidth = '4px';
            } else {
                statusCard.style.background = '#f8d7da';
                statusCard.style.borderLeftColor = '#dc3545';
                statusCard.style.borderLeftWidth = '4px';
            }
        }
    }
}

// Actualizar display de última copia
function updateLastBackupDisplay(lastBackup) {
    var lastEl = document.getElementById('lastAutoBackup');
    var statusLastEl = document.getElementById('statusLastBackup');
    
    if (lastBackup) {
        var lastDate = new Date(lastBackup);
        var formattedDate = lastDate.toLocaleString('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        if (lastEl) {
            lastEl.textContent = formattedDate;
        }
        if (statusLastEl) {
            statusLastEl.textContent = formattedDate;
        }
    } else {
        if (lastEl) {
            lastEl.textContent = 'Nunca';
        }
        if (statusLastEl) {
            statusLastEl.textContent = 'Nunca';
        }
    }
}

// ========== FUNCIONES DEL SIMULADOR ==========
// Función para actualizar información del simulador
function refreshSimulatorInfo() {
    const systemInfoUrl = '<?php echo dol_buildpath("/custom/filemanager/scripts/get_system_info.php", 1); ?>?t=' + Date.now();
    
    fetch(systemInfoUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateMemoryBars(data);
                updateDolibarrInfo(data.dolibarr);
                updateMusicalPrincesaInfo(data.simulator);
            }
        })
        .catch(error => {
            console.error('Error actualizando información:', error);
        });
}

// Función para actualizar las barras de memoria
function updateMemoryBars(data) {
    const phpMem = data.php_memory;
    const sysMem = data.system_memory;
    
    // Actualizar memoria PHP en uso
    const phpUsagePercent = Math.min(100, phpMem.usage_percent);
    const phpUsageColor = phpUsagePercent > 80 ? '#ef4444' : (phpUsagePercent > 60 ? '#f59e0b' : '#10b981');
    const phpUsageBar = document.getElementById('phpMemoryBar');
    const phpUsageText = document.getElementById('phpMemoryUsage');
    
    if (phpUsageBar) {
        phpUsageBar.style.width = phpUsagePercent + '%';
        phpUsageBar.style.background = 'linear-gradient(90deg, ' + phpUsageColor + ', ' + phpUsageColor + ')';
        phpUsageBar.innerHTML = phpUsagePercent > 10 ? '<span style="color: white; font-size: 11px; font-weight: 600;">' + phpUsagePercent.toFixed(1) + '%</span>' : '';
    }
    if (phpUsageText) {
        phpUsageText.textContent = phpMem.usage_mb + ' MB / ' + phpMem.limit_mb + ' MB (' + phpUsagePercent.toFixed(1) + '%)';
    }
    
    // Actualizar memoria PHP pico
    const phpPeakPercent = Math.min(100, phpMem.peak_percent);
    const phpPeakColor = phpPeakPercent > 80 ? '#ef4444' : (phpPeakPercent > 60 ? '#f59e0b' : '#3b82f6');
    const phpPeakBar = document.getElementById('phpMemoryPeakBar');
    const phpPeakText = document.getElementById('phpMemoryPeak');
    
    if (phpPeakBar) {
        phpPeakBar.style.width = phpPeakPercent + '%';
        phpPeakBar.style.background = 'linear-gradient(90deg, ' + phpPeakColor + ', ' + phpPeakColor + ')';
        phpPeakBar.innerHTML = phpPeakPercent > 10 ? '<span style="color: white; font-size: 11px; font-weight: 600;">' + phpPeakPercent.toFixed(1) + '%</span>' : '';
    }
    if (phpPeakText) {
        phpPeakText.textContent = phpMem.peak_mb + ' MB / ' + phpMem.limit_mb + ' MB (' + phpPeakPercent.toFixed(1) + '%)';
    }
    
    // Actualizar memoria del sistema (si está disponible)
    if (sysMem.available) {
        const sysMemSection = document.getElementById('systemMemorySection');
        const sysMemBar = document.getElementById('systemMemoryBar');
        const sysMemText = document.getElementById('systemMemoryUsage');
        
        if (sysMemSection) sysMemSection.style.display = 'block';
        
        const sysMemPercent = Math.min(100, sysMem.used_percent);
        const sysMemColor = sysMemPercent > 80 ? '#ef4444' : (sysMemPercent > 60 ? '#f59e0b' : '#3b82f6');
        
        if (sysMemBar) {
            sysMemBar.style.width = sysMemPercent + '%';
            sysMemBar.style.background = 'linear-gradient(90deg, ' + sysMemColor + ', ' + sysMemColor + ')';
            sysMemBar.innerHTML = sysMemPercent > 10 ? '<span style="color: white; font-size: 11px; font-weight: 600;">' + sysMemPercent.toFixed(1) + '%</span>' : '';
        }
        if (sysMemText) {
            sysMemText.textContent = sysMem.used_gb + ' GB / ' + sysMem.total_gb + ' GB (' + sysMemPercent.toFixed(1) + '%)';
        }
    }
    
    // Actualizar timestamp
    const lastUpdate = document.getElementById('memoryLastUpdate');
    if (lastUpdate) {
        const now = new Date();
        lastUpdate.textContent = 'Actualizado: ' + now.toLocaleTimeString();
    }
}

// Función para actualizar información de Dolibarr
function updateDolibarrInfo(dolibarrInfo) {
    const keys = {
        'versión': 'version',
        'directorio_raíz': 'document_root',
        'base_de_datos': 'database',
        'entidad': 'entity',
        'idioma': 'language',
        'zona_horaria': 'timezone',
        'moneda': 'currency'
    };
    
    for (const [displayKey, dataKey] of Object.entries(keys)) {
        const element = document.querySelector('.dolibarr-info-value[data-key="' + displayKey + '"]');
        if (element && dolibarrInfo[dataKey]) {
            element.textContent = dolibarrInfo[dataKey];
        }
    }
}

// Función para actualizar información de Musical Princesa
function updateMusicalPrincesaInfo(simulatorInfo) {
    const mpInfo = simulatorInfo.musical_princesa;
    
    // Actualizar estado del simulador
    const simulatorSection = document.getElementById('musicalPrincesaSection');
    if (simulatorSection) {
        if (simulatorInfo.active) {
            simulatorSection.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            const h3 = simulatorSection.querySelector('h3');
            if (h3) h3.style.color = 'white';
        } else {
            simulatorSection.style.background = '#f3f4f6';
            const h3 = simulatorSection.querySelector('h3');
            if (h3) h3.style.color = '#1f2937';
        }
    }
    
    // Actualizar módulos activos
    if (mpInfo.modulos_activos && mpInfo.modulos_activos.length > 0) {
        const modulesDiv = document.getElementById('musicalPrincesaModules');
        if (modulesDiv) {
            modulesDiv.innerHTML = '';
            mpInfo.modulos_activos.forEach(function(module) {
                const span = document.createElement('span');
                span.style.cssText = 'background: rgba(255,255,255,0.2); color: white; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;';
                span.textContent = module;
                modulesDiv.appendChild(span);
            });
        }
    }
    
    // Actualizar otros valores
    const keys = {
        'estado': 'estado',
        'entorno': 'entorno',
        'ruta_configurada': 'ruta_configurada',
        'company_name': 'company_name',
        'company_email': 'company_email',
        'default_language': 'default_language'
    };
    
    for (const [dataKey, value] of Object.entries(keys)) {
        const element = document.querySelector('.musical-princesa-value[data-key="' + dataKey + '"]');
        if (element && mpInfo[value]) {
            element.textContent = mpInfo[value];
        }
    }
}

// Auto-actualizar información del simulador cada 3 segundos si está en la pestaña
var simulatorAutoRefresh = null;
function startSimulatorAutoRefresh() {
    if (simulatorAutoRefresh) {
        clearInterval(simulatorAutoRefresh);
    }
    refreshSimulatorInfo(); // Actualizar inmediatamente
    simulatorAutoRefresh = setInterval(function() {
        const simulatorContent = document.getElementById('simulatorContent');
        if (simulatorContent && simulatorContent.style.display !== 'none') {
            refreshSimulatorInfo();
        }
    }, 3000); // Actualizar cada 3 segundos
}

function stopSimulatorAutoRefresh() {
    if (simulatorAutoRefresh) {
        clearInterval(simulatorAutoRefresh);
        simulatorAutoRefresh = null;
    }
}

// Función para purgar el cache del FileManager
function purgeFileManagerCache() {
    const btn = document.getElementById('purgeCacheBtn');
    const statusDiv = document.getElementById('purgeCacheStatus');

    if (!btn || !statusDiv) {
        console.error('Elementos del botón de purga de cache no encontrados');
        return;
    }

    // Deshabilitar botón y mostrar loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Purgando Cache...</span>';

    statusDiv.style.display = 'block';
    statusDiv.innerHTML = '<div style="color: #059669; font-weight: 500;"><i class="fas fa-circle-notch fa-spin"></i> Purgando cache del FileManager...</div>';

    // Hacer la petición AJAX
    fetch('<?php echo dol_buildpath('/custom/filemanager/action.php', 1); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=purge_cache&FILEMANAGER_TOKEN=<?php echo $_SESSION['newtoken']; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<div style="color: #059669; font-weight: 500;"><i class="fas fa-check-circle"></i> ' + (data.message || 'Cache purgado exitosamente') + '</div>';
            // Recargar la página después de 2 segundos para aplicar los cambios
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            statusDiv.innerHTML = '<div style="color: #dc2626; font-weight: 500;"><i class="fas fa-exclamation-triangle"></i> Error: ' + (data.message || 'Error desconocido al purgar cache') + '</div>';
            // Rehabilitar botón
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash-alt"></i><span>Purgar Cache del FileManager</span>';
        }
    })
    .catch(error => {
        console.error('Error al purgar cache:', error);
        statusDiv.innerHTML = '<div style="color: #dc2626; font-weight: 500;"><i class="fas fa-exclamation-triangle"></i> Error de conexión al purgar cache</div>';
        // Rehabilitar botón
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash-alt"></i><span>Purgar Cache del FileManager</span>';
    });
}

// ========== DESCARGA AUTOMÁTICA DE CHUNKS ==========
let chunkDownloadController = null;

function showChunkDownloader() {
    const panel = document.getElementById('chunkDownloaderPanel');
    const status = document.getElementById('chunkDownloaderStatus');

    if (panel) {
        panel.style.display = 'block';
        status.style.display = 'none';

        // Scroll al panel
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function hideChunkDownloader() {
    const panel = document.getElementById('chunkDownloaderPanel');
    if (panel) {
        // Animación de salida
        panel.style.opacity = '0';
        panel.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            panel.style.display = 'none';
        }, 300);
    }

    // Cancelar descarga si está en progreso
    if (chunkDownloadController) {
        chunkDownloadController.abort();
        chunkDownloadController = null;
    }
}

async function startChunkDownload() {
    const btn = document.getElementById('startChunkDownloadBtn');
    const status = document.getElementById('chunkDownloaderStatus');
    const statusText = document.getElementById('chunkDownloaderStatusText');
    const progress = document.getElementById('chunkDownloaderProgress');
    const stats = document.getElementById('chunkDownloaderStats');
    const log = document.getElementById('chunkDownloaderLog');

    if (!btn || !status || !statusText || !progress || !stats || !log) {
        alert('Error: Elementos de la interfaz no encontrados');
        return;
    }

    // Deshabilitar botón
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Descargando...';

    // Mostrar status y log
    status.style.display = 'block';
    log.style.display = 'block';
    log.innerHTML = '';

    // Inicializar AbortController
    chunkDownloadController = new AbortController();

    try {
        logMessage('🚀 Iniciando descarga automática de chunks...', 'info');

        // 1. Obtener información del backup
        statusText.textContent = 'Obteniendo información del backup...';
        logMessage('📊 Consultando información del backup...', 'info');

        const infoResponse = await fetch('<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/descargar_backup.php'; ?>?action=info&t=' + Date.now(), {
            signal: chunkDownloadController.signal
        });

        if (!infoResponse.ok) {
            throw new Error(`HTTP ${infoResponse.status}: ${infoResponse.statusText}`);
        }

        const info = await infoResponse.json();

        if (!info.chunks || info.chunks.length === 0) {
            throw new Error('No se encontraron chunks para descargar. ¿El backup está completo?');
        }

        logMessage(`✅ Backup encontrado: ${info.total_chunks} chunks, ${info.total_tamano_mb} MB total`, 'success');
        logMessage(`⏱️ Tiempo estimado: ~${Math.ceil(info.total_chunks * 4 / 60)} minutos`, 'info');

        let descargados = 0;
        let fallidos = [];
        const totalChunks = info.chunks.length;

        // 2. Descargar chunks uno por uno
        for (let i = 0; i < totalChunks; i++) {
            const chunk = info.chunks[i];

            if (chunkDownloadController.signal.aborted) {
                logMessage('🛑 Descarga cancelada por el usuario', 'warning');
                break;
            }

            statusText.textContent = `Descargando chunk ${i + 1}/${totalChunks}...`;
            logMessage(`📦 Descargando chunk ${chunk.numero}/${totalChunks}: ${chunk.archivo} (${chunk.tamano_mb} MB)`, 'info');

            try {
                const chunkResponse = await fetch(`<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/descargar_backup.php'; ?>?action=descargar&chunk=${chunk.numero}&t=${Date.now()}`, {
                    signal: chunkDownloadController.signal
                });

                if (!chunkResponse.ok) {
                    throw new Error(`HTTP ${chunkResponse.status}: ${chunkResponse.statusText}`);
                }

                const blob = await chunkResponse.blob();

                // Crear descarga automática
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = chunk.archivo;
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                descargados++;
                logMessage(`✅ ${chunk.archivo} descargado exitosamente`, 'success');

                // Actualizar progreso
                const progressPercent = ((i + 1) / totalChunks) * 100;
                progress.style.width = progressPercent + '%';

                // Actualizar estadísticas
                stats.innerHTML = `Chunks descargados: ${descargados}/${totalChunks} | Fallidos: ${fallidos.length} | Tamaño total: ${info.total_tamano_mb} MB`;

            } catch (error) {
                logMessage(`❌ Error descargando ${chunk.archivo}: ${error.message}`, 'error');
                fallidos.push(chunk.numero);
            }

            // Delay entre descargas (CRÍTICO para no matar el servidor)
            if (i < totalChunks - 1 && !chunkDownloadController.signal.aborted) {
                statusText.textContent = `Esperando 3 segundos... (${i + 1}/${totalChunks} completado)`;
                logMessage('⏳ Esperando 3 segundos antes del siguiente chunk...', 'info');
                await new Promise(resolve => setTimeout(resolve, 3000));
            }
        }

        // 3. Resultados finales
        if (descargados === totalChunks && fallidos.length === 0) {
            statusText.textContent = '¡Descarga completada exitosamente!';
            logMessage('\n🎉 ¡DESCARGA COMPLETA EXITOSA!', 'success');
            logMessage(`📁 Todos los ${totalChunks} chunks se descargaron en tu carpeta de Descargas`, 'success');
            logMessage('\n🔧 Para combinar los chunks:', 'info');
            logMessage('   Windows: copy /b chunk_*.zip backup_completo.zip', 'info');
            logMessage('   Linux/Mac: cat chunk_*.zip > backup_completo.zip', 'info');

            // Mostrar mensaje de éxito
            setTimeout(() => {
                alert(`¡Descarga completada!\n\n${descargados} chunks descargados exitosamente.\n\nRevisa tu carpeta de Descargas y combina los archivos usando los comandos mostrados en el log.`);
            }, 1000);

        } else {
            statusText.textContent = `Descarga completada con errores: ${fallidos.length} fallaron`;
            logMessage(`\n❌ DESCARGA COMPLETADA CON ERRORES: ${fallidos.length} chunks fallaron`, 'error');
            if (fallidos.length > 0) {
                logMessage(`Chunks que fallaron: ${fallidos.join(', ')}`, 'error');
                logMessage('💡 Vuelve a ejecutar la descarga para obtener los chunks faltantes', 'info');
            }
        }

    } catch (error) {
        if (error.name === 'AbortError') {
            statusText.textContent = 'Descarga cancelada';
            logMessage('🛑 Descarga cancelada por el usuario', 'warning');
        } else {
            statusText.textContent = 'Error durante la descarga';
            logMessage(`💥 ERROR: ${error.message}`, 'error');
            console.error('Error en descarga de chunks:', error);
        }
    }

    // Limpiar
    chunkDownloadController = null;
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-rocket"></i> Iniciar Descarga Automática';
}

function logMessage(message, type = 'info') {
    const log = document.getElementById('chunkDownloaderLog');
    if (log) {
        const timestamp = new Date().toLocaleTimeString();
        const colorClass = type === 'error' ? 'color: #dc3545;' :
                          type === 'success' ? 'color: #28a745;' :
                          type === 'warning' ? 'color: #ffc107;' : 'color: #666;';

        log.innerHTML += `<div style="${colorClass}">[${timestamp}] ${message}</div>`;
        log.scrollTop = log.scrollHeight;
    }
}

// ========== FIN DESCARGA AUTOMÁTICA DE CHUNKS ==========

// ========== DIÁLOGO DE LIMPIEZA DE CHUNKS ==========
function showChunkCleanupDialog(backupId, totalChunks) {
    // Crear overlay oscuro
    const overlay = document.createElement('div');
    overlay.id = 'chunkCleanupOverlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;

    // Crear diálogo
    const dialog = document.createElement('div');
    dialog.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        max-width: 500px;
        width: 90%;
        text-align: center;
    `;

    dialog.innerHTML = `
        <h3 style="margin: 0 0 20px 0; color: #2c3e50; font-size: 24px;">
            <i class="fas fa-question-circle" style="color: #ff9800;"></i><br>
            ¿Qué hacer con los chunks?
        </h3>

        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; text-align: left;">
            <p style="margin: 0 0 10px 0; font-weight: 600; color: #495057;">
                <i class="fas fa-info-circle"></i> Backup completado exitosamente
            </p>
            <p style="margin: 0; color: #6c757d; line-height: 1.5;">
                Se crearon <strong>${totalChunks} chunks</strong> que contienen todos tus archivos.
                Cada chunk es un archivo ZIP válido e independiente.
            </p>
        </div>

        <div style="margin-bottom: 25px;">
            <button onclick="keepChunks('${backupId}')" style="
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                color: white;
                border: none;
                padding: 12px 25px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                margin: 0 10px 10px 0;
                box-shadow: 0 4px 12px rgba(40,167,69,0.3);
                transition: all 0.3s;
            " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-save"></i> Mantener Chunks
            </button>

            <button onclick="deleteChunks('${backupId}')" style="
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                color: white;
                border: none;
                padding: 12px 25px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                margin: 0 10px 10px 0;
                box-shadow: 0 4px 12px rgba(220,53,69,0.3);
                transition: all 0.3s;
            " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-trash"></i> Eliminar Chunks
            </button>
        </div>

        <div style="font-size: 12px; color: #6c757d; border-top: 1px solid #dee2e6; padding-top: 15px;">
            <p style="margin: 0;"><strong>Mantener chunks:</strong> Podrás descargarlos individualmente más tarde desde "Chunks disponibles"</p>
            <p style="margin: 5px 0 0 0;"><strong>Eliminar chunks:</strong> Libera espacio en disco, pero deberás crear nuevos chunks si los necesitas</p>
        </div>
    `;

    overlay.appendChild(dialog);
    document.body.appendChild(overlay);

    // Agregar log
    addLog('❓ Esperando decisión sobre chunks...');
}

function keepChunks(backupId) {
    addLog('✅ Decisión: Mantener chunks para descargas futuras');
    addLog('📁 Los chunks estarán disponibles en "Chunks disponibles"');
    closeChunkCleanupDialog();
    finalizeBackupAfterChunkDecision();
}

function deleteChunks(backupId) {
    addLog('🗑️ Decisión: Eliminar chunks temporales');

    // Mostrar loading
    const overlay = document.getElementById('chunkCleanupOverlay');
    if (overlay) {
        overlay.innerHTML = `
            <div style="background: white; padding: 30px; border-radius: 12px; text-align: center;">
                <div style="font-size: 48px; color: #dc3545; margin-bottom: 20px;">
                    <i class="fas fa-trash-alt fa-spin"></i>
                </div>
                <h4>Eliminando chunks...</h4>
                <p style="color: #6c757d; margin: 10px 0 0 0;">Liberando espacio en disco</p>
            </div>
        `;
    }

    // Hacer petición para eliminar chunks
    fetch('<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/cleanup_chunks.php'; ?>?backup_id=' + backupId + '&action=delete&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addLog('✅ Chunks eliminados exitosamente');
                addLog('📊 Espacio liberado: ' + data.space_freed_mb + ' MB');
            } else {
                addLog('⚠️ Error al eliminar chunks: ' + (data.error || 'Error desconocido'));
            }

            closeChunkCleanupDialog();
            finalizeBackupAfterChunkDecision();
        })
        .catch(error => {
            addLog('❌ Error de conexión al eliminar chunks');
            console.error('Error deleting chunks:', error);
            closeChunkCleanupDialog();
            finalizeBackupAfterChunkDecision();
        });
}

function closeChunkCleanupDialog() {
    const overlay = document.getElementById('chunkCleanupOverlay');
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(overlay);
        }, 300);
    }
}

function finalizeBackupAfterChunkDecision() {
    addLog('⏱️ La página se recargará en 3 segundos...');
    setTimeout(() => {
        window.location.reload();
    }, 3000);
}

// ========== FIN DIÁLOGO DE LIMPIEZA DE CHUNKS ==========

// ========== FILTROS Y GESTIÓN DE CHUNKS ==========
let allBackupItems = [];
let currentFilter = 'all';

function filterBackups(filterType) {
    currentFilter = filterType;
    const rows = document.querySelectorAll('#backupTableBody tr');

    rows.forEach(row => {
        const isChunk = row.classList.contains('chunk-row');
        const isBackup = row.classList.contains('backup-row');

        switch (filterType) {
            case 'all':
                row.style.display = '';
                break;
            case 'backups':
                row.style.display = isBackup ? '' : 'none';
                break;
            case 'chunks':
                row.style.display = isChunk ? '' : 'none';
                break;
        }
    });

    updateBackupStats();
}

function updateBackupStats() {
    const rows = document.querySelectorAll('#backupTableBody tr');
    let totalBackups = 0;
    let totalChunks = 0;
    let totalSizeBackups = 0;
    let totalSizeChunks = 0;

    rows.forEach(row => {
        if (row.style.display !== 'none') {
            if (row.classList.contains('backup-row')) {
                totalBackups++;
                // Verificar que la fila tenga suficientes celdas antes de acceder
                if (row.cells && row.cells.length > 2 && row.cells[2]) {
                    const sizeText = row.cells[2].textContent || '';
                    const sizeMB = parseFloat(sizeText.replace(' MB', ''));
                    if (!isNaN(sizeMB)) totalSizeBackups += sizeMB;
                }
            } else if (row.classList.contains('chunk-row')) {
                totalChunks++;
                // Verificar que la fila tenga suficientes celdas antes de acceder
                if (row.cells && row.cells.length > 2 && row.cells[2]) {
                    const sizeText = row.cells[2].textContent || '';
                    const sizeMB = parseFloat(sizeText.replace(' MB', ''));
                    if (!isNaN(sizeMB)) totalSizeChunks += sizeMB;
                }
            }
        }
    });

    const statsEl = document.getElementById('backupStats');
    if (statsEl) {
        let statsText = '';
        if (currentFilter === 'all' || currentFilter === 'backups') {
            statsText += `${totalBackups} backups (${totalSizeBackups.toFixed(1)} MB)`;
        }
        if (currentFilter === 'all') {
            statsText += ' • ';
        }
        if (currentFilter === 'all' || currentFilter === 'chunks') {
            statsText += `${totalChunks} chunks (${totalSizeChunks.toFixed(1)} MB)`;
        }
        statsEl.textContent = statsText;
    }
}

function loadAvailableChunks() {
    fetch('<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/cleanup_chunks.php'; ?>?action=list&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success && data.chunks.length > 0) {
                addChunksToTable(data.chunks);
            }
            // Actualizar estadísticas después de cargar chunks
            updateBackupStats();
        })
        .catch(error => {
            console.error('Error cargando chunks:', error);
            // Actualizar estadísticas incluso si hay error
            updateBackupStats();
        });
}

function addChunksToTable(chunks) {
    const tableBody = document.getElementById('backupTableBody');
    if (!tableBody) return;

    chunks.forEach(chunk => {
        const row = document.createElement('tr');
        row.className = 'oddeven chunk-row';
        row.setAttribute('data-type', 'chunk');

        // Nombre del archivo
        const nameCell = document.createElement('td');
        nameCell.innerHTML = `<i class="fas fa-file-archive" style="color: #ff6b35;"></i> ${chunk.file_name}`;
        row.appendChild(nameCell);

        // Tipo
        const typeCell = document.createElement('td');
        typeCell.innerHTML = `<span style="color: #ff6b35;"><i class="fas fa-puzzle-piece"></i> Chunk #${chunk.chunk_number}</span>`;
        row.appendChild(typeCell);

        // Tamaño
        const sizeCell = document.createElement('td');
        sizeCell.textContent = `${chunk.size_mb} MB`;
        row.appendChild(sizeCell);

        // Fecha
        const dateCell = document.createElement('td');
        dateCell.textContent = chunk.modified_formatted;
        row.appendChild(dateCell);

        // Acciones
        const actionsCell = document.createElement('td');
        actionsCell.className = 'right';
        actionsCell.innerHTML = `
            <a class="butAction" href="<?php echo 'http://localhost/dolibarr/custom/filemanager/scripts/descargar_backup.php'; ?>?action=descargar&chunk=${chunk.chunk_number}&t=${Date.now()}"
               title="Descargar este chunk" target="_blank">
                <i class="fas fa-download"></i> Descargar
            </a>
            <a class="butActionDelete" href="javascript:void(0)"
               onclick="deleteSingleChunk('${chunk.backup_id}', ${chunk.chunk_number}, '${chunk.file_name}')"
               title="Eliminar este chunk">
                <i class="fas fa-trash"></i> Eliminar
            </a>
        `;
        row.appendChild(actionsCell);

        tableBody.appendChild(row);
    });

    // Actualizar estadísticas
    updateBackupStats();
}

function deleteSingleChunk(backupId, chunkNumber, fileName) {
    if (!confirm(`¿Estás seguro de eliminar el chunk "${fileName}"?`)) {
        return;
    }

    // Por ahora, redirigir a la funcionalidad general de cleanup
    // En el futuro se puede implementar eliminación individual
    alert('Función de eliminación individual próximamente. Usa "Eliminar Chunks" en el diálogo después del backup.');
}

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    loadAvailableChunks(); // updateBackupStats se llama dentro de loadAvailableChunks después de cargar
});

// ========== FIN FILTROS Y GESTIÓN DE CHUNKS ==========

</script>

<?php
llxFooter();
$db->close();