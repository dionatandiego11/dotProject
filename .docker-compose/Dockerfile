FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
  libpng-dev \
  libjpeg-dev \
  libfreetype6-dev \
  libzip-dev \
  libicu-dev \
  libonig-dev \
  unzip \
  && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
  gd \
  mysqli \
  pdo_mysql \
  zip \
  intl \
  mbstring \
  opcache

# Create session directory
RUN mkdir -p /var/lib/php/sessions && chmod 777 /var/lib/php/sessions

# Enable opcache for better performance
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
  && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
  && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini

# Set recommended PHP settings
RUN echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/dotproject.ini \
  && echo "upload_max_filesize=50M" >> /usr/local/etc/php/conf.d/dotproject.ini \
  && echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/dotproject.ini \
  && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/dotproject.ini \
  && echo "session.save_path=/var/lib/php/sessions" >> /usr/local/etc/php/conf.d/dotproject.ini \
  && echo "session.auto_start=1" >> /usr/local/etc/php/conf.d/dotproject.ini

WORKDIR /var/www/html
