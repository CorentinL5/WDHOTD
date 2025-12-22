# WDHOTD - On This Day

<div align="center">

**WDHOTD** (Wikipedia Daily History On This Day) est une application web moderne qui affiche les événements historiques, naissances, décès et fêtes qui se sont produits chaque jour dans l'histoire, en utilisant les données de l'API Wikipedia.

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

</div>

## ✨ Fonctionnalités

- 📅 **Affichage quotidien** : Découvrez les événements historiques du jour actuel
- 🎯 **Filtres intelligents** : Filtrez par type (événements, naissances, décès, fêtes)
- 🔄 **Import automatique** : Synchronisation automatique avec l'API Wikipedia
- 📱 **Design responsive** : Interface optimisée pour mobile, tablette et desktop
- ⚡ **Performances** : Lazy loading des images, animations fluides
- 🎨 **UI moderne** : Interface sombre élégante avec animations micro-interactions
- 📊 **Logging** : Suivi des visites avec détection OS/Browser

## 🚀 Installation

### Prérequis

- PHP 8.0 ou supérieur
- MySQL 5.7+ ou MariaDB 10.3+
- Extension PHP `pdo_mysql`
- Extension PHP `curl`
- Serveur web (Apache/Nginx) ou serveur de développement PHP intégré

### Configuration

1. **Cloner le projet**

```bash
git clone https://github.com/votre-username/WDHOTD.git
cd WDHOTD
```

2. **Créer la base de données**

Importez le fichier SQL dans votre base de données :

```bash
mysql -u root -p < db_wdhotd.sql
```

Ou via phpMyAdmin :
- Créez une base de données nommée `wdhotd`
- Importez le fichier `db_wdhotd.sql`

3. **Configurer l'environnement**

Créez un fichier `.env` à la racine du projet (copiez depuis `.env.example`) :

```bash
cp .env.example .env
```

Éditez le fichier `.env` avec vos identifiants :

```ini
# Base de données locale (développement)
DB_HOST_LOCAL=localhost
DB_USER_LOCAL=root
DB_PASS_LOCAL=
DB_NAME_LOCAL=wdhotd

# Base de données de production (ex: InfinityFree)
DB_HOST_PROD=sqlXXX.epizy.com
DB_USER_PROD=epiz_12345678
DB_PASS_PROD=votre_mot_de_passe
DB_NAME_PROD=epiz_12345678_otd

# Configuration de l'application
APP_ENV=development
APP_DEBUG=true
```

4. **Configurer le serveur web**

**Avec le serveur PHP intégré (développement) :**

```bash
php -S localhost:8000
```

**Avec Apache/Nginx :**

Configurez votre virtual host pour pointer vers le dossier du projet.

## 📖 Utilisation

### Import manuel des données

Pour importer les données d'une date spécifique :

```
http://votre-site.com/import.php?month=12&day=25
```

Paramètres :
- `month` : Mois (1-12)
- `day` : Jour (1-31)
- `force` : Force la réimportation même si déjà importé aujourd'hui (`?force=1`)
- `verbose` : Mode verbose pour voir les logs (`?verbose=1`)

### Import automatique

L'import automatique est déclenché lors de chaque chargement de page via `auto_import.php`. Il vérifie si les données du jour ont déjà été importées et les importe si nécessaire.

### API Feed

L'endpoint `/feed.php` retourne les événements au format JSON :

```
GET /feed.php?type=all&offset=0&limit=20
```

Paramètres :
- `type` : `all`, `events`, `births`, `deaths`, `holidays` (défaut: `all`)
- `offset` : Nombre d'éléments à ignorer (défaut: `0`)
- `limit` : Nombre d'éléments à retourner (max: 100, défaut: `20`)

**Réponse JSON :**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "events",
      "year": 2023,
      "selected_title": "Titre de l'événement",
      "extract_html": "<p>Description HTML...</p>",
      "extract_short": "Description courte...",
      "thumbnail": "https://...",
      "wikipedia_url": "https://..."
    }
  ],
  "meta": {
    "total": 20,
    "offset": 0,
    "limit": 20,
    "type": "all",
    "date": {
      "month": 12,
      "day": 25
    }
  }
}
```

## 🏗️ Structure du projet

```
WDHOTD/
├── assets/
│   ├── css/
│   │   └── style.css          # Styles CSS
│   └── js/
│       └── feed.js            # Gestion du feed JavaScript
├── config.php                 # Configuration et connexion DB
├── index.php                  # Page principale
├── feed.php                   # API JSON pour le feed
├── import.php                 # Script d'import depuis Wikipedia
├── auto_import.php            # Import automatique quotidien
├── db_wdhotd.sql              # Schéma de la base de données
├── .env.example               # Exemple de configuration
├── .gitignore                 # Fichiers à ignorer par Git
└── README.md                  # Documentation
```

## 🗄️ Base de données

### Table `OTDItem`

Stocke tous les événements historiques :

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT UNSIGNED | Identifiant unique |
| `type` | ENUM | Type : events, births, deaths, holidays |
| `year` | INT | Année de l'événement |
| `month` | INT | Mois (1-12) |
| `day` | INT | Jour (1-31) |
| `pageid` | INT | ID de la page Wikipedia |
| `canonical_title` | VARCHAR(255) | Titre canonique |
| `selected_title` | VARCHAR(255) | Titre d'affichage |
| `extract_short` | TEXT | Extrait court |
| `extract_html` | MEDIUMTEXT | Extrait HTML |
| `thumbnail` | TEXT | URL de l'image |
| `wikipedia_url` | TEXT | Lien vers Wikipedia |
| `raw_json` | LONGTEXT | Données JSON brutes |
| `updated_at` | DATETIME | Date de mise à jour |

### Table `WDLogs`

Logs des visites pour analytics :

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT UNSIGNED | Identifiant unique |
| `page` | VARCHAR(255) | Page visitée |
| `referer` | TEXT | Referer HTTP |
| `user_agent` | TEXT | User agent |
| `os` | VARCHAR(64) | Système d'exploitation |
| `browser` | VARCHAR(64) | Navigateur |
| `device` | VARCHAR(32) | Type d'appareil |
| `datetime` | DATETIME | Date et heure |

## 🔧 Configuration avancée

### Variables d'environnement

Le fichier `.env` permet de configurer :

- **Base de données** : Identifiants pour environnement local et production
- **Application** : Mode (development/production) et debug
- **API** : Timeout et User-Agent pour les appels à Wikipedia

### Détection automatique de l'environnement

Le système détecte automatiquement si vous êtes en local (`localhost`, `127.0.0.1`, `wdhotd.test`, ou IP locale `192.168.x.x`) et utilise la configuration appropriée.

## 🛡️ Sécurité

- ✅ Protection SQL injection (requêtes préparées PDO)
- ✅ Validation et sanitization des entrées
- ✅ Protection XSS (échappement HTML côté client)
- ✅ Variables d'environnement pour les credentials
- ✅ Gestion d'erreurs sécurisée (pas d'exposition en production)

## 🚀 Améliorations futures

- [ ] Système de cache pour améliorer les performances
- [ ] Recherche par date personnalisée
- [ ] Partage social des événements
- [ ] Mode hors ligne (PWA)
- [ ] Analytics avancés
- [ ] Thème clair/sombre
- [ ] Support multi-langue

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à :

1. Fork le projet
2. Créer une branche pour votre feature (`git checkout -b feature/AmazingFeature`)
3. Commit vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 👨‍💻 Auteur

**WDHOTD Team**

- GitHub: [@votre-username](https://github.com/votre-username)

## 🙏 Remerciements

- [Wikipedia](https://www.wikipedia.org/) pour l'API On This Day
- Tous les contributeurs qui ont rendu ce projet possible

---

<div align="center">

Made with ❤️ using PHP, MySQL and JavaScript

⭐ Star ce repo si vous trouvez ce projet utile !

</div>
