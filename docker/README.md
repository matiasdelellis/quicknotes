# Entorno de desarrollo local (Docker)

Levanta un Nextcloud 32 + MariaDB y monta este repositorio dentro de
`custom_apps/quicknotes` para poder probar la app sin instalar Nextcloud
a mano.

Todo lo necesario para el entorno vive en este directorio:

```
docker/
├── docker-compose.yml
├── .env.example
├── scripts/
│   ├── up.sh
│   ├── down.sh
│   ├── build.sh
│   └── enable-app.sh
└── README.md   ← este archivo
```

## Requisitos

- Docker 20.10+ y Docker Compose v2 (o `docker-compose` v1.29+)
- ~2 GB de RAM libres
- Puertos: `8080` (configurable en `.env`)

## Arranque rápido

```bash
# 1) Configurar credenciales (la primera vez)
cp .env.example .env
# editar .env si querés cambiar puertos/contraseñas

# 2) Levantar y habilitar la app
./docker/scripts/up.sh
```

> Nota: el `.env` se mantiene en la raíz del repo para que `docker
> compose` (que busca `.env` relativo al compose) lo encuentre. Si
> preferís tenerlo todo dentro de `docker/`, mové `.env` también a esa
> carpeta y borrá el `--env-file` que pasa `up.sh`.

El script:
1. Levanta MariaDB y Nextcloud.
2. **Si faltan assets compilados** (`js/templates.js`, `js/vendor/`,
   `js/quicknotes-*.js`), corre `./docker/scripts/build.sh`
   automáticamente usando el servicio `builder` (Node 20) de
   docker-compose.
3. Espera a que Nextcloud termine el primer arranque.
4. Pregunta si querés habilitar Quick notes (responde `s`).

Una vez listo, abrí <http://localhost:8080> y entrá con las credenciales
definidas en `.env` (`NEXTCLOUD_ADMIN_USER` / `NEXTCLOUD_ADMIN_PASSWORD`).

## Compilar assets manualmente

Si tocás un `.vue` en `src/components/`, un template Handlebars en
`js/templates/` o un `.js` en `js/`, hay que re-compilar:

```bash
./docker/scripts/build.sh
```

El build se hace dentro del contenedor `quicknotes-builder` (Node 20)
y escribe los artefactos en el host, que ya están montados en
`custom_apps/quicknotes` dentro de Nextcloud. Para que el navegador
tome los cambios, refrescá con Ctrl+Shift+R (Nextcloud cachea JS
agresivamente).

## Comandos útiles

```bash
# Re-habilitar la app luego de cambios de código PHP/templates
./docker/scripts/enable-app.sh

# Re-compilar assets luego de tocar .vue, .handlebars, .js
./docker/scripts/build.sh

# Entrar al contenedor de Nextcloud
docker exec -it -u www-data quicknotes-app bash

# Ver logs de Nextcloud
docker logs -f quicknotes-app

# Ver logs de la DB
docker logs -f quicknotes-db

# Bajar los contenedores (conservando los datos)
./docker/scripts/down.sh

# Bajar y borrar TODO (DB + archivos de Nextcloud + caché de npm)
./docker/scripts/down.sh --purge
```

## Cómo funciona

- `docker-compose.yml` define tres servicios: `db` (MariaDB 11.4),
  `app` (Nextcloud 32) y `builder` (Node 20, para compilar assets).
- El directorio raíz del repo se monta como volumen en
  `/var/www/html/custom_apps/quicknotes` (los volúmenes del compose
  usan `../` para salir de `docker/` y montar el proyecto completo).
- Los datos persistentes (DB + `data/`, `config/`, `apps/` no montados)
  viven en los volúmenes `quicknotes_db`, `quicknotes_nc` y
  `quicknotes_npm_cache`.

## Limitaciones

- El `info.xml` declara la app para Nextcloud 32. Si necesitás otra
  versión, cambiá el tag de la imagen en `docker-compose.yml` (p. ej.
  `nextcloud:31`).
- SELinux en Enforcing requiere el sufijo `:z` en el bind mount (ya
  está configurado). Si tu distro no usa SELinux podés sacarlo.
- El bundle Vue de dashboard pesa ~3 MB (warning de webpack). Es
  tamaño aceptable para dev; la app oficial lo reduce con code-split.
