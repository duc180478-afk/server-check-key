FROM php:8.2-apache

# Cài đặt thư viện cần thiết và extension PDO SQLite
RUN apt-get update && apt-get install -y libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite

# Copy source code
COPY . /var/www/html/

# Tạo thư mục data và phân quyền
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data

# Cấu hình Apache cho phép .htaccess (nếu cần)
RUN a2enmod rewrite

# Expose port 80
EXPOSE 80