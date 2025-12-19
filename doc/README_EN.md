# FileManager Pro for Dolibarr

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![Dolibarr](https://img.shields.io/badge/Dolibarr-15.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPLv3-orange.svg)

## 📋 Description

**FileManager Pro** is an advanced file management module for Dolibarr ERP/CRM that allows you to manage all files and folders of your installation visually and intuitively, in addition to performing complete system backups.

### ✨ Main Features

#### 🗂️ File Management
- **Visual file explorer** with grid view
- **Intuitive navigation** with breadcrumbs and directory tree
- **File operations**: Copy, Cut, Paste, Rename, Delete
- **Drag & drop** file upload
- **Preview** of images, videos, audio and documents
- **Download** individual or multiple files
- **Quick search** for files and folders
- **Recycle bin** with file restoration

#### 💾 Backup System
- **Database Backup**: Export all SQL tables in compressed format
- **Files Backup**: Compress all installation files
- **Complete Backup**: Database + Files in one ZIP
- **Automatic Backups**: Daily, weekly or monthly scheduling
- **Real-time progress** with detailed logs
- **Direct download** of generated backups
- **Backup history**

#### 🔒 Security
- User permission control
- System directory protection
- File type validation
- Detailed activity logs

#### 📱 Responsive Design
- Adaptable interface for any device
- Modern and professional design
- Compatible with tablets and mobiles

## 📸 Screenshots

### Main Panel
![Main Panel](screenshots/main-panel.png)

### Backup System
![Backups](screenshots/backup-system.png)

### Mobile View
![Mobile](screenshots/mobile-view.png)

## 🔧 Requirements

| Requirement | Minimum Version |
|-------------|-----------------|
| Dolibarr | 15.0+ |
| PHP | 7.4+ |
| MySQL/MariaDB | 5.7+ / 10.2+ |
| ZIP Extension | Required |
| Disk space | 500MB+ recommended |

## 📥 Installation

### Method 1: From DoliStore (Recommended)
1. Download the module from DoliStore
2. Extract the file to `/htdocs/custom/`
3. Activate the module in **Home → Setup → Modules**
4. Configure user permissions

### Method 2: Manual
1. Download the module ZIP file
2. Extract contents to `dolibarr/htdocs/custom/filemanager/`
3. Make sure folder permissions are correct (755 for folders, 644 for files)
4. Go to Dolibarr → Setup → Modules
5. Find "FileManager" and activate it

## ⚙️ Configuration

1. Go to **Tools → FileManager → Settings**
2. Configure the file explorer root path
3. Adjust protected folders if needed
4. Configure automatic backups (optional)

### Automatic Backup Configuration

To enable automatic backups, you need to configure a cron job:

```bash
# Run every day at 2:00 AM
0 2 * * * php /var/www/dolibarr/htdocs/custom/filemanager/scripts/auto_backup_cron.php
```

## 📖 Usage

### File Explorer
1. Go to **Tools → FileManager**
2. Navigate through folders using breadcrumbs or clicking on folders
3. Use action buttons to copy, move, rename or delete files
4. Drag files to upload them

### Performing a Backup
1. Go to **Settings → Backups**
2. Select the backup type:
   - **Database**: SQL tables only
   - **Files**: System files only
   - **Complete**: Both in one ZIP
3. Click on the corresponding card
4. Wait for analysis to complete
5. Confirm to start the backup
6. Download the file when finished

## 🌐 Supported Languages

- 🇪🇸 Spanish (es_ES) - Complete
- 🇬🇧 English (en_US) - Complete
- 🇫🇷 French (fr_FR) - Complete
- 🇩🇪 German (de_DE) - Complete

## 🆘 Support

- **Email**: support@yourdomain.com
- **Documentation**: [Module Wiki](https://github.com/your-user/filemanager-dolibarr/wiki)
- **Issues**: [Report problems](https://github.com/your-user/filemanager-dolibarr/issues)

## 📄 License

This module is licensed under **GNU General Public License v3.0 (GPLv3)**.

See [LICENSE](../LICENSE) file for more details.

## 👨‍💻 Author

**Your Name or Company**
- Website: [yourdomain.com](https://yourdomain.com)
- Email: contact@yourdomain.com

---

© 2024 Your Name or Company. All rights reserved.



