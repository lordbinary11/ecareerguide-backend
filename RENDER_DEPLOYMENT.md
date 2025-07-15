# Deploying ECareerGuide Backend on Render (Docker)

This guide will help you deploy your PHP backend on Render using Docker and access the APIs.

## Prerequisites

1. **GitHub Account** - You'll need to connect your code to GitHub
2. **Render Account** - Sign up at https://render.com/
3. **Database** - You'll need a PostgreSQL database (Render provides this)

## Step 1: Prepare Your Code

### 1.1 Create a GitHub Repository

1. Go to GitHub and create a new repository
2. Name it something like `ecareerguide-backend`
3. Make it public (Render free tier requires public repos)

### 1.2 Upload Your Code

1. Clone your repository locally
2. Copy all files from the `backend` folder to your repository
3. Commit and push to GitHub

Your repository structure should look like:
```
ecareerguide-backend/
├── api/
├── public_html/
├── docker/
├── vendor/
├── composer.json
├── composer.lock
├── Dockerfile
├── docker-compose.yml
├── render.yaml
└── README.md
```

## Step 2: Set Up Render

### 2.1 Create a New Web Service

1. Go to https://dashboard.render.com/
2. Click "New +" and select "Web Service"
3. Connect your GitHub account if not already connected
4. Select your `ecareerguide-backend` repository

### 2.2 Configure the Web Service

**Basic Settings:**
- **Name:** `ecareerguide-backend`
- **Environment:** `Docker`
- **Region:** Choose closest to your users
- **Branch:** `main` (or your default branch)
- **Dockerfile Path:** `./Dockerfile` (should auto-detect)

**Environment Variables:**
Add these environment variables in the Render dashboard:

```
DB_HOST = [your-database-host]
DB_NAME = [your-database-name]
DB_USER = [your-database-username]
DB_PASS = [your-database-password]
```

### 2.3 Create a Database

1. In Render dashboard, click "New +" and select "PostgreSQL"
2. Name it `ecareerguide-db`
3. Note down the connection details

## Step 3: Deploy

1. Click "Create Web Service"
2. Render will automatically build and deploy your Docker container
3. Wait for the build to complete (usually 3-7 minutes for first build)
4. Your app will be available at: `https://your-app-name.onrender.com`

## Step 4: Set Up Database

### 4.1 Import Database Schema

1. Go to your database in Render dashboard
2. Click "Connect" and select "External Database"
3. Use a PostgreSQL client (like pgAdmin, DBeaver, or psql) to connect
4. Import your `database_schema.sql` file

### 4.2 Update Environment Variables

1. Go back to your web service
2. Click "Environment" tab
3. Update the database environment variables with your actual database credentials

## Step 5: Test Your APIs

### 5.1 Test the Base URL

Visit: `https://your-app-name.onrender.com`

You should see a JSON response with API documentation.

### 5.2 Test Login API

**URL:** `https://your-app-name.onrender.com/api/login.php`

**Method:** POST

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
    "email": "test@example.com",
    "password": "testpassword"
}
```

### 5.3 Test Registration API

**URL:** `https://your-app-name.onrender.com/api/register.php`

**Method:** POST

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
    "name": "Test User",
    "email": "testuser@example.com",
    "password": "testpassword123",
    "phone": "+1234567890",
    "user_type": "student"
}
```

## Step 6: Update Frontend

Update your React Native app's API base URL:

```javascript
// In your API service file
const BASE_URL = 'https://your-app-name.onrender.com';
```

## Local Development

To test locally before deploying:

```bash
# Build and run with Docker Compose
docker-compose up --build

# Your app will be available at http://localhost:8080
# Database will be available at localhost:5432
```

## Troubleshooting

### Common Issues:

1. **Build Fails:**
   - Check that `Dockerfile` exists
   - Ensure all required files are present
   - Check Docker build logs in Render dashboard

2. **Database Connection Fails:**
   - Verify environment variables are set correctly
   - Check that database is accessible from your web service
   - Ensure database credentials are correct
   - Make sure you're using PostgreSQL connection details, not MySQL

3. **CORS Issues:**
   - The Apache configuration should handle CORS
   - Check that headers are being set correctly

4. **404 Errors:**
   - Ensure files are in the correct directories
   - Check that Apache configuration is correct

### Useful Commands:

```bash
# Check build logs
# Go to your web service in Render dashboard and click "Logs"

# Check environment variables
# Go to "Environment" tab in your web service

# Test locally
docker-compose up --build
```

## API Endpoints

Once deployed, your APIs will be available at:

- **Base URL:** `https://your-app-name.onrender.com`
- **Login:** `POST /api/login.php`
- **Register:** `POST /api/register.php`
- **Profile:** `GET /api/profile.php`
- **Counselors:** `GET /api/get_counselors.php`
- **AI Chat:** `POST /api/ask-ai.php`

## Cost

- **Free Tier:** $0/month (with limitations)
- **Paid Plans:** Starting at $7/month for more resources

The free tier includes:
- 750 hours/month of runtime
- Sleep mode after 15 minutes of inactivity
- 512MB RAM
- Shared CPU

## PostgreSQL Considerations

### Why PostgreSQL?
- **Render's Default**: PostgreSQL is the default database on Render's free tier
- **Better Performance**: PostgreSQL offers better performance for complex queries
- **JSON Support**: Native JSONB support for storing structured data
- **ACID Compliance**: Full ACID compliance for data integrity

### Key Differences from MySQL:
- Uses `SERIAL` instead of `AUTO_INCREMENT` for auto-incrementing IDs
- Uses `JSONB` instead of `JSON` for better performance
- Uses `CHECK` constraints instead of `ENUM` types
- Uses `INTEGER` instead of `INT`
- Connection string format: `pgsql:host=...` instead of `mysql:host=...`

## Advantages of Docker Deployment

1. **Consistent Environment** - Same environment locally and in production
2. **Better Control** - Full control over PHP version and extensions
3. **Easier Debugging** - Can test locally with exact same setup
4. **Scalability** - Easy to scale horizontally
5. **Dependencies** - All dependencies are bundled in the container 