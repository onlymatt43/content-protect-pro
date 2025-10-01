# 📋 **GUIDE DES SHORTCODES - Content Protect Pro**

## 🎯 **Shortcodes Disponibles**

### 1. **Formulaire de Code Cadeau** `[cpp_giftcode_form]`
Utilisez ce shortcode pour afficher un formulaire où les utilisateurs peuvent entrer leur code cadeau.

```php
[cpp_giftcode_form redirect_url="/premium-content/" success_message="Bienvenue dans le contenu premium!"]
```

**Paramètres:**
- `redirect_url` (optionnel): URL de redirection après validation réussie
- `success_message` (optionnel): Message affiché après validation
- `class` (optionnel): Classe CSS personnalisée

**Exemple d'utilisation:**
Sur une page d'accueil ou de connexion pour valider l'accès.

---

### 2. **Vidéo Protégée** `[cpp_protected_video]`
Affiche une vidéo protégée qui nécessite un code cadeau valide.

```php
[cpp_protected_video id="votre-video-presto-id" code="GIFT_CODE"]
```

**Paramètres:**
- `id` (obligatoire): ID de la vidéo Presto Player
- `code` (optionnel): Code cadeau requis pour l'accès
- `width` (optionnel): Largeur du player (défaut: `100%`)
- `height` (optionnel): Hauteur du player (défaut: `400px`)

**Exemple d'utilisation:**
```php
[cpp_protected_video id="123" code="VIP2024" width="800px" height="450px"]
```

---

### 3. **Vérification de Code Cadeau** `[cpp_giftcode_check]`
Affiche du contenu différent selon si l'utilisateur a un code cadeau valide.

```php
[cpp_giftcode_check required_codes="PREMIUM,VIP" success_content="Contenu premium ici" failure_content="Veuillez entrer un code valide"]
```

**Paramètres:**
- `required_codes` (optionnel): Codes requis, séparés par des virgules
- `success_content` (obligatoire): Contenu affiché si code valide
- `failure_content` (optionnel): Message si code invalide

**Exemple d'utilisation:**
```php
[cpp_giftcode_check required_codes="GOLD,PLATINUM" success_content="<h2>Contenu VIP</h2><p>Voici votre contenu exclusif...</p>" failure_content="<p>Accès réservé aux membres premium.</p>"]
```

---

### 4. **Bibliothèque de Vidéos** `[cpp_video_library]`
Affiche TOUTES les vidéos dans une grille avec filtres et recherche.

```php
[cpp_video_library per_page="12" columns="3" show_filters="true" show_search="true"]
```

**Paramètres:**
- `per_page` (optionnel): Nombre de vidéos par page (défaut: `12`)
- `columns` (optionnel): Nombre de colonnes (1-4, défaut: `3`)
- `show_filters` (optionnel): Afficher les filtres `true`/`false` (défaut: `true`)
- `show_search` (optionnel): Afficher la recherche `true`/`false` (défaut: `true`)
- `access_level` (optionnel): Filtrer par niveau d'accès
- `require_giftcode` (optionnel): `true` pour n'afficher que les vidéos nécessitant un code
- `class` (optionnel): Classe CSS personnalisée

**Exemple complet:**
```php
[cpp_video_library per_page="20" columns="4" show_filters="true" show_search="true"]
```

---

## 🚀 **Exemples Pratiques**

### **Page de Connexion**
```php
<h1>Bienvenue sur notre plateforme</h1>
<p>Entrez votre code d'accès pour continuer:</p>

[cpp_giftcode_form redirect_url="/dashboard/" success_message="Connexion réussie! Redirection..."]
```

### **Page de Vidéo Premium**
```php
<h1>Vidéo Exclusive</h1>
<p>Cette vidéo est réservée à nos membres premium.</p>

[cpp_protected_video video_id="premium-video-001" require_giftcode="true" player_type="bunny"]
```

### **Contenu Conditionnel**
```php
<h1>Tableau de Bord</h1>

[cpp_giftcode_check required_codes="ADMIN,SUPERUSER"
    success_content="<div class='admin-panel'><h2>Panneau Admin</h2><p>Gestion complète du système...</p></div>"
    failure_content="<div class='user-panel'><h2>Espace Membre</h2><p>Contenu standard pour les membres...</p></div>"]
```

### **Page de Bibliothèque Vidéo**
```php
<h1>Ma Bibliothèque Vidéo</h1>
<p>Découvrez toutes nos vidéos exclusives.</p>

[cpp_video_library]
```

### **Section avec filtres**
```php
<h2>Vidéos Premium</h2>
[cpp_video_library per_page="8" columns="2" show_filters="true"]
```

### **Galerie simple**
```php
[cpp_video_library show_filters="false" show_search="false" columns="3"]
```

---

## ⚙️ **Configuration Requise**

### **Avant d'utiliser les shortcodes:**

1. **Configurez les intégrations** dans Content Protect Pro → Settings → Integrations
2. **Ajoutez vos vidéos** dans Content Protect Pro → Protected Videos
3. **Créez des codes cadeaux** dans Content Protect Pro → Gift Codes

### **IDs de vidéos:**
- Pour Presto Player: Utilisez l'ID du player Presto (visible dans l'admin Presto Player)

---

## 🎨 **Personnalisation CSS**

Ajoutez ces classes CSS pour personnaliser l'apparence:

```css
/* Formulaire de code cadeau */
.cpp-giftcode-form {
    max-width: 400px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.cpp-form-group {
    margin-bottom: 15px;
}

.cpp-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.cpp-form-group input[type="text"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.cpp-form-group button {
    background: #007cba;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.cpp-form-group button:hover {
    background: #005a87;
}

/* Contenu protégé */
.cpp-protected-content {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    color: #666;
}

/* Messages */
.cpp-message {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
}

.cpp-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.cpp-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
```

---

## 🔧 **Dépannage**

### **Le shortcode ne s'affiche pas:**
- Vérifiez que le plugin est activé
- Assurez-vous que l'ID de vidéo existe dans l'admin
- Vérifiez les permissions utilisateur

### **La vidéo ne se charge pas:**
- Vérifiez que Presto Player est installé et activé
- Assurez-vous que l'utilisateur a un code cadeau valide
- Vérifiez que l'ID Presto Player est correct

### **Le formulaire ne fonctionne pas:**
- Activez JavaScript dans le navigateur
- Vérifiez que les nonces WordPress sont actifs
- Vérifiez les permissions AJAX

---

## 📞 **Support**

Si vous avez des problèmes avec les shortcodes:
1. Vérifiez la configuration des intégrations
2. Testez avec un code cadeau valide
3. Consultez les logs WordPress (WP_DEBUG activé)
4. Contactez le support avec les détails de l'erreur

**🎉 Prêt à protéger votre contenu !**