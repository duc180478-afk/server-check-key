FROM php:8.2-apache

# Cài đặt thư viện cần thiết cho SQLite và PostgreSQL
RUN apt-get update && apt-get install -y libsqlite3-dev libpq-dev \
    && docker-php-ext-install pdo_sqlite pdo_pgsql

# Bật mod_rewrite + cho phép Apache đọc file .htaccess
RUN a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy source code
COPY . /var/www/html/

# Tạo thư mục data và phân quyền (dùng cho SQLite fallback)
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data

# Cấu hình Apache
RUN a2enmod rewrite

EXPOSE 80