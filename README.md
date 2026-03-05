# TacoMap France API (PHP 8.2, no framework)

API REST pour gérer l'entité unique `TacosPlace` avec JWT, upload image sécurisé, envoi d'email SMTP à la création et export PDF.

## Prérequis
- PHP 8.2+
- MySQL 8+

## Installation
1. Copier l'environnement:
```bash
copy .env.example .env
```
2. Créer la base:
```bash
mysql -u root -p < database/schema.sql
```
3. Lancer l'API:
```bash
php -S localhost:8001 -t public
```

## Authentification
- `POST /api/auth/login` (public) retourne un JWT Bearer.
- CRUD `POST/PUT/DELETE` + `GET /api/tacos-places/{id}/pdf` sont protégés JWT.
- `GET /api/tacos-places` et `GET /api/tacos-places/{id}` sont publics.

## Endpoints
- `POST /api/auth/login`
- `GET /api/tacos-places?page=1&limit=20&q=...`
- `GET /api/tacos-places/{id}`
- `POST /api/tacos-places` (multipart, champ `photo`)
- `PUT /api/tacos-places/{id}` (JSON ou multipart)
- `DELETE /api/tacos-places/{id}`
- `GET /api/tacos-places/{id}/pdf` (JWT)

## Format JSON `TacosPlace`
```json
{
  "id": 1,
  "name": "Tacos Lyon Centre",
  "description": "Adresse incontournable",
  "date": "2026-03-01 12:00:00",
  "price": 10,
  "latitude": 45.764043,
  "longitude": 4.835659,
  "contact_name": "Alice Martin",
  "contact_email": "alice@example.com",
  "photo": "uploads/abc123.jpg",
  "photo_url": "http://localhost:8001/uploads/abc123.jpg",
  "created_at": "2026-03-01 12:00:00",
  "updated_at": "2026-03-01 12:00:00"
}
```

## Fonctionnalités sécurité
- CORS strict basé sur `CORS_ALLOWED_ORIGINS`.
- Validation stricte des types/champs (email, int, float, bornes latitude/longitude).
- Requêtes SQL préparées (PDO).
- Upload photo sécurisé:
  - mimetype + extension autorisés
  - taille max configurable (`UPLOAD_MAX_SIZE_BYTES`)
  - nom de fichier aléatoire
- Nettoyage texte (strip tags) pour limiter contenus HTML/XSS.

## Email à la création
- Lors d'un `POST /api/tacos-places`, l'API envoie un email SMTP à `contact_email`.
- Le mail contient le récapitulatif des champs + lien de détail (`ADMIN_DETAIL_BASE_URL/{id}` ou fallback API).

## PDF
- `GET /api/tacos-places/{id}/pdf` retourne un PDF de fiche détail (tous les champs + URL photo).

## Exemple rapide
1. Login:
```bash
curl -s -X POST http://localhost:8001/api/auth/login ^
  -H "Content-Type: application/json" ^
  -d "{\"email\":\"admin@tacomap.local\",\"password\":\"Password123!\"}"
```
2. Créer un TacosPlace:
```bash
curl -s -X POST http://localhost:8001/api/tacos-places ^
  -H "Authorization: Bearer <TOKEN>" ^
  -F "name=Tacos Lille" ^
  -F "description=Très bon tacos" ^
  -F "date=2026-03-05 12:30:00" ^
  -F "price=12" ^
  -F "latitude=50.62925" ^
  -F "longitude=3.057256" ^
  -F "contact_name=Marie" ^
  -F "contact_email=marie@example.com" ^
  -F "photo=@C:/path/photo.jpg"
```
