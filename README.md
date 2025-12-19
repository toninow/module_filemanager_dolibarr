# FileManager Pro para Dolibarr

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![Dolibarr](https://img.shields.io/badge/Dolibarr-15.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPLv3-orange.svg)

## 📋 Descripción

**FileManager Pro** es un módulo avanzado de gestión de archivos para Dolibarr ERP/CRM que permite administrar todos los archivos y carpetas de tu instalación de forma visual e intuitiva, además de realizar copias de seguridad completas del sistema.

### ✨ Características Principales

#### 🗂️ Gestión de Archivos
- **Explorador visual** de archivos y carpetas con vista de cuadrícula
- **Navegación intuitiva** con breadcrumbs y árbol de directorios
- **Operaciones de archivos**: Copiar, Cortar, Pegar, Renombrar, Eliminar
- **Subida de archivos** con arrastrar y soltar (drag & drop)
- **Previsualización** de imágenes, videos, audio y documentos
- **Descarga** individual o múltiple de archivos
- **Búsqueda** rápida de archivos y carpetas
- **Papelera de reciclaje** con restauración de archivos

#### 💾 Sistema de Backups
- **Backup de Base de Datos**: Exporta todas las tablas SQL en formato comprimido
- **Backup de Archivos**: Comprime todos los archivos de la instalación
- **Backup Completo**: Base de datos + Archivos en un solo ZIP
- **Backups Automáticos**: Programación diaria, semanal o mensual
- **Progreso en tiempo real** con logs detallados
- **Descarga directa** de backups generados
- **Historial** de copias de seguridad

#### 🔒 Seguridad
- Control de permisos por usuario
- Protección de directorios del sistema
- Validación de tipos de archivo
- Logs de actividad detallados

#### 📱 Diseño Responsive
- Interfaz adaptable a cualquier dispositivo
- Diseño moderno y profesional
- Compatible con tablets y móviles

## 📸 Capturas de Pantalla

### Panel Principal
![Panel Principal](doc/screenshots/main-panel.png)

### Sistema de Backups
![Backups](doc/screenshots/backup-system.png)

### Vista Móvil
![Mobile](doc/screenshots/mobile-view.png)

## 🔧 Requisitos

| Requisito | Versión Mínima |
|-----------|----------------|
| Dolibarr | 15.0+ |
| PHP | 7.4+ |
| MySQL/MariaDB | 5.7+ / 10.2+ |
| Extensión ZIP | Requerida |
| Espacio en disco | 500MB+ recomendado |

## 📥 Instalación

### Método 1: Desde DoliStore (Recomendado)
1. Descarga el módulo desde DoliStore
2. Descomprime el archivo en `/htdocs/custom/`
3. Activa el módulo en **Inicio → Configuración → Módulos**
4. Configura los permisos de usuario

### Método 2: Manual
1. Descarga el archivo ZIP del módulo
2. Extrae el contenido en `dolibarr/htdocs/custom/filemanager/`
3. Asegúrate de que los permisos de carpetas sean correctos (755 para carpetas, 644 para archivos)
4. Accede a Dolibarr → Configuración → Módulos
5. Busca "FileManager" y actívalo

## ⚙️ Configuración

1. Ve a **Utilidades → FileManager → Configuración**
2. Configura la ruta raíz del explorador de archivos
3. Ajusta las carpetas protegidas si es necesario
4. Configura los backups automáticos (opcional)

### Configuración de Backups Automáticos

Para habilitar backups automáticos, necesitas configurar un cron job:

```bash
# Ejecutar cada día a las 2:00 AM
0 2 * * * php /var/www/dolibarr/htdocs/custom/filemanager/scripts/auto_backup_cron.php
```

## 📖 Uso

### Explorador de Archivos
1. Accede a **Utilidades → FileManager**
2. Navega por las carpetas usando los breadcrumbs o haciendo clic en las carpetas
3. Usa los botones de acción para copiar, mover, renombrar o eliminar archivos
4. Arrastra archivos para subirlos

### Realizar un Backup
1. Ve a **Configuración → Backups**
2. Selecciona el tipo de backup:
   - **Base de Datos**: Solo tablas SQL
   - **Archivos**: Solo archivos del sistema
   - **Completo**: Ambos en un ZIP
3. Haz clic en la tarjeta correspondiente
4. Espera a que se complete el análisis
5. Confirma para iniciar el backup
6. Descarga el archivo cuando termine

## 🌐 Idiomas Soportados

- 🇪🇸 Español (es_ES) - Completo
- 🇬🇧 English (en_US) - Próximamente
- 🇫🇷 Français (fr_FR) - Próximamente

## 🆘 Soporte

- **Email**: soporte@tudominio.com
- **Documentación**: [Wiki del módulo](https://github.com/tu-usuario/filemanager-dolibarr/wiki)
- **Issues**: [Reportar problemas](https://github.com/tu-usuario/filemanager-dolibarr/issues)

## 📄 Licencia

Este módulo está licenciado bajo **GNU General Public License v3.0 (GPLv3)**.

Ver archivo [LICENSE](LICENSE) para más detalles.

## 👨‍💻 Autor

**Tu Nombre o Empresa**
- Website: [tudominio.com](https://tudominio.com)
- Email: contacto@tudominio.com

## 🙏 Agradecimientos

- Comunidad Dolibarr
- Contribuidores del proyecto

---

© 2024 Tu Nombre o Empresa. Todos los derechos reservados.



