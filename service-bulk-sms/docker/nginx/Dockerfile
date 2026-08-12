FROM alpine:latest

# Add Nginx
RUN apk update && apk add nginx

# Run in foreground
RUN apk update \
    && echo "daemon off;" >> /etc/nginx/nginx.conf \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && mkdir -p /run/nginx/ && touch /run/nginx/nginx.pid

# Set the working directory
WORKDIR /app

# Copy the nginx config file (modern Alpine nginx loads vhosts from http.d, not conf.d)
COPY ./docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy the app
COPY . /app

# Expose port 80
EXPOSE 80

# Start Nginx
CMD [ "nginx" ]