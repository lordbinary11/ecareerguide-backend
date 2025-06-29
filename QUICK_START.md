# Quick Start: Deploy on Render

## 🚀 Fast Deployment Steps

### 1. Create GitHub Repository
```bash
# Create a new repository on GitHub
# Name: ecareerguide-backend
# Make it public
```

### 2. Upload Code to GitHub
```bash
# Clone your repository
git clone https://github.com/yourusername/ecareerguide-backend.git

# Copy backend files to repository
cp -r backend/* ecareerguide-backend/

# Commit and push
cd ecareerguide-backend
git add .
git commit -m "Initial backend deployment"
git push origin main
```

### 3. Deploy on Render

1. **Go to Render:** https://dashboard.render.com/
2. **Click "New +" → "Web Service"**
3. **Connect GitHub** and select your repository
4. **Configure:**
   - Name: `ecareerguide-backend`
   - Environment: `PHP`
   - Build Command: `composer install`
   - Start Command: `vendor/bin/heroku-php-apache2 public_html/`
5. **Click "Create Web Service"**

### 4. Create Database

1. **Click "New +" → "MySQL"**
2. **Name:** `ecareerguide-db`
3. **Note down credentials**

### 5. Set Environment Variables

In your web service dashboard:
```
DB_HOST = [from database]
DB_NAME = [from database]
DB_USER = [from database]
DB_PASS = [from database]
PHP_VERSION = 8.1
```

### 6. Import Database

1. Go to your database in Render
2. Click "Connect" → "External Database"
3. Use MySQL client to import `database_schema.sql`

### 7. Test Your API

**Base URL:** `https://your-app-name.onrender.com`

**Test endpoints:**
- `GET /` - API documentation
- `POST /api/login.php` - Login
- `POST /api/register.php` - Registration

## 📱 Update Frontend

In your React Native app, update the API base URL:

```javascript
// services/api.js
const BASE_URL = 'https://your-app-name.onrender.com';
```

## ✅ Verify Deployment

Visit: `https://your-app-name.onrender.com/test_render_deployment.php`

This will show you if everything is working correctly.

## 🔧 Troubleshooting

- **Build fails:** Check `composer.json` exists
- **Database error:** Verify environment variables
- **404 errors:** Check file paths in `public_html/`

## 💰 Cost

- **Free tier:** $0/month
- **Limitations:** Sleep mode after 15 min inactivity
- **Upgrade:** $7/month for always-on service 