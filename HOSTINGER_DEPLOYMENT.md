# Hostinger Deployment Guide for AI Invoice to Excel

This guide provides step-by-step instructions for deploying this Laravel application onto Hostinger (Web Hosting, Cloud Hosting, or VPS).

---

## Method 1: Hostinger Shared / Cloud Hosting (via hPanel File Manager)

### Step 1: Upload Files
1. Zip your project files, but **DO NOT** include the following:
   - `node_modules` folder (not needed, production assets are pre-built in `public/build`)
   - `.git` folder
2. In Hostinger **hPanel**, go to **File Manager**.
3. Upload and extract your zip file directly into `public_html/`.

> **Note on Root `.htaccess`:**
> A root [`.htaccess`](file:///c:/xampp/htdocs/pdftoexcel/.htaccess) file has been added to automatically route domain traffic directly to the `public/` directory without exposing system files.

---

### Step 2: Configure Database in hPanel
1. In hPanel, navigate to **Databases -> MySQL Databases**.
2. Create a new database:
   - **Database Name** (e.g., `u123456_invoice`)
   - **Username** (e.g., `u123456_admin`)
   - **Password**
3. Open **phpMyAdmin** in hPanel and import your database or run migrations via SSH.

---

### Step 3: Configure `.env`
1. Rename [`.env.production`](file:///c:/xampp/htdocs/pdftoexcel/.env.production) to `.env` in your root directory.
2. Edit the `.env` file with your live details:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_database_username
   DB_PASSWORD=your_database_password

   GEMINI_API_KEY=your_gemini_api_key
   ```

---

### Step 4: Hostinger PHP Configuration
1. In hPanel, go to **Advanced -> PHP Configuration**.
2. Select **PHP 8.2** or **PHP 8.3**.
3. Under **PHP Extensions**, ensure the following are enabled:
   - `pdo_mysql`, `fileinfo`, `mbstring`, `zip`, `gd`, `curl`, `xml`, `intl`.

---

### Step 5: Run Production Artisan Commands (via SSH or hPanel Terminal)
Connect to your server via SSH and run:
```bash
# 1. Generate unique application key
php artisan key:generate

# 2. Run database migrations and default admin seeder
php artisan migrate --force
php artisan db:seed --force

# 3. Create storage symlink
php artisan storage:link

# 4. Cache configurations and routes for high performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

### Step 6: Setup Background Queue & Cron Job (Optional but Recommended)
In hPanel, go to **Advanced -> Cron Jobs**:
- Choose **Common Settings: Every Minute (`* * * * *`)**
- Command:
  ```bash
  cd /home/u123456/public_html && php artisan schedule:run >> /dev/null 2>&1
  ```

---

## Method 2: Hostinger VPS (Ubuntu / Nginx)

If deploying to a VPS with Nginx, use this site configuration block:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/pdftoexcel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## Default Login Credentials
- **Admin Email:** `admin@example.com`
- **Password:** `password`
- *(Change this password immediately under **My Profile** after first login).*
