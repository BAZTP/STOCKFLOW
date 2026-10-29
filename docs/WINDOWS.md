# Instalación en Windows (PowerShell y Visual Studio Code)

Requisitos: Git, PHP 8.5 x64, Composer 2, Node.js 24 y MySQL 8.4.
Habilitar en php.ini: pdo_mysql, mbstring, bcmath, curl, fileinfo, openssl,
dom, xml, xmlwriter y zip. Revisar `php -v`, `php -m`, `composer -V`,
`node -v`, `npm -v` y `mysql --version` antes de instalar.

Cuando el código esté publicado en GitHub, clona el repositorio. También puedes
copiar el proyecto actual, excluyendo `.env`, `vendor` y `node_modules`.

```powershell
git clone https://github.com/BAZTP/STOCKFLOW.git
cd STOCKFLOW
Copy-Item .env.example .env
composer install
npm ci
php artisan key:generate
```

Crear las bases MySQL `stockflow` y `stockflow_test`, con un usuario local
que tenga permisos sobre ambas. En `.env` configurar DB_HOST=127.0.0.1,
DB_PORT, DB_DATABASE=stockflow, DB_USERNAME y DB_PASSWORD. No subir `.env`
ni reutilizar credenciales de producción.

```powershell
php artisan migrate --seed
php artisan storage:link
php artisan stockflow:admin admin@tuempresa.com
npm run build
php artisan test
php artisan serve
```

El comando de administrador pide la contraseña sin mostrarla. Para desarrollar
estilos, ejecutar `npm run dev` en otra terminal. Para cambiar la contraseña
usar Mi perfil. Configurar MAIL_* en `.env` para enviar recuperación por correo;
el transporte `log` es sólo para desarrollo y guarda mensajes en storage/logs.
No compartir logs que contengan enlaces de recuperación.

No usar `migrate:fresh` sobre datos que deban conservarse. Los tests usan la base
`stockflow_test` y eliminan sus tablas: debe ser exclusiva para pruebas.
Los comprobantes internos no tienen autorización electrónica del SRI.
