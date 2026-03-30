# HTTPS vhost for {{SERVER_NAME}}
server {
    listen 443 ssl;
    http2 on;
    server_name {{SERVER_NAME}};

    access_log /var/log/nginx/{{SERVER_NAME}}_access.log main_ext buffer=256k flush=5s;
    error_log  /var/log/nginx/{{SERVER_NAME}}_error.log;

    ssl_certificate     /etc/letsencrypt/live/{{SERVER_NAME}}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{{SERVER_NAME}}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        proxy_pass {{UPSTREAM}};
        proxy_next_upstream error timeout invalid_header http_500 http_502 http_503 http_504;

        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Authorization     $http_authorization;

        proxy_connect_timeout 30s;
        proxy_send_timeout    3000s;
        proxy_read_timeout    3000s;
        client_body_timeout   3000s;

        proxy_buffering         off;
        proxy_request_buffering off;

        proxy_http_version 1.1;
        proxy_set_header Upgrade    $http_upgrade;
        proxy_set_header Connection "upgrade";

        client_max_body_size    0;
        client_body_buffer_size 32k;
    }
}
