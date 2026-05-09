# Commandes CLI

## app:user:promote — Promouvoir / rétrograder un admin

```bash
php bin/console app:user:promote <email>
php bin/console app:user:promote <email> --revoke
```

**Utilité** : accéder à `/admin` sans modifier la base manuellement. Une seule ligne au lieu d'un `UPDATE SQL`.

---

## app:user:verify — Vérifier un compte sans cliquer le lien email

```bash
php bin/console app:user:verify <email>
```

**Utilité** : passe `is_verified = true` et efface le `verification_token`. Indispensable quand tu testes une feature qui exige un compte vérifié sans aller dans Mailpit à chaque fois.

---

## app:user:reset-link — Générer un lien reset password sans email

```bash
php bin/console app:user:reset-link <email>
```

**Utilité** : génère un token, l'enregistre en base avec une expiration à +1h, et affiche le lien directement dans la console. Copie-colle dans le navigateur, sans dépendre du SMTP / Mailpit.

---

## app:user:create — Créer un utilisateur depuis la CLI

```bash
php bin/console app:user:create <email> <password> [options]
```

| Argument / Option | Description |
|---|---|
| `email` | Email du nouveau compte |
| `password` | Mot de passe en clair (sera hashé) |
| `--firstname=` | Prénom (défaut : `Test`) |
| `--lastname=` | Nom (défaut : `User`) |
| `--admin` | Attribue `ROLE_ADMIN` |
| `--verified` | Marque le compte comme vérifié |


**Utilité** : créer plusieurs comptes de test en une ligne sans passer par le formulaire d'inscription. Idéal pour scripter un jeu de données initial.

---

## app:user:list — Lister les utilisateurs

```bash
php bin/console app:user:list [options]
```

| Option | Description |
|---|---|
| `--admins` | Affiche uniquement les administrateurs |
| `--unverified` | Affiche uniquement les comptes non vérifiés |


**Utilité** : voir d'un coup d'œil qui existe en base (ID, email, nom, rôles, statut vérifié, date d'inscription) sans ouvrir phpMyAdmin ou DBeaver.

---

### app:admin:weekly-signups-report — Rapport hebdomadaire des inscriptions

```bash
php bin/console app:admin:weekly-signups-report [options]
```

| Option | Description |
|---|---|
| `--days=N` | Fenêtre en jours (défaut : `7`) |
| `--dry-run` | Affiche le rapport sans envoyer d'email |

**Utilité** : envoie à l'adresse configurée dans `MAILER_REPORT_EMAIL` (.env) un tableau récapitulatif des nouveaux inscrits.

find public/uploads/articles public/uploads/avatars -type f ! -name ".gitkeep" -delete && find public/media/cache -type f -delete 2>/dev/null; echo "Uploads nettoyés."