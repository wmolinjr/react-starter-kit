# 14 - Deployment e Produção

## Configuração de Produção

### 1. Environment Variables

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.myapp.com

# Domínios
CENTRAL_DOMAIN=app.myapp.com
TENANT_DOMAIN_SUFFIX=.myapp.com

# Database
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_DATABASE=production_db
DB_USERNAME=production_user
DB_PASSWORD=secure_password

# Cache & Queue
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=secure_redis_password

# Stripe Production
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_live_...

# S3 para arquivos
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_FROM_ADDRESS=noreply@myapp.com
```

### 2. Wildcard SSL Certificate

**Opção 1: Let's Encrypt com Certbot**

```bash
sudo certbot certonly --dns-cloudflare \
  -d myapp.com \
  -d *.myapp.com
```

**Opção 2: Cloudflare (Recomendado)**

1. Adicionar domínio ao Cloudflare
2. Ativar proxy (laranja)
3. Habilitar "Full (strict)" SSL/TLS
4. Criar Origin Certificate
5. Configurar no servidor

### 3. Nginx Configuration

```nginx
# /etc/nginx/sites-available/myapp

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name myapp.com *.myapp.com;
    return 301 https://$host$request_uri;
}

# HTTPS Server
server {
    listen 443 ssl http2;
    server_name myapp.com *.myapp.com;

    root /var/www/myapp/public;
    index index.php;

    # SSL Certificates (Wildcard)
    ssl_certificate /etc/ssl/certs/myapp.com.crt;
    ssl_certificate_key /etc/ssl/private/myapp.com.key;

    # SSL Configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Logs
    access_log /var/log/nginx/myapp-access.log;
    error_log /var/log/nginx/myapp-error.log;

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 4. Queue Workers (Supervisor)

```ini
# /etc/supervisor/conf.d/myapp-worker.conf

[program:myapp-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/myapp/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/myapp/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start myapp-worker:*
```

### 5. Scheduled Tasks (Cron)

```cron
* * * * * cd /var/www/myapp && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Deploy Script

```bash
#!/bin/bash
# deploy.sh

set -e

cd /var/www/myapp

# Maintenance mode
php artisan down

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers
sudo supervisorctl restart myapp-worker:*

# Exit maintenance mode
php artisan up

echo "Deployment completed successfully!"
```

---

## Checklist

- [ ] Variables de ambiente configuradas
- [ ] Wildcard SSL instalado
- [ ] Nginx configurado para subdomains
- [ ] Queue workers rodando via Supervisor
- [ ] Cron configurado
- [ ] Deploy script criado
- [ ] Backups automatizados
- [ ] Monitoring (Telescope, Sentry, etc)

---

## Próximo Passo

➡️ **[15-SECURITY.md](./15-SECURITY.md)** - Checklist de Segurança

---

**Versão:** 1.0
