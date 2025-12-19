# Changelog

Todos los cambios notables de este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [1.0.0] - 2024-11-30

### ✨ Añadido
- **Explorador de Archivos**
  - Vista de cuadrícula moderna para archivos y carpetas
  - Navegación con breadcrumbs
  - Operaciones: Copiar, Cortar, Pegar, Renombrar, Eliminar
  - Subida de archivos con drag & drop
  - Previsualización de imágenes, videos y audio
  - Búsqueda rápida de archivos
  - Papelera de reciclaje con restauración

- **Sistema de Backups**
  - Backup de Base de Datos (SQL comprimido)
  - Backup de Archivos (ZIP)
  - Backup Completo (BD + Archivos)
  - Backups Automáticos programados
  - Progreso en tiempo real
  - Logs detallados de cada operación
  - Historial de backups

- **Panel de Configuración**
  - Configuración de ruta raíz
  - Gestión de carpetas protegidas
  - Configuración de backups automáticos
  - Logs de actividad

- **Diseño Responsive**
  - Interfaz adaptable a móviles y tablets
  - Diseño moderno con gradientes
  - Animaciones suaves

- **Seguridad**
  - Control de permisos por usuario
  - Protección de directorios del sistema
  - Validación de tipos de archivo
  - Tokens CSRF

### 🔒 Seguridad
- Implementación de validación de tokens CSRF
- Sanitización de rutas de archivos
- Protección contra path traversal

### 📚 Documentación
- README.md completo
- Documentación de instalación
- Guía de configuración

---

## [Próximas Versiones]

### Planificado para v1.1.0
- [ ] Soporte multiidioma completo (EN, FR)
- [ ] Integración con almacenamiento en la nube
- [ ] Compresión de imágenes automática
- [ ] API REST para acceso externo

### Planificado para v1.2.0
- [ ] Editor de texto integrado
- [ ] Visor de PDFs mejorado
- [ ] Sincronización con servicios externos
- [ ] Backups incrementales

---

[1.0.0]: https://github.com/tu-usuario/filemanager-dolibarr/releases/tag/v1.0.0



