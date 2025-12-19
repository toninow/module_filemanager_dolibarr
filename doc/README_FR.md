# FileManager Pro pour Dolibarr

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![Dolibarr](https://img.shields.io/badge/Dolibarr-15.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPLv3-orange.svg)

## 📋 Description

**FileManager Pro** est un module avancé de gestion de fichiers pour Dolibarr ERP/CRM qui vous permet de gérer tous les fichiers et dossiers de votre installation de manière visuelle et intuitive, en plus d'effectuer des sauvegardes complètes du système.

### ✨ Fonctionnalités Principales

#### 🗂️ Gestion des Fichiers
- **Explorateur de fichiers visuel** avec vue en grille
- **Navigation intuitive** avec fil d'Ariane et arborescence
- **Opérations sur fichiers** : Copier, Couper, Coller, Renommer, Supprimer
- **Téléversement** par glisser-déposer
- **Prévisualisation** d'images, vidéos, audio et documents
- **Téléchargement** individuel ou multiple
- **Recherche rapide** de fichiers et dossiers
- **Corbeille** avec restauration de fichiers

#### 💾 Système de Sauvegarde
- **Sauvegarde Base de Données** : Export de toutes les tables SQL en format compressé
- **Sauvegarde Fichiers** : Compression de tous les fichiers de l'installation
- **Sauvegarde Complète** : Base de données + Fichiers dans un ZIP
- **Sauvegardes Automatiques** : Programmation quotidienne, hebdomadaire ou mensuelle
- **Progression en temps réel** avec journaux détaillés
- **Téléchargement direct** des sauvegardes générées
- **Historique des sauvegardes**

#### 🔒 Sécurité
- Contrôle des permissions par utilisateur
- Protection des répertoires système
- Validation des types de fichiers
- Journaux d'activité détaillés

#### 📱 Design Responsive
- Interface adaptable à tout appareil
- Design moderne et professionnel
- Compatible tablettes et mobiles

## 📸 Captures d'Écran

### Panneau Principal
![Panneau Principal](screenshots/main-panel.png)

### Système de Sauvegarde
![Sauvegardes](screenshots/backup-system.png)

### Vue Mobile
![Mobile](screenshots/mobile-view.png)

## 🔧 Prérequis

| Prérequis | Version Minimale |
|-----------|------------------|
| Dolibarr | 15.0+ |
| PHP | 7.4+ |
| MySQL/MariaDB | 5.7+ / 10.2+ |
| Extension ZIP | Requise |
| Espace disque | 500Mo+ recommandé |

## 📥 Installation

### Méthode 1 : Depuis DoliStore (Recommandé)
1. Téléchargez le module depuis DoliStore
2. Extrayez le fichier dans `/htdocs/custom/`
3. Activez le module dans **Accueil → Configuration → Modules**
4. Configurez les permissions utilisateur

### Méthode 2 : Manuelle
1. Téléchargez le fichier ZIP du module
2. Extrayez le contenu dans `dolibarr/htdocs/custom/filemanager/`
3. Assurez-vous que les permissions des dossiers sont correctes (755 pour les dossiers, 644 pour les fichiers)
4. Accédez à Dolibarr → Configuration → Modules
5. Recherchez "FileManager" et activez-le

## ⚙️ Configuration

1. Allez dans **Outils → FileManager → Paramètres**
2. Configurez le chemin racine de l'explorateur de fichiers
3. Ajustez les dossiers protégés si nécessaire
4. Configurez les sauvegardes automatiques (optionnel)

### Configuration des Sauvegardes Automatiques

Pour activer les sauvegardes automatiques, vous devez configurer une tâche cron :

```bash
# Exécuter chaque jour à 2h00
0 2 * * * php /var/www/dolibarr/htdocs/custom/filemanager/scripts/auto_backup_cron.php
```

## 📖 Utilisation

### Explorateur de Fichiers
1. Allez dans **Outils → FileManager**
2. Naviguez dans les dossiers en utilisant le fil d'Ariane ou en cliquant sur les dossiers
3. Utilisez les boutons d'action pour copier, déplacer, renommer ou supprimer des fichiers
4. Glissez-déposez des fichiers pour les téléverser

### Effectuer une Sauvegarde
1. Allez dans **Paramètres → Sauvegardes**
2. Sélectionnez le type de sauvegarde :
   - **Base de données** : Tables SQL uniquement
   - **Fichiers** : Fichiers système uniquement
   - **Complète** : Les deux dans un ZIP
3. Cliquez sur la carte correspondante
4. Attendez que l'analyse soit terminée
5. Confirmez pour démarrer la sauvegarde
6. Téléchargez le fichier une fois terminé

## 🌐 Langues Supportées

- 🇪🇸 Espagnol (es_ES) - Complet
- 🇬🇧 Anglais (en_US) - Complet
- 🇫🇷 Français (fr_FR) - Complet
- 🇩🇪 Allemand (de_DE) - Complet

## 🆘 Support

- **Email** : support@votredomaine.com
- **Documentation** : [Wiki du module](https://github.com/votre-utilisateur/filemanager-dolibarr/wiki)
- **Problèmes** : [Signaler des problèmes](https://github.com/votre-utilisateur/filemanager-dolibarr/issues)

## 📄 Licence

Ce module est distribué sous licence **GNU General Public License v3.0 (GPLv3)**.

Voir le fichier [LICENSE](../LICENSE) pour plus de détails.

## 👨‍💻 Auteur

**Votre Nom ou Entreprise**
- Site web : [votredomaine.com](https://votredomaine.com)
- Email : contact@votredomaine.com

---

© 2024 Votre Nom ou Entreprise. Tous droits réservés.



