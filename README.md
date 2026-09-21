# X_Academy

Journal académique numérique — suivi des enseignements et des présences.

## Installation (XAMPP)

1. **Copier le dossier** `X_Academy/` dans `htdocs/` de votre installation XAMPP
   (ex. `C:\xampp\htdocs\X_Academy` ou `/opt/lampp/htdocs/X_Academy`).

2. **Créer la base de données** :
   - Démarrez Apache et MySQL depuis le panneau XAMPP.
   - Ouvrez phpMyAdmin (`http://localhost/phpmyadmin`).
   - Importez le fichier `database/x_academy.sql` (onglet *Importer*).
     Ce script crée la base `x_academy`, toutes les tables, et un compte
     administrateur par défaut.

3. **Configurer la connexion** (si besoin) :
   - Fichier `config/db.php` : adaptez `DB_USER` / `DB_PASS` si votre
     installation MySQL n'utilise pas les valeurs par défaut de XAMPP
     (`root` sans mot de passe).
   - Fichier `config/config.php` : adaptez `BASE_URL` si vous avez placé
     le projet dans un sous-dossier différent de `/X_Academy`.

4. **Accéder à l'application** :
   - Ouvrez `http://localhost/X_Academy` dans votre navigateur.
   - Connectez-vous avec le compte administrateur par défaut :
     - **Identifiant :** `admin`
     - **Mot de passe :** `Admin@2026`
   - Vous serez invité à changer ce mot de passe dès la première connexion.

## Mise à jour d'une installation existante

Si vous avez déjà importé une version précédente de `x_academy.sql`, exécutez
dans l'ordre les scripts de migration correspondant aux versions que vous
n'avez pas encore appliquées (onglet SQL de phpMyAdmin, sur la base
`x_academy`) :

1. `database/migration_v2_settings.sql` — paramètres de l'université
2. `database/migration_v4_audit_log.sql` — journal d'audit
3. `database/migration_v5_student_portal.sql` — **espace Étudiant, filières,
   cycle de vie des séances, notifications** (lisez les commentaires en tête
   de ce fichier avant de l'exécuter : il contient une vérification à faire
   manuellement si des étudiants existants partagent déjà un même matricule)

(la version 3 concernait un assistant IA payant, abandonnée — ignorez-la
si vous l'aviez reçue).

## ⚠️ Changement important : durée d'une heure académique

Dans cette université, **une heure de cours dure 50 minutes réelles**, pas
60. Ce paramètre est centralisé dans `config/config.php` :

```php
define('MINUTES_PER_ACADEMIC_HOUR', 50);
```

Toute la durée d'une séance (et donc tout le calcul du volume horaire des
cours, de la progression, du score de santé...) est désormais exprimée dans
cette unité. Si votre université utilise une autre durée, changez uniquement
cette constante — tout le reste de l'application s'adapte automatiquement.

## Nouveautés de cette version — Espace Étudiant

**Nouveau rôle : Étudiant**
- Chaque étudiant a son propre compte, créé par le Délégué (identifiant,
  mot de passe temporaire, matricule)
- À la première connexion : changement de mot de passe obligatoire, puis
  complétion/confirmation du profil (nom, téléphone, e-mail)
- Le **matricule** est verrouillé pour l'étudiant et le Délégué — seul le
  Chef de Département peut le corriger (`head/student_matricule_edit.php`),
  sur demande faite hors de l'application
- Département, filière, promotion et groupe sont hérités automatiquement du
  compte créé par le Délégué et ne sont jamais modifiables par l'étudiant

**Nouvelle hiérarchie : Filière**

```
Département → Filière → Promotion → Groupe
```

Gérée par l'Admin (`admin/filieres.php`). Chaque promotion doit désormais
être rattachée à une filière de son département.

**Cycle de vie des séances**

```
Brouillon → Publié → Terminé (appel fait)
                ↘
                 Annulé
```

- Une séance en **brouillon** n'est visible que par le Délégué et reste
  librement modifiable
- Une fois **publiée**, elle apparaît dans l'horaire des étudiants et
  déclenche une **notification automatique** ; toute modification ultérieure
  renvoie une nouvelle notification
- Une séance n'est comptée dans les heures prestées qu'une fois **Terminée**
  (l'appel a été fait) — jamais avant, jamais si elle est **Annulée**

**Notifications internes** (cloche dans la barre de navigation étudiant,
badge de compteur non lus) — aucun envoi d'e-mail, tout reste dans l'app.

**Espace Étudiant** (`student/`) : tableau de bord, horaire, mes cours
(avec accès en lecture au registre PDF), historique de mes présences (avec
mêmes badges d'assiduité que côté Délégué), notifications, profil.

## Nouveautés de la version précédente

**Pilotage et visibilité**
- **Tableaux de bord enrichis** avec un bloc d'alertes automatiques (cours
  sans séance depuis longtemps, volume horaire presque épuisé).
- **Score de santé académique** (0-100) par département et par promotion,
  combinant progression et assiduité, affiché dans les pages Statistiques.
- **Aperçu automatique** (déjà présent) conservé et complété par ces alertes.
- **Prédiction de fin de cours** : estimation de la date d'achèvement d'un
  cours à partir du rythme réel des séances (`delegate/course_view.php`).

**Suivi des étudiants**
- **Profil individuel étudiant** (`delegate/student_view.php`) : taux de
  présence global, détail par cours, historique des absences.
- **Détection de risque d'assiduité** (Normal / À surveiller / À risque),
  affichée sur toutes les listes d'étudiants (Délégué, Chef, Admin).
- **Badges d'assiduité** (Présence parfaite, Assidu, Régulier) sur le profil.

**Gouvernance et fiabilité**
- **Journal d'audit** (`admin/audit_log.php`) : trace des connexions,
  créations, modifications et téléchargements de registres.
- **Détection de qualité des données** (`admin/data_quality.php`) : repère
  les intitulés de cours proches (variantes d'orthographe) au sein d'une
  même promotion, avec une action d'uniformisation en un clic.

**Confort d'usage**
- **Recherche globale** (barre dans la navigation) : étudiants, cours,
  promotions, départements, selon la portée du rôle connecté.
- **Export CSV** (compatible Excel) des étudiants et des cours, à
  plusieurs niveaux (université, département, promotion).
- **Registre PDF enrichi** d'une page de synthèse finale (statistiques
  globales, étudiants les moins assidus sur le cours).

Toutes ces fonctionnalités sont **gratuites et fonctionnent sans clé API**
ni service externe payant — uniquement du calcul local sur les données déjà
enregistrées.

## Prise en main

1. En tant qu'**Administrateur**, configurez d'abord les informations de
   l'université (`admin/settings.php`), créez une **filière**
   (`admin/filieres.php`), puis un département (`admin/departments.php`)
   et un Chef de Département (`admin/heads.php`).
2. Le **Chef de Département** se connecte, change son mot de passe, puis crée
   une promotion rattachée à une filière (`head/promotions.php`) et un
   Délégué (`head/delegates.php`), qu'il assigne à cette promotion.
3. Le **Délégué** se connecte, change son mot de passe, ajoute ses étudiants
   avec un identifiant et un matricule (`delegate/students.php`) — un mot de
   passe temporaire est généré pour chacun —, crée ses cours
   (`delegate/courses.php`), puis planifie des séances (`delegate/course_view.php`)
   en brouillon ou publiées directement.
4. L'**Étudiant** se connecte avec l'identifiant fourni, change son mot de
   passe, complète son profil, puis consulte son horaire, ses cours et son
   historique de présence.
5. Une fois une séance publiée, le Délégué **fait l'appel**
   (`delegate/attendance.php`) — c'est cette étape qui fait passer la séance
   au statut "Terminée" et qui la comptabilise dans les heures prestées.
6. Le registre PDF de chaque cours est disponible à tout moment depuis la
   fiche du cours, le journal du Délégué, les statistiques du Chef, ou
   l'espace de l'étudiant concerné (lecture seule).

## Notes techniques

- **Aucune dépendance externe** : la génération des PDF est assurée par une
  petite classe maison (`includes/SimplePDF.php`), en PHP pur, sans Composer
  ni bibliothèque tierce — pratique sur un simple XAMPP sans accès Internet.
- **Connexion Internet requise pour l'interface** : les polices (Google
  Fonts), le framework de style (Tailwind CDN) et les diagrammes
  (Chart.js CDN) sont chargés depuis Internet. Sans connexion, l'application
  reste fonctionnelle mais s'affiche sans mise en forme ni graphiques.
- **Mots de passe** : hashés avec `password_hash()` (bcrypt). Les mots de
  passe temporaires générés à la création d'un compte doivent être changés
  à la première connexion (`must_change_password`).
- **Conservation des données** : désactiver un compte (Délégué, Chef,
  département) ne supprime jamais les cours, séances, présences ou PDF
  déjà enregistrés — seul l'accès au compte est coupé.
- **Un cours = un registre PDF unique**, généré à la volée à partir de MySQL
  (source de vérité) à chaque consultation/téléchargement — il n'y a donc
  jamais de désynchronisation entre les données et le PDF.

## Compte par défaut

| Rôle  | Identifiant | Mot de passe   |
|-------|-------------|----------------|
| Admin | `admin`     | `Admin@2026`   |

*(à changer immédiatement après la première connexion)*
