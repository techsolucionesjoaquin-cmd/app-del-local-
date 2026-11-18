FROM php:8.4-cli
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j2 pdo_mysql bcmath mbstring gd \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY frontend ./frontend
COPY backend ./backend
COPY database ./database
RUN mkdir -p backend/storage/uploads backend/storage/logs && chown -R www-data:www-data backend/storage
USER www-data
EXPOSE 3000
CMD ["php","-d","upload_max_filesize=5M","-d","post_max_size=6M","-S","0.0.0.0:3000","-t","frontend","backend/bin/serve.php"]
