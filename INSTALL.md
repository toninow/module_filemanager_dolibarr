# Guía de Instalación - FileManager Pro

## Requisitos Previos

Antes de instalar el módulo, asegúrese de cumplir con los siguientes requisitos:

- **Dolibarr**: Versión 15.0 o superior
- **PHP**: Versión 7.4 o superior
- **MySQL/MariaDB**: Versión 5.7+ / 10.2+
- **Extensión ZIP de PHP**: Obligatoria
- **Permisos de escritura**: En la carpeta `custom/`

### Verificar extensión ZIP

```bash
php -m | grep zip
```

Si no aparece, instálela:

```bash
# Debian/Ubuntu
sudo apt-get install php-zip

# CentOS/RHEL
sudo yum install php-zip
```

## Instalación

### Paso 1: Descarga

Descargue el archivo ZIP del módulo desde DoliStore.

### Paso 2: Extracción

Extraiga el contenido en la carpeta `custom` de su instalación de Dolibarr:

```bash
cd /var/www/dolibarr/htdocs/custom/
unzip filemanager-pro-v1.0.0.zip
```

### Paso 3: Permisos

Establezca los permisos correctos:

```bash
chmod -R 755 filemanager/
chmod -R 644 filemanager/*.php
chmod -R 755 filemanager/backups/
chmod -R 755 filemanager/logs/
chmod -R 755 filemanager/cache/
```

### Paso 4: Activación

1. Inicie sesión en Dolibarr como administrador
2. Vaya a **Inicio → Configuración → Módulos/Aplicaciones**
3. Busque "FileManager" en la lista
4. Haga clic en el interruptor para activar el módulo

### Paso 5: Configuración Inicial

1. Vaya a **Utilidades → FileManager → Configuración**
2. Configure la ruta raíz del explorador
3. Ajuste las carpetas protegidas si es necesario
4. Guarde los cambios

## Configuración de Backups Automáticos (Opcional)

Para habilitar los backups automáticos, configure un cron job:

```bash
# Editar crontab
crontab -e

# Añadir línea (ejemplo: cada día a las 2:00 AM)
0 2 * * * php /var/www/dolibarr/htdocs/custom/filemanager/scripts/auto_backup_cron.php >> /var/log/dolibarr_backup.log 2>&1
```

## Permisos de Usuario

Después de la instalación, configure los permisos:

1. Vaya a **Usuarios y Grupos**
2. Edite el usuario o grupo deseado
3. En la pestaña "Permisos", busque "FileManager"
4. Active los permisos necesarios:
   - Leer archivos
   - Escribir archivos
   - Eliminar archivos
   - Gestionar backups

## Solución de Problemas

### Error: "Extensión ZIP no encontrada"
Instale la extensión ZIP de PHP y reinicie el servidor web.

### Error: "Permiso denegado"
Verifique los permisos de las carpetas `backups/`, `logs/` y `cache/`.

### El módulo no aparece en la lista
Asegúrese de que la carpeta esté en `htdocs/custom/` y no en `htdocs/custom/filemanager/filemanager/`.

## Actualización

1. Desactive el módulo en Dolibarr
2. Haga backup de la carpeta actual
3. Reemplace los archivos con la nueva versión
4. Active el módulo nuevamente

## Desinstalación

1. Desactive el módulo en Dolibarr
2. Elimine la carpeta `custom/filemanager/`
3. (Opcional) Elimine las tablas de la base de datos con prefijo `llx_filemanager_`

## Soporte

Si tiene problemas con la instalación:
- Email: soporte@tudominio.com
- Documentación: Ver README.md
