FROM nginx:1.27-alpine

# Remove default config
RUN rm /etc/nginx/conf.d/default.conf

COPY nginx.conf /etc/nginx/nginx.conf

# Create directories for SSL and ACME challenge
RUN mkdir -p /etc/nginx/ssl /var/www/certbot

EXPOSE 80 443
