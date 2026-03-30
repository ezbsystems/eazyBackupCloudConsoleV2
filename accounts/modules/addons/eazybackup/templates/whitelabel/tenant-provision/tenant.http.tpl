# HTTP -> HTTPS redirect for {{SERVER_NAME}}
server {
    listen 80;
    server_name {{SERVER_NAME}};
    access_log /var/log/nginx/{{SERVER_NAME}}_access.log main_ext buffer=256k flush=5s;
    error_log  /var/log/nginx/{{SERVER_NAME}}_error.log;

    # ACME HTTP-01 (webroot) — ^~ ensures this wins over the catch-all redirect
    location ^~ /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
        try_files $uri =404;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}
