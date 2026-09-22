FROM node:24-alpine

WORKDIR /app

# Dependencies first, so this layer is cached until the lockfile changes.
# npm ci installs exactly what package-lock.json pins (and never rewrites it).
# The lock-hash stamp lets the entrypoint tell whether a (volume-persisted)
# node_modules still matches package-lock.json.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund \
    && sha1sum package-lock.json | cut -d' ' -f1 > node_modules/.package-lock-hash

COPY . .

# Outside the bind-mounted app directory, so it can't be shadowed. The sed
# strips CRLF in case the script was checked out on Windows without
# .gitattributes applied.
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint \
    && chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 5173

ENTRYPOINT ["docker-entrypoint"]
CMD ["npm", "run", "dev"]
