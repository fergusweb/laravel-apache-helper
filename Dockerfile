FROM php:8.3-apache
RUN apt-get update -y && apt-get install -y openssl zip unzip git npm
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
#RUN docker-php-ext-install pdo mbstring
WORKDIR /app

COPY package.json /app
COPY package-lock.json /app
RUN npm ci && npm cache clean --force

COPY . /app
RUN composer install
#RUN npm install
RUN npm run build

CMD php artisan serve --host=0.0.0.0 --port=80
EXPOSE 80
